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
?> 