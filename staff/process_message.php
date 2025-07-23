<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

require_once '../includes/config.php';
require_once 'includes/auth_check.php';

// Function to send JSON response and exit
function sendJsonResponse($response) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    error_log("Unauthorized access attempt in process_message.php");
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized access']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in process_message.php: " . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method']);
}

try {

    
    // Get staff's user_id
    $staff_query = "SELECT user_id FROM staff WHERE staff_id = $1";
    $staff_result = pg_query_params($db_connection, $staff_query, array($_SESSION['staff_id']));
    
    if (!$staff_result) {
        error_log("Database error in staff query: " . pg_last_error($db_connection));
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . pg_last_error($db_connection)]);
    }
    
    $staff = pg_fetch_assoc($staff_result);
    
    if (!$staff) {
        error_log("Staff user not found for staff_id: " . $_SESSION['staff_id']);
        sendJsonResponse(['success' => false, 'message' => 'Staff user not found']);
    }



    // Validate input
    if (empty($_POST['recipient_id']) || empty($_POST['content'])) {
        error_log("Missing required fields in message send request");
        sendJsonResponse(['success' => false, 'message' => 'Please fill in all required fields.']);
    }

    $recipient_id = filter_input(INPUT_POST, 'recipient_id', FILTER_VALIDATE_INT);
    $content = trim(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING));
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING) ?: 'normal';
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING) ?: 'general';



    // Verify recipient is an admin or a family member of a resident assigned to this staff
    $recipient_query = "SELECT user_id FROM users WHERE user_id = $1 AND (role = 'admin' OR (role = 'family' AND EXISTS (SELECT 1 FROM residents WHERE family_member_id = $1 AND caregiver_id = $2)))";
    $recipient_result = pg_query_params($db_connection, $recipient_query, array($recipient_id, $_SESSION['staff_id']));
    
    if (!$recipient_result) {
        error_log("Database error in recipient query: " . pg_last_error($db_connection));
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . pg_last_error($db_connection)]);
    }
    
    if (pg_num_rows($recipient_result) === 0) {
        error_log("Invalid recipient - user_id: $recipient_id is not an admin or assigned family");
        sendJsonResponse(['success' => false, 'message' => 'Invalid recipient']);
    }

    // Insert message
    $insert_query = "INSERT INTO messages (sender_id, recipient_id, content, priority, category) 
                    VALUES ($1, $2, $3, $4, $5)";
    $result = pg_query_params(
        $db_connection,
        $insert_query,
        array($staff['user_id'], $recipient_id, $content, $priority, $category)
    );

    if ($result) {
        error_log("Message sent successfully from staff " . $staff['user_id'] . " to admin " . $recipient_id);
        sendJsonResponse(['success' => true]);
    } else {
        error_log("Failed to send message: " . pg_last_error($db_connection));
        sendJsonResponse(['success' => false, 'message' => 'Failed to send message: ' . pg_last_error($db_connection)]);
    }
} catch (Exception $e) {
    error_log("Error in process_message.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while sending the message'
    ]);
}
?> 