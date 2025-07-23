<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php';

header('Content-Type: application/json');

try {
    // Get staff's user_id
    $staff_query = "SELECT user_id FROM staff WHERE staff_id = $1";
    $staff_result = pg_query_params($db_connection, $staff_query, array($_SESSION['staff_id']));
    $staff = pg_fetch_assoc($staff_result);
    
    if (!$staff) {
        throw new Exception("Staff user not found");
    }

    // Get unread messages grouped by sender
    $query = "SELECT 
                u.user_id as sender_id,
                u.username as sender_name,
                u.role as sender_role,
                COUNT(*) as count
              FROM messages m
              JOIN users u ON m.sender_id = u.user_id
              WHERE m.recipient_id = $1
                AND m.read = FALSE
              GROUP BY u.user_id, u.username, u.role";

    $result = pg_query_params($db_connection, $query, array($staff['user_id']));
    
    $unread_counts = array();
    while ($row = pg_fetch_assoc($result)) {
        $unread_counts[] = array(
            'sender_id' => (int)$row['sender_id'],
            'sender_name' => $row['sender_name'],
            'sender_role' => $row['sender_role'],
            'count' => (int)$row['count']
        );
    }

    echo json_encode([
        'hasNewMessages' => !empty($unread_counts),
        'unreadCounts' => $unread_counts
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 