<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = "localhost";
$dbname = "elderly_care_system";
$user = "postgres";
$password = "37749508";

// Establish connection
$connection_string = "host=$host dbname=$dbname user=$user password=$password";
$db_connection = pg_connect($connection_string);

if (!$db_connection) {
    die("Connection failed: " . pg_last_error());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check for new messages
function hasNewMessages($db_connection, $user_id) {
    $query = "SELECT COUNT(*) as count 
              FROM messages 
              WHERE recipient_id = $1 
              AND read = false";
    $result = pg_query_params($db_connection, $query, array($user_id));
    $row = pg_fetch_assoc($result);
    return $row['count'] > 0;
}

// Function to check for new feedback responses
function hasNewFeedbackResponses($db_connection, $user_id) {
    $query = "SELECT COUNT(*) as count 
              FROM feedback 
              WHERE user_id = $1 
              AND response_status = 'responded' 
              AND response_message IS NOT NULL
              AND response_seen = false";
    $result = pg_query_params($db_connection, $query, array($user_id));
    $row = pg_fetch_assoc($result);
    return $row['count'] > 0;
}
?> 