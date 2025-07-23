<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get unread message counts with sender information
$unread_query = "SELECT 
    m.sender_id, 
    COUNT(*) as count,
    CASE 
        WHEN u.role = 'family' THEN u.username
        WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
        WHEN u.role = 'admin' THEN u.username
    END as sender_name,
    u.role as sender_role,
    MAX(m.sent_at) as latest_message_time,
    -- Add latest unread message_id for this sender
    (
        SELECT m2.message_id
        FROM messages m2
        WHERE m2.sender_id = m.sender_id
          AND m2.recipient_id = $1
          AND m2.read = FALSE
        ORDER BY m2.sent_at DESC
        LIMIT 1
    ) as message_id
FROM messages m
JOIN users u ON m.sender_id = u.user_id
LEFT JOIN staff s ON u.role = 'staff' AND u.user_id = s.user_id
WHERE m.recipient_id = $1 
AND m.read = FALSE 
GROUP BY m.sender_id, u.username, u.role, s.first_name, s.last_name
ORDER BY latest_message_time DESC";

$unread_result = pg_query_params($db_connection, $unread_query, array($_SESSION['user_id']));

$unread_counts = array();
while ($row = pg_fetch_assoc($unread_result)) {
    $unread_counts[] = array(
        'sender_id' => $row['sender_id'],
        'count' => (int)$row['count'],
        'sender_name' => $row['sender_name'],
        'sender_role' => $row['sender_role'],
        'latest_message_time' => $row['latest_message_time'],
        'message_id' => $row['message_id'] // Add message_id to response
    );
}

header('Content-Type: application/json');
echo json_encode(array(
    'hasNewMessages' => !empty($unread_counts),
    'unreadCounts' => $unread_counts,
    
)); 