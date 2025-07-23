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
UNION
SELECT 
    u.user_id as contact_id,
    s.first_name || ' ' || s.last_name as contact_name,
    'staff' as contact_role,
    s.position as title
FROM staff s
JOIN users u ON s.user_id = u.user_id
WHERE u.role = 'staff'
  AND s.staff_id IN (
      SELECT caregiver_id
      FROM residents
      WHERE family_member_id = $1
  )
ORDER BY contact_name";

if (!($contacts_result = pg_query_params($db_connection, $contacts_query, array($_SESSION['user_id'])))) {
    error_log("Contacts query failed: " . pg_last_error($db_connection));
    $contacts_result = false;
}

// Get selected conversation
$selected_user = null;
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    $user_query = "WITH user_details AS (
        SELECT 
            u.user_id as user_id,
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
           OR (u.role = 'staff' AND u.user_id = $1 
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

// Add helper functions for badge classes
function getPriorityBadgeClass($priority) {
    switch (strtolower($priority)) {
        case 'urgent': return 'danger';
        case 'high': return 'warning';
        default: return 'secondary';
    }
}
function getCategoryBadgeClass($category) {
    switch (strtolower($category)) {
        case 'medical': return 'danger';
        case 'payment': return 'success';
        case 'visitation': return 'info';
        case 'other': return 'dark';
        default: return 'primary';
    }
}

// Include the header
require_once 'includes/header.php';
?>

<style>
.messages-container {
    display: flex;
        height: calc(100vh - 120px);
    background: white;
    border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
        margin: 0 auto;
    max-width: 1200px;
}

.contacts-list {
    width: 300px;
    border-right: 1px solid #dee2e6;
    background-color: #f8f9fa;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow-y: auto;
        visibility: hidden; /* Hide initially to prevent flicker */
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

.contact-info h5 {
    margin: 0;
    font-size: 1rem;
}

.contact-info p {
    margin: 0;
    font-size: 0.85rem;
}

.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
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
        padding: 8px 12px;
    border-radius: 10px;
        max-width: 70%;
    position: relative;
    display: flex;
    flex-direction: column;
        width: -moz-fit-content;
        width: fit-content;
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
        margin-bottom: 4px;
    font-size: 0.85em;
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

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div id="newMessageBanner" class="alert alert-success alert-dismissible fade show" style="display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; margin: 0; border-radius: 0; text-align: center; background-color: #28a745; color: white;">
                    <div class="container">
                        <i class="fas fa-envelope me-2"></i>
                        <span id="newMessageText"></span>
                        <button type="button" class="btn-close" onclick="dismissNotification()" aria-label="Close"></button>
                    </div>
                </div>

                <div class="messages-container">
                    <!-- Contacts List -->
                    <div class="contacts-list" id="contactsList">
                        <div class="p-3 border-bottom">
                            <input type="text" class="form-control" id="searchContacts" placeholder="Search...">
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
                                        <span class="badge bg-danger rounded-pill"><?php echo $unread_counts[$contact['contact_id']]; ?></span>
                                    <?php endif; ?>
                                    <a href="messages.php?user=<?php echo htmlspecialchars($contact['contact_id']); ?>" 
                                       class="btn btn-primary btn-sm start-chat">
                                        <i class="fas fa-comments"></i> Chat
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">No contacts found</div>
                        <?php endif; ?>
                        <div id="noResults" class="p-3 text-center text-muted" style="display: none;">No contacts found</div>
                    </div>

                    <!-- Chat Area -->
                    <div class="chat-area">
                        <?php if ($selected_user): ?>
                            <div class="chat-header p-3 border-bottom">
                                <h5 class="mb-0"><?php echo htmlspecialchars($selected_user['display_name']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($selected_user['title']); ?></small>
                            </div>
                            
                            <div class="alert alert-info mb-0 rounded-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Messages are sent to administrators who will coordinate with staff.
                            </div>

                            <div class="messages" id="chatMessages">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received'; ?>"
                                             id="message-<?php echo $message['message_id']; ?>">
                                            <div class="message-header">
                                                <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                                                <small>
                                                    <?php echo date('M d, Y H:i', strtotime($message['sent_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="content">
                                                <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                            </div>
                                            <div class="d-inline-flex align-items-center gap-2 mt-2">
                                                <?php if (!empty($message['priority'])): ?>
                                                    <span class="badge bg-<?php echo getPriorityBadgeClass($message['priority']); ?>">
                                                        <?php echo ucfirst($message['priority']); ?> Priority
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($message['category'])): ?>
                                                    <span class="badge bg-<?php echo getCategoryBadgeClass($message['category']); ?>">
                                                        <?php echo ucfirst($message['category']); ?> Inquiry
                                                    </span>
                                                <?php endif; ?>
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
                                <form id="messageForm">
                                    <input type="hidden" name="recipient_id" value="<?php echo $selected_user['user_id']; ?>">
                                    <div class="message-options mb-2">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <select class="form-select" name="priority">
                                                    <option value="normal">Normal Priority</option>
                                                    <option value="high">High Priority</option>
                                                    <option value="urgent">Urgent</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" name="category">
                                                    <option value="general">General Inquiry</option>
                                                    <option value="medical">Medical Update</option>
                                                    <option value="payment">Payment Question</option>
                                                    <option value="visitation">Visitation Request</option>
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
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const contactsList = document.getElementById('contactsList');
    const searchInput = document.getElementById('searchContacts');
    const contacts = document.querySelectorAll('.contact-item');
    const chatLinks = document.querySelectorAll('.start-chat');

    // Restore state on page load
    if (sessionStorage.getItem('contactsSearch')) {
        searchInput.value = sessionStorage.getItem('contactsSearch');
    }

    function filterContacts() {
        const searchText = searchInput.value.toLowerCase();
        let hasVisibleContacts = false;

        contacts.forEach(contact => {
            const name = contact.querySelector('.contact-info h5').textContent.toLowerCase();
            const matchesSearch = name.includes(searchText);

            if (matchesSearch) {
                contact.style.display = 'flex'; // Use flex to match original display
                hasVisibleContacts = true;
                } else {
                contact.style.display = 'none';
            }
        });

        const noResults = document.getElementById('noResults');
        if (noResults) {
            noResults.style.display = hasVisibleContacts ? 'none' : 'block';
        }
    }

    // Add event listeners for filtering
    if (searchInput) {
        searchInput.addEventListener('input', filterContacts);
    }

    // Initial filter to apply saved filters
                filterContacts();

    // Restore scroll position and make the list visible
    if (sessionStorage.getItem('contactsScrollTop')) {
        contactsList.scrollTop = parseInt(sessionStorage.getItem('contactsScrollTop'), 10);
    }
    contactsList.style.visibility = 'visible';

    // Save state when a user clicks a chat link
    chatLinks.forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem('contactsScrollTop', contactsList.scrollTop);
            sessionStorage.setItem('contactsSearch', searchInput.value);
        });
    });

    function scrollChatToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    scrollChatToBottom();

    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }
});

function sendMessage() {
    const form = document.getElementById('messageForm');
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');

            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('process_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
            alert(data.message || 'Error sending message.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
        alert('An error occurred. Please try again.');
            })
            .finally(() => {
                submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
    });
}

function clearChat(recipientId) {
    if (confirm('Are you sure you want to clear this entire chat history? This cannot be undone.')) {
                fetch('clear_chat.php', {
                    method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'recipient_id=' + recipientId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                window.location.href = 'messages.php';
                    } else {
                alert(data.message || 'Failed to clear chat.');
            }
        })
        .catch(error => console.error('Error:', error));
}
}
    </script>

<script>
// --- New Message Polling and Alert Logic ---
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
            } else {
                // Hide banner if no new messages
                document.getElementById('newMessageBanner').style.display = 'none';
                // Remove notification dots
                document.querySelectorAll('.notification-dot').forEach(dot => dot.remove());
            }
        })
        .catch(error => console.error('Error checking messages:', error));
}

// Function to dismiss notification
function dismissNotification() {
    document.getElementById('newMessageBanner').style.display = 'none';
}

// Check for new messages every 5 seconds
setInterval(checkNewMessages, 5000);
// Initial check when page loads
window.addEventListener('DOMContentLoaded', checkNewMessages);
    </script>

<?php require_once 'includes/footer.php'; ?> 