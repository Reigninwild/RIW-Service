<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function createUser($login, $password) {
        require_once 'PassHash.php';
        $response = array();

        if (!$this->isUserExists($login)) {
            $password_hash = PassHash::hash($password);

            $user_key = $this->generateApiKey();
            $money = 0;

            $stmt = $this->conn->prepare("INSERT INTO profile(login, password, money, user_key) values(?, ?, ?, ?)");
            $stmt->bind_param("ssis", $login, $password_hash, $money, $user_key);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            } else {
                return USER_CREATE_FAILED;
            }
        } else {
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }

    public function createPlayer($profile_id, $skin, $hair, $hair_color) {
        $cloth = 0;
        $headwear = 0;

        $stmt = $this->conn->prepare("INSERT INTO player(profile_id, skin, cloth, headwear, hair, hair_color) values(?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiii", $profile_id, $skin, $cloth, $headwear, $hair, $hair_color);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return USER_CREATED_SUCCESSFULLY;
        } else {
            return NULL;
        }
    }

    public function checkLogin($login, $password) {
        $stmt = $this->conn->prepare("SELECT password FROM profile WHERE login = ?");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            $stmt->close();

            return FALSE;
        }
    }

    private function isUserExists($login) {
        $stmt = $this->conn->prepare("SELECT profile_id from profile WHERE login = ?");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function getUserId($user_key) {
        $stmt = $this->conn->prepare("SELECT id FROM profile WHERE user_key = ?");
        $stmt->bind_param("s", $user_key);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    public function isValidApiKey($user_key) {
        $stmt = $this->conn->prepare("SELECT id from profile WHERE user_key = ?");
        $stmt->bind_param("s", $user_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    public function checkIsSetup($profile_id) {
        $stmt = $this->conn->prepare("SELECT * FROM player WHERE profile_id = ?");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $stmt->store_result();
        $num_row = $stmt->num_rows;
        $stmt->close();
        return $num_row > 0;
    }

    public function updateCloth($user_id, $cloth, $headwear) {
        $stmt = $this->conn->prepare("UPDATE player SET cloth = ?, headwear = ? WHERE profile_id = ?");
        $stmt->bind_param("ssi", $cloth, $headwear, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    public function getUserKey($login) {
        $stmt = $this->conn->prepare("SELECT user_key FROM profile WHERE login = ?");
        $stmt->bind_param("s", $login);
        if ($stmt->execute()) {
            $stmt->bind_result($user_key);
            $stmt->fetch();
            $stmt->close();
            return $user_key;
        } else {
            return NULL;
        }
    }

    public function getAllUserCloth(/*$user_id*/) {
        $stmt = $this->conn->prepare("SELECT name FROM cloth WHERE cost = 0");
        //$stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cloth = $stmt->get_result();
        $stmt->close();
        return $cloth;
    }

    public function getAllUserHeadwear(/*$user_id*/) {
        $stmt = $this->conn->prepare("SELECT name FROM headwear WHERE cost = 0");
        //$stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cloth = $stmt->get_result();
        $stmt->close();
        return $cloth;
    }

    public function getPlayer($user_id) {
        $stmt = $this->conn->prepare("SELECT cloth, headwear, skin, hair, hair_color from player WHERE profile_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($cloth, $headwear, $skin, $hair, $hair_color);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["cloth"] = $cloth;
            $res["headwear"] = $headwear;
            $res["skin"] = $skin;
            $res["hair"] = $hair;
            $res["hair_color"] = $hair_color;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }




















    public function createTask($user_id, $task) {
        $stmt = $this->conn->prepare("INSERT INTO tasks(task) VALUES(?)");
        $stmt->bind_param("s", $task);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $new_task_id = $this->conn->insert_id;
            $res = $this->createUserTask($user_id, $new_task_id);
            if ($res) {
                return $new_task_id;
            } else {
                return NULL;
            }
        } else {
            return NULL;
        }
    }

    public function getTask($task_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT t.id, t.task, t.status, t.created_at from tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        if ($stmt->execute()) {
            $res = array();
            $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["id"] = $id;
            $res["task"] = $task;
            $res["status"] = $status;
            $res["created_at"] = $created_at;
            $stmt->close();
            return $res;
        } else {
            return NULL;
        }
    }

    public function getAllUserTasks($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM tasks t, user_tasks ut WHERE t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }

    public function updateTask($user_id, $task_id, $task, $status) {
        $stmt = $this->conn->prepare("UPDATE tasks t, user_tasks ut set t.task = ?, t.status = ? WHERE t.id = ? AND t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $task, $status, $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    public function deleteTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("DELETE t FROM tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_tasks(user_id, task_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $task_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }
}

?>
