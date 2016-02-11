<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$user_id = NULL;

function authenticate(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

$app->post('/register', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('login', 'password'));
    $response = array();

    // reading post params
    $login = $app->request->post('login');
    $password = $app->request->post('password');

    $db = new DbHandler();
    $res = $db->createUser($login, $password);

    if ($res == USER_CREATED_SUCCESSFULLY) {
        $response["error"] = false;
        $response["message"] = "You are successfully registered";
    } else if ($res == USER_CREATE_FAILED) {
        $response["error"] = true;
        $response["message"] = "Oops! An error occurred while registereing";
    } else if ($res == USER_ALREADY_EXISTED) {
        $response["error"] = true;
        $response["message"] = "Sorry, this email already existed";
    }
    // echo json response
    echoRespnse(201, $response);
});

$app->post('/login', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('login', 'password'));

    // reading post params
    $login = $app->request()->post('login');
    $password = $app->request()->post('password');
    $response = array();

    $db = new DbHandler();
    // check for correct email and password
    if ($db->checkLogin($login, $password)) {
        $response["error"] = false;
        $response['user_key'] = $db->getUserKey($login);
    } else {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }

    echoRespnse(200, $response);
});

$app->get('/check_player', 'authenticate', function () {
    global $user_id;
    $response = array();
    $db = new DbHandler();

    $result = $db->checkIsSetup($user_id);

    $response["exist"] = $result;

    echoRespnse(200, $response);
});

$app->post('/create_player', 'authenticate', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('skin', 'hair', 'hair_color'));

    $response = array();
    $skin = $app->request->post('skin');
    $hair = $app->request->post('hair');
    $hair_color = $app->request->post('hair_color');

    global $user_id;
    $db = new DbHandler();

    $player_id = $db->createPlayer($user_id, $skin, $hair, $hair_color);

    if ($player_id == 0) {
        $response["error"] = false;
        $response["message"] = "Player created successfully";
        $response["player_id"] = $player_id;
        echoRespnse(201, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to create player. Please try again";
        echoRespnse(200, $response);
    }
});

$app->post('/update_player', 'authenticate', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('cloth', 'headwear'));

    global $user_id;
    $cloth = $app->request->post('cloth');
    $headwear = $app->request->post('headwear');

    $db = new DbHandler();
    $response = array();

    $response["cloth"] = $cloth;
    $result = $db->updateCloth($user_id, $cloth, $headwear);

    if ($result) {
        $response["error"] = false;
        $response["message"] = "Player updated successfully";
        echoRespnse(201, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to create player. Please try again";
        echoRespnse(200, $response);
    }
});

$app->get('/cloth', 'authenticate', function() {
    global $user_id;
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllUserCloth();

    $response["error"] = false;
    $response["cloth"] = array();

    while ($cloth = $result->fetch_assoc()) {
//        $tmp = array();
//        $tmp["name"] = $cloth["name"];
        array_push($response["cloth"], $cloth["name"]);
    }

    echoRespnse(200, $response);
});

$app->get('/headwear', 'authenticate', function() {
    global $user_id;
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllUserHeadwear();

    $response["error"] = false;
    $response["headwear"] = array();

    while ($headwear = $result->fetch_assoc()) {
//        $tmp = array();
//        $tmp["name"] = $cloth["name"];
        array_push($response["headwear"], $headwear["name"]);
    }

    echoRespnse(200, $response);
});

$app->get('/get_player', 'authenticate', function() {
    global $user_id;
    $response = array();
    $db = new DbHandler();

    // fetch task
    $result = $db->getPlayer($user_id);

    if ($result != NULL) {
        $response["error"] = false;
        $response["cloth"] = $result["cloth"];
        $response["headwear"] = $result["headwear"];
        $response["skin"] = $result["skin"];
        $response["hair"] = $result["hair"];
        $response["hair_color"] = $result["hair_color"];
        echoRespnse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "The requested resource doesn't exists";
        echoRespnse(404, $response);
    }
});

function verifyRequiredParams($required_fields)
{
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

function echoRespnse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>