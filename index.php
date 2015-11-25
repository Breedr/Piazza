<?php

require_once 'include/DbHandler.php';
require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
$user_id = NULL;

/**
 * JSON-ify response
 */
function echoResponse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
    // setting response content type to json
    $app->contentType('application/json');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
}

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $app = \Slim\Slim::getInstance();
    $request_params = json_decode($app->request()->getBody());

    if(!$request_params) {
        $response = array();
        $response["error"] = true;
        $response["message"] = 'Malformed JSON input';
        echoResponse(400, $response);
        $app->stop();
    }

    foreach ($required_fields as $field) {
        if (!array_key_exists($field, $request_params)) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' are missing or empty';
        echoResponse(400, $response);
        $app->stop();
    }
}

function validateEmail($email) {
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoResponse(400, $response);
        $app->stop();
    }
}

function authenticate(\Slim\Route $route) {
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
            $response["message"] = "Access Denied. Invalid API key";
            echoResponse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL)
                $user_id = $user["id"];
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "API Key is required";
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * Slim application routes
 */

$app->get('/', function(){
    echoResponse(200, true);
});

$app->get('/cuisine', function(){
    $db = new DbHandler();
    $response = $db->getAllCuisines();
    echoResponse(200, $response);
});

$app->get('/bancarelle', function(){
    $db = new DbHandler();
    $response = $db->getAllBancarelle();
    echoResponse(200, $response);
});

$app->post('/bancarelle', 'authenticate', function() use ($app){
    verifyRequiredParams(array("name", "primary_cuisine", "secondary_cuisine"));

    $db = new DbHandler();
    $body = json_decode($app->request->getBody(), true);
    $response = $db->insertNewBancarella($body["name"], $body["primary_cuisine"], $body["secondary_cuisine"]);

    //Log creation of venue
    global $user_id;
    error_log("User " . $user_id . " added venue " . $body["name"]);

    echoResponse(200, $response);
});

$app->post('/bancarelle/:id/rating', 'authenticate', function($bancarella_id) use ($app){
    verifyRequiredParams(array("rating"));
    global $user_id;
    $db = new DbHandler();
    $body = json_decode($app->request->getBody(), true);
    $result = $db->rateBancarella($user_id, $bancarella_id, $body['rating']);
    echoResponse(200, $result);
});


$app->post('/register', function() use ($app) {
    verifyRequiredParams(array('name', 'email', 'password'));

    $response = array();
    $body = json_decode($app->request->getBody(), true);
    $name = $body['name'];
    $email = $body['email'];
    $password = $body['password'];;

    // validate email address - 400 if invalid
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);

    switch($res){
        case USER_ALREADY_EXISTED:
            $response["error"] = true;
            $response["message"] = "Sorry, this email already existed";
            echoResponse(200, $response);
            break;
        case USER_CREATED_SUCCESSFULLY:
            $response["error"] = false;
            $response["message"] = "You are successfully registered";
            echoResponse(201, $response);
            break;
        default:
            $response["error"] = true;
            $response["message"] = "An error occurred while registering";
            echoResponse(200, $response);
            break;
    }
});

$app->post('/login', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('email', 'password'));

    // reading post params
    $body = json_decode($app->request->getBody(), true);
    $email = $body['email'];
    $password = $body['password'];
    $response = array();

    $db = new DbHandler();
    // check for correct email and password
    if ($db->checkLogin($email, $password)) {
        // get the user by email
        $response = $db->getUserByEmail($email);

        if ($response != NULL) {
            $response["error"] = false;
        } else {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    } else {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }
    echoResponse(200, $response);
});

/**
 * Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();
