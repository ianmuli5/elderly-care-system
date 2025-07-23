<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];

// Check for new feedback responses
$query = "SELECT COUNT(*) as count 
          FROM feedback 
          WHERE user_id = $1 
          AND response_status = 'responded' 
          AND response_message IS NOT NULL
          AND response_seen = false";
$result = pg_query_params($db_connection, $query, array($user_id));
$row = pg_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode([
    'hasNewResponses' => $row['count'] > 0,
    'count' => (int)$row['count']
], JSON_PRETTY_PRINT); 