<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php';

// Handle message deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = $_GET['delete'];
    $delete_query = "DELETE FROM messages WHERE message_id = $1 AND (sender_id = $2 OR recipient_id = $2)";
    pg_query_params($db_connection, $delete_query, array($message_id, $_SESSION['user_id']));
    header("Location: messages.php");
    exit;
}

// Get staff's user_id
$staff_query = "SELECT s.user_id 
                FROM staff s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.staff_id = $1 AND u.role = 'staff'";
$staff_result = pg_query_params($db_connection, $staff_query, array($_SESSION['staff_id']));
$staff = pg_fetch_assoc($staff_result);
$user_id = $staff['user_id'];

// Debug log to file
$debug_log = fopen(__DIR__ . '/debug.log', 'a');
fwrite($debug_log, "\n=== " . date('Y-m-d H:i:s') . " ===\n");
fwrite($debug_log, "Staff user_id: " . $user_id . "\n");
fwrite($debug_log, "Staff staff_id: " . $_SESSION['staff_id'] . "\n");
fwrite($debug_log, "Session data: " . print_r($_SESSION, true) . "\n");

// Get list of available contacts (admins only)
$contacts_query = "SELECT 
    u.user_id as contact_id,
    u.username as contact_name,
    'admin' as contact_role,
    'Administrator' as title
                  FROM users u 
                  WHERE u.role = 'admin' 
                  ORDER BY u.username";

$contacts_result = pg_query($db_connection, $contacts_query);

// Get selected conversation
$selected_user = null;
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    $user_query = "SELECT 
                    u.user_id,
                    u.username as display_name,
                    u.role,
                    CASE 
                        WHEN u.role = 'admin' THEN 'Administrator'
                        ELSE 'Unknown'
                    END as title
                FROM users u
                WHERE u.user_id = $1 AND u.role = 'admin'";
    
    $selected_user_result = pg_query_params($db_connection, $user_query, array($_GET['user']));
    $selected_user = pg_fetch_assoc($selected_user_result);
}

// Get conversation list (recent messages)
$conversations_query = "SELECT DISTINCT 
                        CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                        END as other_user_id,
                        u.username as display_name,
                        u.role,
                        'Administrator' as title,
                        (SELECT content 
                         FROM messages 
                         WHERE (sender_id = $1 AND recipient_id = CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                         END)
                         OR (sender_id = CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                         END AND recipient_id = $1)
                         ORDER BY sent_at DESC 
                         LIMIT 1) as last_message,
                        (SELECT sent_at 
                         FROM messages 
                         WHERE (sender_id = $1 AND recipient_id = CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                         END)
                         OR (sender_id = CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                         END AND recipient_id = $1)
                         ORDER BY sent_at DESC 
                         LIMIT 1) as last_message_time,
                        (SELECT COUNT(*) 
                         FROM messages 
                         WHERE recipient_id = $1 
                         AND sender_id = CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                         END 
                         AND read = FALSE) as unread_count
                    FROM messages m
                    JOIN users u ON CASE 
                        WHEN m.sender_id = $1 THEN m.recipient_id = u.user_id
                        ELSE m.sender_id = u.user_id
                    END
                    WHERE (m.sender_id = $1 OR m.recipient_id = $1)
                    AND u.role = 'admin'
                    ORDER BY last_message_time DESC";
$conversations_result = pg_query_params($db_connection, $conversations_query, array($user_id));

// Get messages for selected conversation
$messages = array();
if ($selected_user) {
    // Mark messages as read
    $mark_read_query = "UPDATE messages 
                       SET read = TRUE 
                       WHERE recipient_id = $1 
                         AND sender_id = $2 
                         AND read = FALSE";
    pg_query_params($db_connection, $mark_read_query, array($user_id, $selected_user['user_id']));

    // Get conversation messages
$messages_query = "SELECT m.*, 
                         u.username as sender_name,
                         u.role as sender_role,
                             CASE 
                                 WHEN m.sender_id = $1 THEN TRUE 
                                 ELSE FALSE 
                             END as is_sender,
                             m.priority,
                             m.category
                  FROM messages m
                  JOIN users u ON m.sender_id = u.user_id
                      WHERE (m.sender_id = $1 AND m.recipient_id = $2)
                         OR (m.sender_id = $2 AND m.recipient_id = $1)
                  ORDER BY m.sent_at ASC";

    // Debug log the query
    fwrite($debug_log, "Messages query: " . $messages_query . "\n");
    fwrite($debug_log, "User ID: " . $user_id . "\n");
    fwrite($debug_log, "Selected user ID: " . $selected_user['user_id'] . "\n");

    $messages_result = pg_query_params($db_connection, $messages_query, array($user_id, $selected_user['user_id']));
    while ($row = pg_fetch_assoc($messages_result)) {
        // Debug log each message
        fwrite($debug_log, "Message ID: " . $row['message_id'] . "\n");
        fwrite($debug_log, "Sender ID: " . $row['sender_id'] . "\n");
        fwrite($debug_log, "Recipient ID: " . $row['recipient_id'] . "\n");
        fwrite($debug_log, "Is sender: " . ($row['is_sender'] ? 'true' : 'false') . "\n");
        fwrite($debug_log, "Message class: " . ($row['is_sender'] ? 'sent' : 'received') . "\n");
        fwrite($debug_log, "---\n");
        $messages[] = $row;
    }
}
fclose($debug_log);

// Get unread message counts
$unread_query = "SELECT sender_id, COUNT(*) as count 
                 FROM messages 
                 WHERE recipient_id = $1 
                 AND read = FALSE 
                 GROUP BY sender_id";
$unread_result = pg_query_params($db_connection, $unread_query, array($user_id));
$unread_counts = array();
while ($row = pg_fetch_assoc($unread_result)) {
    $unread_counts[$row['sender_id']] = $row['count'];
}

// Get total unread count for navbar
$total_unread_query = "SELECT COUNT(*) as unread_count 
                       FROM messages 
                       WHERE recipient_id = $1 
                         AND read = FALSE";
$total_unread_result = pg_query_params($db_connection, $total_unread_query, array($user_id));
$messages_count = pg_fetch_assoc($total_unread_result)['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            padding-top: 56px;
        }
        
        .navbar {
            background-color: #4e73df;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .nav-link:hover {
            color: white !important;
        }

        .nav-link.active {
            color: white !important;
            font-weight: bold;
        }
        
        .messages-container {
            display: flex;
            height: calc(100vh - 76px);
            background: white;
            margin: 10px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .contacts-list {
            width: 300px;
            border-right: 1px solid #e3e6f0;
            overflow-y: auto;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #f8f9fc;
        }
        
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #f8f9fc;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            margin: 10px 0;
            padding: 12px;
            border-radius: 10px;
            max-width: 60%;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .message.sent {
            background-color: #4e73df;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        
        .message.received {
            background-color: #28a745;
            color: white;
            align-self: flex-start;
            border-bottom-left-radius: 2px;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 0.85em;
        }
        
        .message.sent .message-header {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message.received .message-header {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message-badges {
            margin-bottom: 10px;
        }
        
        .message-content {
            white-space: pre-wrap;
            color: white;
        }
        
        .message.sent .badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: white;
        }
        
        .message.received .badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: white;
        }

        .chat-input {
            padding: 20px;
            background-color: white;
            border-top: 1px solid #e3e6f0;
        }

        .contact-item {
            padding: 15px;
            border-bottom: 1px solid #e3e6f0;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .contact-item:hover {
            background-color: #f8f9fc;
        }

        .contact-info h5 {
            margin: 0;
            font-size: 1rem;
        }

        .contact-info p {
            margin: 5px 0 0;
            font-size: 0.85rem;
        }

        .notification-dot {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 10px;
            height: 10px;
            background-color: #e74a3b;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .priority-high { background-color: #e74a3b !important; color: white !important; }
        .priority-urgent { background-color: #e74a3b !important; color: white !important; }
        .priority-normal { background-color: #858796 !important; color: white !important; }

        .category-medical { background-color: #4e73df !important; }
        .category-payment { background-color: #1cc88a !important; }
        .category-visitation { background-color: #f6c23e !important; }
        .category-general { background-color: #858796 !important; }
        .category-other { background-color: #858796 !important; }

        .badge {
            font-size: 0.8em;
            padding: 0.35em 0.65em;
            margin-right: 0.5em;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-user-md me-2"></i>Staff Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="messages.php">
                            <i class="fas fa-envelope me-2"></i>Messages
                            <?php if ($messages_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $messages_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['staff_name']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Update notification banner -->
    <div id="newMessageBanner" class="alert alert-success alert-dismissible fade show" 
         style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; margin: 0; border-radius: 0; text-align: center; background-color: #28a745; color: white;">
        <div class="container">
            <i class="fas fa-envelope me-2"></i>
            <span id="newMessageText"></span>
            <button type="button" class="btn-close" onclick="dismissNotification()" aria-label="Close"></button>
        </div>
    </div>

    <div class="messages-container">
        <!-- Contacts List -->
        <div class="contacts-list">
            <div class="p-3 border-bottom">
                <input type="text" class="form-control" id="searchContacts" placeholder="Search contacts...">
            </div>
            <?php if ($contacts_result && pg_num_rows($contacts_result) > 0): ?>
                <?php while ($contact = pg_fetch_assoc($contacts_result)): 
                    $has_unread = isset($unread_counts[$contact['contact_id']]) && $unread_counts[$contact['contact_id']] > 0;
                ?>
                    <div class="contact-item" onclick="window.location.href='messages.php?user=<?php echo $contact['contact_id']; ?>'">
                        <div class="contact-info">
                            <h5><?php echo htmlspecialchars($contact['contact_name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($contact['title']); ?></p>
                        </div>
                        <?php if ($has_unread): ?>
                            <div class="notification-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-3 text-center text-muted">No contacts available</div>
            <?php endif; ?>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($selected_user): ?>
                <div class="chat-header p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($selected_user['display_name']); ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($selected_user['title']); ?></small>
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="clearChat(<?php echo $selected_user['user_id']; ?>)">
                            <i class="fas fa-trash"></i> Clear Chat
                        </button>
                    </div>
                </div>

                <div class="messages" id="chatMessages">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $message): 
                            // Debug output
                            error_log("Message sender_id: " . $message['sender_id']);
                            error_log("Current user_id: " . $user_id);
                            error_log("Is sender: " . ($message['is_sender'] ? 'true' : 'false'));
                            error_log("Message class: " . ($message['is_sender'] ? 'sent' : 'received'));
                        ?>
                            <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <?php if (!$message['is_sender'] && !$message['read']): ?>
                            <div class="unread-indicator">
                                <i class="fas fa-circle"></i>
                            </div>
                        <?php endif; ?>
                        <div class="message-header">
                            <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                            <span><?php echo date('M d, Y H:i', strtotime($message['sent_at'])); ?></span>
                        </div>
                                <?php if ($message['priority'] !== 'normal' || $message['category'] !== 'general'): ?>
                        <div class="message-badges">
                                        <?php if ($message['priority'] !== 'normal'): ?>
                                            <span class="badge bg-danger">
                                                <?php echo ucfirst($message['priority']); ?> Priority
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($message['category'] !== 'general'): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($message['category']); ?>
                                            </span>
                            <?php endif; ?>
                        </div>
                                <?php endif; ?>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted mt-4">No messages yet. Start a conversation!</div>
                    <?php endif; ?>
                </div>

                <div class="chat-input">
                    <form id="messageForm">
                        <input type="hidden" name="recipient_id" value="<?php echo $selected_user['user_id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <select name="priority" class="form-select">
                                    <option value="normal">Normal Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="category" class="form-select">
                                    <option value="general">General</option>
                                    <option value="medical">Medical</option>
                                    <option value="payment">Payment</option>
                                    <option value="visitation">Visitation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="input-group">
                                    <textarea name="content" class="form-control" rows="2" placeholder="Type your message..." required></textarea>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center text-muted">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <h5>Select a contact to start messaging</h5>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll chat to bottom
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Search contacts
        document.getElementById('searchContacts').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.contact-item').forEach(item => {
                const name = item.querySelector('h5').textContent.toLowerCase();
                const title = item.querySelector('p').textContent.toLowerCase();
                if (name.includes(searchTerm) || title.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Clear chat
        function clearChat(userId) {
            if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) {
                window.location.href = 'clear_chat.php?user=' + userId;
            }
        }

        // Handle form submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            fetch('process_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server response:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Clear the message input
                    this.querySelector('textarea').value = '';
                    // Reload the page and scroll to bottom
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to send message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message: ' + error.message);
            })
            .finally(() => {
                // Re-enable submit button and restore original text
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });

        // Check for new messages
        function checkNewMessages() {
            fetch('check_new_messages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasNewMessages) {
                        // Format the notification message
                        let messageText = '';
                        data.unreadCounts.forEach((item, index) => {
                            if (index > 0) {
                                messageText += index === data.unreadCounts.length - 1 ? ' and ' : ', ';
                            }
                            messageText += `${item.sender_name}${item.sender_role === 'admin' ? ' (Administrator)' : ''}`;
                            if (item.count > 1) {
                                messageText += ` (${item.count} messages)`;
                            }
                        });
                        
                        messageText = `New message${data.unreadCounts.length > 1 || data.unreadCounts[0].count > 1 ? 's' : ''} from ${messageText}`;
                        
                        // Show banner with message
                        const banner = document.getElementById('newMessageBanner');
                        const messageElement = document.getElementById('newMessageText');
                        if (banner && messageElement) {
                            messageElement.textContent = messageText;
                            banner.style.display = 'block';
                            banner.classList.add('show');
                        }

                        // Reload the page if we're in a conversation with one of the senders
                        const currentUser = new URLSearchParams(window.location.search).get('user');
                        if (currentUser && data.unreadCounts.some(item => item.sender_id === parseInt(currentUser))) {
                            window.location.reload();
                        }
                    }
                })
                .catch(error => console.error('Error checking messages:', error));
        }

        // Function to dismiss notification
        function dismissNotification() {
            const banner = document.getElementById('newMessageBanner');
            if (banner) {
                banner.classList.remove('show');
                setTimeout(() => {
                    banner.style.display = 'none';
                }, 150);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to bottom on page load
            scrollChatToBottom();
            
            // Start checking for new messages
            setInterval(checkNewMessages, 10000);
            checkNewMessages();
        });
    </script>
</body>
</html> 