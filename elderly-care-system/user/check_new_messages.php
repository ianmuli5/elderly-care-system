<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get unread message counts with sender information
$unread_query = "SELECT 
    m.sender_id, 
    COUNT(*) as count,
    CASE 
        WHEN u.role = 'admin' THEN u.username
        WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
    END as sender_name,
    u.role as sender_role
FROM messages m
JOIN users u ON m.sender_id = u.user_id
LEFT JOIN staff s ON u.role = 'staff' AND u.user_id = s.user_id
WHERE m.recipient_id = $1 
AND m.read = FALSE 
GROUP BY m.sender_id, u.username, u.role, s.first_name, s.last_name";

$unread_result = pg_query_params($db_connection, $unread_query, array($_SESSION['user_id']));

if (!$unread_result) {
    error_log("Error checking unread messages: " . pg_last_error($db_connection));
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$unread_counts = array();
while ($row = pg_fetch_assoc($unread_result)) {
    $unread_counts[] = array(
        'sender_id' => $row['sender_id'],
        'count' => (int)$row['count'],
        'sender_name' => $row['sender_name'],
        'sender_role' => $row['sender_role']
    );
}

// Debug log
error_log("Checking unread messages for user " . $_SESSION['user_id']);
error_log("Found unread messages: " . json_encode($unread_counts));

echo json_encode(array(
    'hasNewMessages' => !empty($unread_counts),
    'unreadCounts' => $unread_counts
)); 