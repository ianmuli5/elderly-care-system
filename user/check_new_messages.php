<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];

// Get unread message counts with sender information
$query = "SELECT 
    m.sender_id, 
    COUNT(*) as count,
    CASE 
        WHEN u.role = 'admin' THEN u.username
        WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
    END as sender_name,
    u.role as sender_role,
    MAX(m.sent_at) as latest_message_time
FROM messages m
JOIN users u ON m.sender_id = u.user_id
LEFT JOIN staff s ON u.role = 'staff' AND u.user_id = s.user_id
WHERE m.recipient_id = $1 
AND m.read = FALSE 
GROUP BY m.sender_id, u.username, u.role, s.first_name, s.last_name
ORDER BY latest_message_time DESC";

$result = pg_query_params($db_connection, $query, array($user_id));
$unread_counts = array();

while ($row = pg_fetch_assoc($result)) {
    $unread_counts[] = array(
        'sender_id' => $row['sender_id'],
        'count' => (int)$row['count'],
        'sender_name' => $row['sender_name'],
        'sender_role' => $row['sender_role'],
        'latest_message_time' => $row['latest_message_time']
    );
}

header('Content-Type: application/json');
echo json_encode([
    'hasNewMessages' => !empty($unread_counts),
    'unreadCounts' => $unread_counts,
    
]); 