<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$recipient_id = $_POST['recipient_id'] ?? null;
$content = $_POST['content'] ?? '';
$priority = $_POST['priority'] ?? 'normal';
$category = $_POST['category'] ?? 'general';

// Validate inputs
if (!$recipient_id || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Recipient and message content are required']);
    exit;
}

// Validate priority
$valid_priorities = ['normal', 'high', 'urgent'];
if (!in_array($priority, $valid_priorities)) {
    echo json_encode(['success' => false, 'message' => 'Invalid priority level']);
    exit;
}

// Validate category
$valid_categories = ['general', 'medical', 'payment', 'visitation', 'other'];
if (!in_array($category, $valid_categories)) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit;
}

// Verify recipient is an admin or assigned staff
$recipient_query = "SELECT user_id FROM users WHERE user_id = $1 AND (role = 'admin' OR (role = 'staff' AND EXISTS (SELECT 1 FROM residents r JOIN staff s ON r.caregiver_id = s.staff_id WHERE r.family_member_id = $2 AND s.user_id = $1)))";
$recipient_result = pg_query_params($db_connection, $recipient_query, array($recipient_id, $_SESSION['user_id']));

if (pg_num_rows($recipient_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
    exit;
}

// Insert message
$insert_query = "INSERT INTO messages (sender_id, recipient_id, content, priority, category) 
                 VALUES ($1, $2, $3, $4, $5)";
$result = pg_query_params($db_connection, $insert_query, 
    array($_SESSION['user_id'], $recipient_id, $content, $priority, $category));

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
} 