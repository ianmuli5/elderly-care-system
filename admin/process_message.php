<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

require_once '../includes/config.php';
require_once '../includes/auth.php';

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

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    error_log("Unauthorized access attempt in process_message.php");
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized access']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method in process_message.php: " . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method']);
}

$action = $_POST['action'] ?? 'send';
error_log("Processing message action: " . $action);



try {
    switch ($action) {
        case 'clear':
            if (empty($_POST['recipient_id'])) {
                error_log("Missing recipient_id in clear chat request");
                sendJsonResponse(['success' => false, 'message' => 'Invalid request.']);
            }

            // Delete messages between current user and recipient
            $delete_query = "DELETE FROM messages 
                           WHERE (sender_id = $1 AND recipient_id = $2)
                              OR (sender_id = $2 AND recipient_id = $1)";
            $delete_result = pg_query_params($db_connection, $delete_query, array($_SESSION['user_id'], $_POST['recipient_id']));
            
            if ($delete_result) {
                error_log("Chat cleared successfully between users " . $_SESSION['user_id'] . " and " . $_POST['recipient_id']);
                sendJsonResponse(['success' => true, 'message' => 'Chat cleared successfully.']);
            } else {
                $error = pg_last_error($db_connection);
                error_log("Database error while clearing chat: " . $error);
                sendJsonResponse(['success' => false, 'message' => 'Error clearing chat: ' . $error]);
            }
            break;

        case 'send':
            // Validate required fields
            if (empty($_POST['recipient_id']) || empty($_POST['content'])) {
                error_log("Missing required fields in message send request");
                sendJsonResponse(['success' => false, 'message' => 'Please fill in all required fields.']);
            }

            // Log the data being sent
            error_log("Sending message to recipient_id: " . $_POST['recipient_id']);
            error_log("Message content: " . $_POST['content']);

            // Determine the actual recipient ID
            $recipient_id = $_POST['recipient_id']; // Use the selected ID directly

            // Only reroute if the selected contact is a resident (not staff or family)
            $recipient_role_query = "SELECT role FROM users WHERE user_id = $1";
            $recipient_role_result = pg_query_params($db_connection, $recipient_role_query, array($recipient_id));
            $recipient_role_row = pg_fetch_assoc($recipient_role_result);
            if ($recipient_role_row && $recipient_role_row['role'] === 'resident') {
                // If it's a resident, route to their family member
                $resident_query = "SELECT family_member_id FROM residents WHERE resident_id = $1";
                $resident_result = pg_query_params($db_connection, $resident_query, array($recipient_id));
                $resident = pg_fetch_assoc($resident_result);
                if ($resident && $resident['family_member_id']) {
                    $recipient_id = $resident['family_member_id'];
                }
            }

            // Log the routing decision
            error_log("Message routing: Original ID: " . $_POST['recipient_id'] . ", Final ID: " . $recipient_id);
            
            // Prepare data for insertion
            $reply_to = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
            $data = array(
                $_SESSION['user_id'],
                $recipient_id,
                $_POST['content'],
                $_POST['priority'] ?? 'normal',
                $_POST['category'] ?? 'general',
                $reply_to
            );

            // Insert new message
            $query = "INSERT INTO messages (sender_id, recipient_id, content, priority, category, reply_to_id, sent_at) 
                     VALUES ($1, $2, $3, $4, $5, $6, CURRENT_TIMESTAMP) 
                     RETURNING message_id, sent_at";
            
            error_log("Executing query: " . $query);
            error_log("Query parameters: " . print_r($data, true));
            
            $result = pg_query_params($db_connection, $query, array(
                $_SESSION['user_id'],
                $recipient_id,
                $_POST['content'],
                $_POST['priority'] ?? 'normal',
                $_POST['category'] ?? 'general',
                $reply_to
            ));
            
            if ($result) {
                $message = pg_fetch_assoc($result);
                error_log("Message sent successfully. Message ID: " . $message['message_id']);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Message sent successfully.',
                    'data' => [
                        'message_id' => $message['message_id'],
                        'sent_at' => date('M d, Y H:i', strtotime($message['sent_at'])),
                        'content' => $_POST['content']
                    ]
                ]);
            } else {
                $error = pg_last_error($db_connection);
                error_log("Database error while sending message: " . $error);
                error_log("PostgreSQL error code: " . pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
                error_log("PostgreSQL error message: " . pg_result_error_field($result, PGSQL_DIAG_MESSAGE_PRIMARY));
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error sending message. Please try again.'
                ]);
            }
            break;

        case 'edit':
            if (empty($_POST['message_id']) || empty($_POST['content'])) {
                error_log("Missing required fields in message edit request");
                sendJsonResponse(['success' => false, 'message' => 'Invalid request.']);
            }

            // Verify message belongs to user
            $verify_query = "SELECT message_id FROM messages WHERE message_id = $1 AND sender_id = $2";
            $verify_result = pg_query_params($db_connection, $verify_query, array($_POST['message_id'], $_SESSION['user_id']));
            
            if (pg_num_rows($verify_result) === 0) {
                error_log("Unauthorized message edit attempt for message_id: " . $_POST['message_id']);
                sendJsonResponse(['success' => false, 'message' => 'You cannot edit this message.']);
            }

            // Update message
            $update_query = "UPDATE messages SET content = $1, edited = TRUE WHERE message_id = $2 RETURNING sent_at";
            $update_result = pg_query_params($db_connection, $update_query, array($_POST['content'], $_POST['message_id']));
            
            if ($update_result) {
                $message = pg_fetch_assoc($update_result);
                error_log("Message updated successfully. Message ID: " . $_POST['message_id']);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Message updated successfully.',
                    'data' => [
                        'sent_at' => date('M d, Y H:i', strtotime($message['sent_at'])),
                        'content' => $_POST['content']
                    ]
                ]);
            } else {
                $error = pg_last_error($db_connection);
                error_log("Database error while updating message: " . $error);
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Error updating message: ' . $error
                ]);
            }
            break;

        default:
            error_log("Invalid action in process_message.php: " . $action);
            sendJsonResponse(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    error_log("Exception in process_message.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.'
    ]);
} 