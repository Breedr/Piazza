<?php

/**
 * Created by PhpStorm.
 * User: edgeorge
 * Date: 24/11/2015
 * Time: 16:09
 */
class DbConnect
{
    private $conn;
    function __construct() {}
    /**
     * Establish connection to database
     */
    function connect(){
        include_once dirname(__FILE__) . '/config.php';
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if(mysqli_connect_errno()){
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }
        return $this->conn;
    }
}