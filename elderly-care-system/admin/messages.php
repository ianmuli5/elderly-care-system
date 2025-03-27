<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Get list of available contacts
$contacts_query = "WITH all_contacts AS (
    -- Get all users except current user
    SELECT 
        u.user_id as contact_id,
        CASE 
            WHEN u.role = 'family' THEN u.username
            WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
        END as contact_name,
        u.role as contact_role,
        CASE 
            WHEN u.role = 'family' THEN 'Family Member'
            WHEN u.role = 'staff' THEN s.position
        END as title,
        CASE 
            WHEN u.role = 'family' THEN 
                (SELECT string_agg(first_name || ' ' || last_name, ', ') 
                 FROM residents 
                 WHERE family_member_id = u.user_id)
            ELSE NULL
        END as related_to,
        NULL as family_member_id,
        NULL as parent_id
    FROM users u
    LEFT JOIN staff s ON u.user_id = s.user_id
    WHERE u.user_id != $1
    AND u.username IS NOT NULL
    AND u.username != ''
    UNION
    -- Get residents
    SELECT 
        r.resident_id as contact_id,
        r.first_name || ' ' || r.last_name as contact_name,
        'resident' as contact_role,
        'Resident' as title,
        u.username as related_to,
        r.family_member_id,
        r.family_member_id as parent_id
    FROM residents r
    LEFT JOIN users u ON r.family_member_id = u.user_id
    WHERE r.status = 'active'
    AND r.first_name IS NOT NULL
    AND r.last_name IS NOT NULL
    AND r.first_name != ''
    AND r.last_name != ''
)
SELECT * FROM all_contacts 
WHERE contact_name IS NOT NULL 
AND contact_name != ''
ORDER BY 
    CASE 
        WHEN contact_role = 'family' THEN 1
        WHEN contact_role = 'staff' THEN 2
        WHEN contact_role = 'resident' THEN 3
    END,
    contact_name";

$contacts_result = pg_query_params($db_connection, $contacts_query, array($_SESSION['user_id']));
if (!$contacts_result) {
    error_log("Contacts query failed: " . pg_last_error($db_connection));
    $contacts_result = false;
} else {
    // Debug: Log the number of contacts and their roles
    error_log("Number of contacts: " . pg_num_rows($contacts_result));
    error_log("SQL Query: " . $contacts_query);
    error_log("User ID: " . $_SESSION['user_id']);
    
    // Debug: Check family members directly
    $family_check_query = "SELECT user_id, username, role FROM users WHERE role = 'family'";
    $family_result = pg_query($db_connection, $family_check_query);
    error_log("Family members in users table:");
    while ($row = pg_fetch_assoc($family_result)) {
        error_log("Family member: " . $row['username'] . " (ID: " . $row['user_id'] . ")");
    }
    
    // Debug: Check staff members
    $staff_check_query = "SELECT s.staff_id, s.first_name, s.last_name, u.user_id 
                         FROM staff s 
                         LEFT JOIN users u ON s.user_id = u.user_id";
    $staff_result = pg_query($db_connection, $staff_check_query);
    error_log("Staff members:");
    while ($row = pg_fetch_assoc($staff_result)) {
        error_log("Staff member: " . $row['first_name'] . " " . $row['last_name'] . " (ID: " . $row['staff_id'] . ", User ID: " . $row['user_id'] . ")");
    }
    
    // Log contacts from main query
    error_log("Contacts from main query:");
    while ($row = pg_fetch_assoc($contacts_result)) {
        error_log("Contact: " . $row['contact_name'] . " (Role: " . $row['contact_role'] . ", ID: " . $row['contact_id'] . ")");
    }
    // Reset the result pointer
    pg_result_seek($contacts_result, 0);
}

// Get selected conversation
$selected_user = null;
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    // Get user details with role-specific information
    $user_query = "WITH contact_info AS (
        -- Get user/staff info
        SELECT 
            CASE 
                WHEN u.role = 'family' THEN u.user_id
                WHEN u.role = 'staff' THEN u.user_id
            END as user_id,
            CASE 
                WHEN u.role = 'family' THEN u.username
                WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
            END as display_name,
            u.role,
            CASE 
                WHEN u.role = 'family' THEN 'Family Member'
                WHEN u.role = 'staff' THEN s.position
            END as title,
            CASE 
                WHEN u.role = 'family' THEN 
                    (SELECT string_agg(first_name || ' ' || last_name, ', ') 
                     FROM residents 
                     WHERE family_member_id = u.user_id)
                ELSE NULL
            END as related_to,
            NULL as family_member_id
        FROM users u
        LEFT JOIN staff s ON u.user_id = s.user_id
        WHERE u.user_id = $1
        UNION
        -- Get resident info
        SELECT 
            r.resident_id as user_id,
            r.first_name || ' ' || r.last_name as display_name,
            'resident' as role,
            'Resident' as title,
            NULL as related_to,
            r.family_member_id
        FROM residents r
        WHERE r.resident_id = $1
    )
    SELECT * FROM contact_info";

    $selected_user_result = pg_query_params($db_connection, $user_query, array($_GET['user']));
    $selected_user = pg_fetch_assoc($selected_user_result);
}

// Get messages for selected conversation
$messages = array();
if ($selected_user) {
    // Determine the actual recipient ID for messages
    $recipient_id = $selected_user['user_id']; // Always use the selected user's ID directly

    // Mark messages as read
    $mark_read_query = "UPDATE messages 
                       SET read = TRUE 
                       WHERE recipient_id = $1 
                         AND sender_id = $2 
                         AND read = FALSE";
    pg_query_params($db_connection, $mark_read_query, array($_SESSION['user_id'], $recipient_id));

    // Get conversation messages
    $messages_query = "SELECT m.message_id, m.sender_id, m.recipient_id, m.content, m.sent_at, m.read, 
                             CASE 
                                 WHEN u.role = 'admin' THEN u.username
                                 WHEN u.role = 'family' THEN u.username
                                 WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
                             END as sender_name,
                             u.role as sender_role,
                             CASE 
                                 WHEN m.sender_id = $1 THEN TRUE 
                                 ELSE FALSE 
                             END as is_sender,
                             m.priority,
                             m.category
                      FROM messages m
                      JOIN users u ON m.sender_id = u.user_id
                      LEFT JOIN staff s ON u.user_id = s.user_id
                      WHERE (m.sender_id = $1 AND m.recipient_id = $2)
                         OR (m.sender_id = $2 AND m.recipient_id = $1)
                      ORDER BY m.sent_at ASC";
    $messages_result = pg_query_params($db_connection, $messages_query, array($_SESSION['user_id'], $recipient_id));
    while ($row = pg_fetch_assoc($messages_result)) {
        $messages[] = $row;
    }
}

// Get unread message counts
$unread_query = "SELECT sender_id, COUNT(*) as count 
                 FROM messages 
                 WHERE recipient_id = $1 
                   AND read = FALSE 
                 GROUP BY sender_id";
$unread_result = pg_query_params($db_connection, $unread_query, array($_SESSION['user_id']));
$unread_counts = array();
while ($row = pg_fetch_assoc($unread_result)) {
    $unread_counts[$row['sender_id']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .messages-container {
            display: flex;
            height: calc(100vh - 100px);
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 10px auto;
            max-width: 1200px;
        }

        .contacts-list {
            width: 300px;
            border-right: 1px solid #dee2e6;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .contacts-scroll {
            flex: 1;
            overflow-y: auto;
            height: calc(100% - 70px);
        }

        .contact-item {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .contact-item:hover {
            background-color: #e9ecef;
        }

        .contact-item.active {
            background-color: #e3f2fd;
        }

        .contact-info {
            flex: 1;
        }

        .contact-info h5 {
            margin: 0;
            font-size: 1rem;
        }

        .contact-info p {
            margin: 0;
            font-size: 0.85rem;
        }

        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
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

        .unread-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }
            70% {
                transform: scale(1.1);
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        .contact-item {
            position: relative;
        }

        .contact-item .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            animation: pulse 2s infinite;
        }

        .message-input {
            padding: 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .message-form {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .message-options {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .message-options select {
            flex: 1;
        }

        .input-group textarea {
            border-radius: 20px;
            padding: 10px 15px;
            resize: none;
        }

        .input-group textarea:focus {
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .input-group button {
            border-radius: 20px;
            padding: 10px 20px;
            margin-left: 10px;
        }

        /* Add notification banner styles */
        #newMessageBanner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
            z-index: 1000;
            animation: slideDown 0.5s ease-in-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }

        .notification-dot {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 10px;
            height: 10px;
            background-color: #ff4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>

    <!-- Add notification banner -->
    <div id="newMessageBanner" style="display: none;">
        <i class="fas fa-envelope me-2"></i>
        <span id="newMessageText">You have new messages!</span>
        <button type="button" class="btn-close btn-close-white float-end" onclick="dismissNotification()"></button>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="messages-container mt-4">
                    <!-- Contacts List -->
                    <div class="contacts-list" id="contactsList">
                        <div class="p-3 border-bottom">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <input type="text" class="form-control" id="searchContacts" placeholder="Search...">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <select class="form-select" id="roleFilter">
                                        <option value="all">All</option>
                                        <option value="staff">Staff</option>
                                        <option value="family">Family</option>
                                        <option value="resident">Resident</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php
                        if ($contacts_result && pg_num_rows($contacts_result) > 0):
                            while ($contact = pg_fetch_assoc($contacts_result)):
                                $has_unread = isset($unread_counts[$contact['contact_id']]) && $unread_counts[$contact['contact_id']] > 0;
                                echo '<div class="contact-item" data-contact-id="' . htmlspecialchars($contact['contact_id']) . '" data-role="' . htmlspecialchars($contact['contact_role']) . '">';
                                echo '<div class="contact-info">';
                                echo '<h5>' . htmlspecialchars($contact['contact_name']) . '</h5>';
                                echo '<p class="text-muted">' . htmlspecialchars($contact['title']) . '</p>';
                                if (!empty($contact['related_to'])) {
                                    echo '<small class="text-muted">Related to: ' . htmlspecialchars($contact['related_to']) . '</small>';
                                }
                                echo '</div>';
                                if ($has_unread) {
                                    echo '<div class="notification-dot"></div>';
                                }
                                echo '<div class="contact-actions">';
                                echo '<a href="messages.php?user=' . htmlspecialchars($contact['contact_id']) . '" class="btn btn-primary btn-sm start-chat">';
                                echo '<i class="fas fa-comments"></i> Chat';
                                echo '</a>';
                                echo '</div>';
                                echo '</div>';
                            endwhile;
                        else:
                            echo '<div class="p-3 text-center text-muted">No contacts found</div>';
                        endif;
                        ?>
                        <div id="noResults" class="p-3 text-center text-muted" style="display: none;">
                            <p>No contacts found matching your criteria</p>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="chat-area">
                        <?php if ($selected_user): ?>
                            <div class="chat-header p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($selected_user['display_name']); ?></h5>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($selected_user['title']); ?>
                                            <?php if ($selected_user['role'] === 'family' && !empty($selected_user['related_to'])): ?>
                                                <br>Related to: <?php echo htmlspecialchars($selected_user['related_to']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-danger btn-sm" onclick="clearChat(<?php echo $selected_user['user_id']; ?>)">
                                        <i class="fas fa-trash"></i> Clear Chat
                                    </button>
                                </div>
                            </div>
                            <div class="messages" id="chatMessages">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received'; ?>">
                                            <?php if ($message['sender_id'] != $_SESSION['user_id'] && !$message['read']): ?>
                                                <div class="unread-indicator">
                                                    <i class="fas fa-circle"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="message-header">
                                                <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                                                <span><?php echo date('M d, Y H:i', strtotime($message['sent_at'])); ?></span>
                                            </div>
                                            
                                            <div class="message-badges">
                                                <?php if ($message['priority'] === 'high'): ?>
                                                    <span class="badge bg-danger">High Priority</span>
                                                <?php elseif ($message['priority'] === 'urgent'): ?>
                                                    <span class="badge bg-danger">Urgent</span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($message['category']); ?></span>
                                            </div>
                                            
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted p-4">
                                        <i class="fas fa-comments fa-3x mb-3"></i>
                                        <p>No messages yet. Start a conversation!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="chat-footer p-3 border-top">
                                <form id="messageForm" onsubmit="return sendMessage(event)">
                                    <input type="hidden" name="recipient_id" value="<?php echo $selected_user['user_id']; ?>">
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="message_id" value="">
                                    <input type="hidden" name="reply_to" value="">
                                    <div id="replyPreview" style="display: none; margin-bottom: 10px; padding: 5px; background: #f5f5f5; border-radius: 5px;">
                                        <div id="replyText"></div>
                                        <a href="#" onclick="cancelReply()" style="font-size: 0.8em;">Cancel Reply</a>
                                    </div>
                                    <div class="message-options mb-2">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <select class="form-select" name="priority" required>
                                                    <option value="normal">Normal Priority</option>
                                                    <option value="high">High Priority</option>
                                                    <option value="urgent">Urgent</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" name="category" required>
                                                    <option value="general">General Inquiry</option>
                                                    <option value="medical">Medical</option>
                                                    <option value="payment">Payment</option>
                                                    <option value="visitation">Visitation</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <textarea class="form-control" name="content" placeholder="Type your message..." rows="2" required></textarea>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()" style="display: none;">Cancel Edit</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5>Select a contact to start messaging</h5>
                                    <p class="text-muted">You can message family members and staff</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const contacts = document.querySelectorAll('.contact-item');
        const searchInput = document.getElementById('searchContacts');
        const roleFilter = document.getElementById('roleFilter');

        function filterContacts() {
            const searchText = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;
            let hasVisibleContacts = false;

            contacts.forEach(contact => {
                const name = contact.querySelector('.contact-info h5').textContent.toLowerCase();
                const role = contact.dataset.role;
                const matchesSearch = name.includes(searchText);
                const matchesRole = selectedRole === 'all' || role === selectedRole;

                if (matchesSearch && matchesRole) {
                    contact.style.display = 'block';
                    hasVisibleContacts = true;
                } else {
                    contact.style.display = 'none';
                }
            });

            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            if (noResults) {
                noResults.style.display = hasVisibleContacts ? 'none' : 'block';
            }
        }

        // Add event listeners
        if (searchInput) {
            searchInput.addEventListener('input', filterContacts);
            searchInput.addEventListener('keyup', filterContacts);
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    filterContacts();
                }
            });
        }

        if (roleFilter) {
            roleFilter.addEventListener('change', filterContacts);
        }

        // Initial filter
        filterContacts();

        // Scroll chat to bottom function
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Scroll to bottom on page load
        scrollChatToBottom();

        // Handle message form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                
                // Disable submit button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                fetch('process_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear the message input
                        this.querySelector('textarea').value = '';
                        // Reload the page and scroll to bottom
                        window.location.reload();
                        setTimeout(scrollChatToBottom, 100); // Add small delay to ensure content is loaded
                    } else {
                        alert(data.message || 'Failed to send message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'An error occurred while sending the message. Please try again.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            });
        }
    });

    // Message form handling
    let currentEditId = null;
    const messageForm = document.getElementById('messageForm');
    const contentInput = messageForm?.querySelector('[name="content"]');
    const actionInput = messageForm?.querySelector('[name="action"]');
    const messageIdInput = messageForm?.querySelector('[name="message_id"]');
    const cancelEditBtn = messageForm?.querySelector('.btn-secondary');

    function checkNewMessages() {
        fetch('check_new_messages.php')
            .then(response => response.json())
            .then(data => {
                if (data.hasNewMessages) {
                    // Update the banner text with sender details
                    let messageText = 'New messages from: ';
                    let senderNames = data.unreadCounts.map(item => item.sender_name);
                    messageText += senderNames.join(', ');
                    document.getElementById('newMessageText').textContent = messageText;
                    
                    // Show banner
                    const banner = document.getElementById('newMessageBanner');
                    banner.style.display = 'block';
                    
                    // Update notification dots
                    document.querySelectorAll('.notification-dot').forEach(dot => dot.remove());
                    data.unreadCounts.forEach(item => {
                        const contactElement = document.querySelector(`[data-contact-id="${item.sender_id}"]`);
                        if (contactElement) {
                            const dot = document.createElement('div');
                            dot.className = 'notification-dot';
                            contactElement.appendChild(dot);
                        }
                    });
                }
            })
            .catch(error => console.error('Error checking messages:', error));
    }

    // Function to dismiss notification
    function dismissNotification() {
        document.getElementById('newMessageBanner').style.display = 'none';
    }

    // Check for new messages every 10 seconds
    setInterval(checkNewMessages, 10000);
    
    // Initial check when page loads
    document.addEventListener('DOMContentLoaded', checkNewMessages);

    function editMessage(messageId) {
        const messageElement = document.getElementById(`message-${messageId}`);
        if (messageElement) {
            const content = messageElement.querySelector('.content').textContent;
            contentInput.value = content;
            actionInput.value = 'edit';
            messageIdInput.value = messageId;
            currentEditId = messageId;
            cancelEditBtn.style.display = 'inline-block';
            contentInput.focus();
        }
    }

    function cancelEdit() {
        messageForm.reset();
        actionInput.value = 'send';
        messageIdInput.value = '';
        currentEditId = null;
        cancelEditBtn.style.display = 'none';
    }

    function replyToMessage(messageId, senderName, content) {
        const replyPreview = document.getElementById('replyPreview');
        const replyText = document.getElementById('replyText');
        replyText.innerHTML = `<strong>${senderName}:</strong> ${content}`;
        replyPreview.style.display = 'block';
        messageForm.querySelector('[name="reply_to"]').value = messageId;
        contentInput.focus();
    }

    function cancelReply() {
        const replyPreview = document.getElementById('replyPreview');
        replyPreview.style.display = 'none';
        messageForm.querySelector('[name="reply_to"]').value = '';
    }

    function clearChat(recipientId) {
        if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) {
            const formData = new FormData();
            formData.append('action', 'clear');
            formData.append('recipient_id', recipientId);

            fetch('process_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to clear chat');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while clearing the chat');
            });
        }
    }
    </script>
</body>
</html> 