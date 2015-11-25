<?php

/**
 * Created by PhpStorm.
 * User: edgeorge
 * Date: 24/11/2015
 * Time: 16:14
 */
class DbHandler
{
    private $conn;
    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        //Open db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateApiKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO `piazza_db`.`user`(`email`,`name`, `password_hash`, `api_key`)
                                        VALUES(?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $name, $password_hash, $api_key);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        require_once 'PassHash.php';
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM `piazza_db`.`user` WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id from `piazza_db`.`user` WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT id, email, name, api_key  FROM `piazza_db`.`user` WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM `piazza_db`.`user` WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM `piazza_db`.`user` WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from `piazza_db`.`user` WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }



    public function getAllBancarelle() {
        $stmt = $this->conn->prepare("SELECT * FROM piazza_db.bancarelle order by date_added ASC");
        $stmt->execute();
        $bancarelle = array();
        $result = $stmt->get_result();
        while($bancarella = $result->fetch_assoc())
        {
            array_push($bancarelle, $bancarella);
        }
        $stmt->close();
        return $bancarelle;
    }


    public function getAllCuisines() {
        $stmt = $this->conn->prepare("SELECT * FROM piazza_db.cuisine ORDER BY name ASC");
        $stmt->execute();
        $cuisines = array();
        $result = $stmt->get_result();
        while($cuisine = $result->fetch_assoc())
        {
            array_push($cuisines, $cuisine);
        }
        $stmt->close();
        return $cuisines;
    }


    public function insertNewBancarella($name, $primary_cuisine_id, $secondary_cuisine_id)
    {
        $stmt = $this->conn->prepare("INSERT INTO `piazza_db`.`bancarelle`
                                    (`name`, `primary_cuisine`, `secondary_cuisine`, `date_added`)
                                    VALUES (?, ?, ?, ?);");

        if ($stmt) {
            $datetime = date_create()->format('Y-m-d H:i:s');
            $stmt->bind_param("siis", $name, $primary_cuisine_id, $secondary_cuisine_id, $datetime);
            $stmt_result = $stmt->execute();
            $stmt->close();

            if ($stmt_result) {

                $result["error"] = false;
                $result["created_id"] = $this->conn->insert_id;
                return $result;
            }
        }
        $result["error"] = true;
        $result["message"] = "Failed to insert " . $name;
        return $result;
    }

    private function updateRatingForBancarella($bancarella_id) {

        $stmt = $this->conn->prepare("UPDATE piazza_db.bancarelle b SET b.rating =
                                        (SELECT AVG(rating) AS avg_rating FROM piazza_db.rating
                                            WHERE bancarella_id = ?)
                                        WHERE b.id = ?;");
        $stmt->bind_param("ii", $bancarella_id, $bancarella_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }


    public function rateBancarella($user_id, $bancarella_id, $rating)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS ratings FROM piazza_db.rating WHERE user_id = ? AND bancarella_id = ?;");
        $stmt->bind_param("ii", $user_id, $bancarella_id);
        $stmt->execute();
        $stmt->bind_result($num_rows);
        $stmt->fetch();
        $stmt->close();
        $stmt_result = NULL;
        if($num_rows > 0){
            $stmt = $this->conn->prepare("UPDATE `piazza_db`.`rating` SET `rating`= ?
                                          WHERE `user_id`= ? AND `bancarella_id` = ?;");
            $stmt->bind_param("iii", $rating, $user_id, $bancarella_id);
            $stmt_result = $stmt->execute();
            $stmt->close();
        }else{
            $stmt = $this->conn->prepare("INSERT INTO `piazza_db`.`rating`(`rating`, `user_id`, `bancarella_id`)
                                          VALUES (?, ?, ?);");
            $stmt->bind_param("iii", $rating, $user_id, $bancarella_id);
            $stmt_result = $stmt->execute();
            $stmt->close();
        }

        if($stmt_result && $this->updateRatingForBancarella($bancarella_id)) {
            error_log("User " . $user_id . " rated bancarella " . $bancarella_id . " rating " . $rating);
            $result["error"] = false;
            $result["message"] = "Rating successful";
            return $result;
        }else{
            $result["error"] = true;
            $result["message"] = "Failed to give rating";
            return $result;
        }

    }

}