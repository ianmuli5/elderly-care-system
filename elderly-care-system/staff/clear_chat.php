<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php';

// Get staff's user_id
$staff_query = "SELECT user_id FROM staff WHERE staff_id = $1";
$staff_result = pg_query_params($db_connection, $staff_query, array($_SESSION['staff_id']));
$staff = pg_fetch_assoc($staff_result);

if (!$staff) {
    header("Location: error.php");
    exit;
}

// Get the user ID to clear chat with
$other_user_id = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);

if (!$other_user_id) {
    header("Location: messages.php");
    exit;
}

// Verify the other user is an admin
$verify_query = "SELECT user_id FROM users WHERE user_id = $1 AND role = 'admin'";
$verify_result = pg_query_params($db_connection, $verify_query, array($other_user_id));

if (pg_num_rows($verify_result) === 0) {
    header("Location: messages.php");
    exit;
}

// Delete all messages between these users
$delete_query = "DELETE FROM messages 
                WHERE (sender_id = $1 AND recipient_id = $2)
                   OR (sender_id = $2 AND recipient_id = $1)";
pg_query_params($db_connection, $delete_query, array($staff['user_id'], $other_user_id));

// Redirect back to messages
header("Location: messages.php?user=" . $other_user_id);
exit;
?> 