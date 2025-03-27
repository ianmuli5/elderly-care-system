<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$recipient_id = $data['recipient_id'] ?? null;

if (!$recipient_id) {
    echo json_encode(['success' => false, 'message' => 'Recipient ID is required']);
    exit;
}

// Delete messages between the current user and the recipient
$delete_query = "DELETE FROM messages 
                 WHERE (sender_id = $1 AND recipient_id = $2)
                 OR (sender_id = $2 AND recipient_id = $1)";
$result = pg_query_params($db_connection, $delete_query, array($_SESSION['user_id'], $recipient_id));

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear chat']);
} 