<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    header("Location: ../index.php");
    exit;
}

// Handle message deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $message_id = $_GET['delete'];
    $delete_query = "DELETE FROM messages WHERE message_id = $1 AND (sender_id = $2 OR recipient_id = $2)";
    pg_query_params($db_connection, $delete_query, array($message_id, $_SESSION['user_id']));
    header("Location: messages.php");
    exit;
}

// Get user's ID
$user_id = $_SESSION['user_id'];

// Get list of available contacts (admins only)
$contacts_query = "SELECT 
    u.user_id as contact_id,
    u.username as contact_name,
    'admin' as contact_role,
    'Administrator' as title
                  FROM users u 
                  WHERE u.role = 'admin' 
AND u.user_id != $1
ORDER BY u.username";

if (!($contacts_result = pg_query_params($db_connection, $contacts_query, array($_SESSION['user_id'])))) {
    error_log("Contacts query failed: " . pg_last_error($db_connection));
    $contacts_result = false;
}

// Get selected conversation
$selected_user = null;
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    $user_query = "WITH user_details AS (
        SELECT 
                    CASE 
                        WHEN u.role = 'admin' THEN u.user_id
                        WHEN u.role = 'staff' THEN s.staff_id
                    END as user_id,
                    CASE 
                        WHEN u.role = 'admin' THEN u.username
                        WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
                    END as display_name,
                    u.role,
                    CASE 
                        WHEN u.role = 'admin' THEN 'Administrator'
                        WHEN u.role = 'staff' THEN s.position
                    END as title
                FROM users u
                LEFT JOIN staff s ON u.user_id = s.user_id
                WHERE (u.role = 'admin' AND u.user_id = $1)
                   OR (u.role = 'staff' AND s.staff_id = $1 
                       AND s.staff_id IN (
                           SELECT caregiver_id 
                           FROM residents 
                           WHERE family_member_id = $2
               ))
    )
    SELECT * FROM user_details";
    
    $selected_user_result = pg_query_params($db_connection, $user_query, array($_GET['user'], $_SESSION['user_id']));
    $selected_user = pg_fetch_assoc($selected_user_result);
}

// Get conversation list (recent messages)
$conversations_query = "SELECT DISTINCT 
                        CASE 
                            WHEN m.sender_id = $1 THEN m.recipient_id
                            ELSE m.sender_id
                        END as other_user_id,
                        CASE 
                            WHEN u.role = 'admin' THEN u.username
                            WHEN u.role = 'staff' THEN s.first_name || ' ' || s.last_name
                        END as display_name,
                        u.role,
                        CASE 
                            WHEN u.role = 'admin' THEN 'Administrator'
                            WHEN u.role = 'staff' THEN s.position
                        END as title,
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
                    LEFT JOIN staff s ON u.role = 'staff' AND u.user_id = s.user_id
                    WHERE (m.sender_id = $1 OR m.recipient_id = $1)
                    AND (u.role = 'admin' 
                         OR (u.role = 'staff' 
                             AND s.staff_id IN (
                                 SELECT caregiver_id 
                                 FROM residents 
                                 WHERE family_member_id = $1
                             )))
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
    pg_query_params($db_connection, $mark_read_query, array($_SESSION['user_id'], $selected_user['user_id']));

    // Get conversation messages
    $messages_query = "SELECT m.*, 
                             u.username as sender_name,
                             u.role as sender_role,
                             CASE WHEN m.sender_id = $1 THEN TRUE ELSE FALSE END as is_sender,
                             m.priority,
                             m.category
                      FROM messages m
                      JOIN users u ON m.sender_id = u.user_id
                      WHERE (m.sender_id = $1 AND m.recipient_id = $2)
                         OR (m.sender_id = $2 AND m.recipient_id = $1)
                      ORDER BY m.sent_at ASC";
    $messages_result = pg_query_params($db_connection, $messages_query, array($_SESSION['user_id'], $selected_user['user_id']));
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

// Include the header
require_once 'includes/header.php';
?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Update notification banner -->
                <div id="newMessageBanner" class="alert alert-success alert-dismissible fade show" style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; margin: 0; border-radius: 0; text-align: center; background-color: #28a745; color: white;">
                    <div class="container">
                        <i class="fas fa-envelope me-2"></i>
                        <span id="newMessageText"></span>
                        <button type="button" class="btn-close" onclick="dismissNotification()" aria-label="Close"></button>
                    </div>
                </div>

                <div class="messages-container mt-4">
                    <!-- Contacts List -->
                    <div class="contacts-list" id="contactsList">
                        <div class="p-3 border-bottom">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <input type="text" class="form-control" id="searchContacts" placeholder="Search...">
                                </div>
                            </div>
                        </div>
                        <?php if ($contacts_result && pg_num_rows($contacts_result) > 0): ?>
                            <?php while ($contact = pg_fetch_assoc($contacts_result)): 
                                $has_unread = isset($unread_counts[$contact['contact_id']]) && $unread_counts[$contact['contact_id']] > 0;
                            ?>
                                <div class="contact-item" data-contact-id="<?php echo htmlspecialchars($contact['contact_id']); ?>" data-role="<?php echo htmlspecialchars($contact['contact_role']); ?>">
                                    <div class="contact-info">
                                        <h5><?php echo htmlspecialchars($contact['contact_name']); ?></h5>
                                        <p class="text-muted"><?php echo htmlspecialchars($contact['title']); ?></p>
                                    </div>
                                    <?php if ($has_unread): ?>
                                        <div class="notification-dot"></div>
                                    <?php endif; ?>
                                    <div class="contact-actions">
                                        <a href="messages.php?user=<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                           class="btn btn-primary btn-sm start-chat">
                                            <i class="fas fa-comments"></i> Chat
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">No contacts found</div>
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
                            
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Communication Flow:</strong> Messages are sent to administrators who will coordinate with staff members as needed. For urgent matters, please mark your message as "High Priority".
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
                                    <div class="text-center text-muted mt-4">No messages yet. Start a conversation!</div>
                                <?php endif; ?>
                            </div>

                            <div class="chat-footer p-3 border-top">
                                <form id="messageForm" class="message-form">
                                    <input type="hidden" name="recipient_id" value="<?php echo $selected_user['user_id']; ?>">
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
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5>Select a contact to start messaging</h5>
                                <p class="text-muted">Click the Chat button next to an administrator to start a conversation</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

.badge {
    font-size: 0.8em;
    padding: 0.35em 0.65em;
}

.badge.bg-danger {
    background-color: #dc3545 !important;
}

.badge.bg-secondary {
    background-color: #6c757d !important;
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
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Update notification banner styles */
#newMessageBanner {
    animation: slideDown 0.5s ease-in-out;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    padding: 15px;
}

#newMessageBanner .btn-close {
    color: white;
    opacity: 0.8;
    transition: opacity 0.2s;
}

#newMessageBanner .btn-close:hover {
    opacity: 1;
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

.contact-item {
    position: relative;
}
</style>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll chat to bottom function
    function scrollChatToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // Scroll to bottom on page load
    scrollChatToBottom();

    const contacts = document.querySelectorAll('.contact-item');
    const searchInput = document.getElementById('searchContacts');

    function filterContacts() {
        const searchText = searchInput.value.toLowerCase();
        let hasVisibleContacts = false;

        contacts.forEach(contact => {
            const name = contact.querySelector('.contact-info h5').textContent.toLowerCase();
            const matchesSearch = name.includes(searchText);

            if (matchesSearch) {
                contact.style.display = 'block';
                hasVisibleContacts = true;
                } else {
                contact.style.display = 'none';
            }
        });

        // Show/hide no results message
        const noResults = document.querySelector('.contacts-scroll');
        if (noResults) {
            noResults.style.display = hasVisibleContacts ? 'block' : 'none';
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

    // Initial filter
    filterContacts();

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
                alert('Failed to send message');
            })
            .finally(() => {
                // Re-enable submit button and restore original text
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }

    // Add clear chat functionality
    const clearChatBtn = document.getElementById('clearChat');
    if (clearChatBtn) {
        clearChatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) {
                const recipientId = document.querySelector('input[name="recipient_id"]').value;
                fetch('clear_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ recipient_id: recipientId })
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
                    alert('Failed to clear chat');
                });
            }
        });
    }

    // Add click event listeners to contacts
    contacts.forEach(contact => {
        contact.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            window.location.href = `messages.php?user=${userId}`;
        });
    });
});

function checkNewMessages() {
    fetch('check_new_messages.php')
        .then(response => response.json())
        .then(data => {
            console.log('New messages data:', data); // Debug log
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
                
                // Update notification dots
                document.querySelectorAll('.notification-dot').forEach(dot => dot.remove());
                data.unreadCounts.forEach(item => {
                    const contactElement = document.querySelector(`[data-contact-id="${item.sender_id}"]`);
                    if (contactElement) {
                        if (!contactElement.querySelector('.notification-dot')) {
                            const dot = document.createElement('div');
                            dot.className = 'notification-dot';
                            contactElement.appendChild(dot);
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error checking messages:', error);
        });
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

// Check for new messages every 10 seconds
setInterval(checkNewMessages, 10000);

// Initial check when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initial message check
    checkNewMessages();
});
    </script>

<?php require_once 'includes/footer.php'; ?> 