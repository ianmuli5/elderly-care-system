<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Check if feedback_id is provided
if (!isset($_POST['feedback_id'])) {
    http_response_code(400);
    exit('Missing feedback_id');
}

$feedback_id = intval($_POST['feedback_id']);
$user_id = $_SESSION['user_id'];

// Update the feedback to mark response as seen
$query = "UPDATE feedback 
          SET response_seen = true 
          WHERE feedback_id = $1 
          AND user_id = $2";
$result = pg_query_params($db_connection, $query, array($feedback_id, $user_id));

if ($result) {
    http_response_code(200);
    echo 'Response marked as seen';
} else {
    http_response_code(500);
    echo 'Error updating feedback';
} 