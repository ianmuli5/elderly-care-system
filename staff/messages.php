<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php'; // Ensures staff is logged in

// Get staff's user_id
$user_id = $_SESSION['user_id'];

// Get list of available contacts (admins)
$contacts_query = "SELECT u.user_id as contact_id, u.username as contact_name, 'admin' as contact_role, 'Administrator' as title
FROM users u 
WHERE u.role = 'admin'
UNION
SELECT 
    u.user_id as contact_id,
    u.username as contact_name,
    'family' as contact_role,
    'Family of ' || r.first_name || ' ' || r.last_name as title
FROM residents r
JOIN users u ON r.family_member_id = u.user_id
WHERE u.role = 'family'
  AND r.caregiver_id = $1
ORDER BY contact_name";
$contacts_result = pg_query_params($db_connection, $contacts_query, array($_SESSION['staff_id']));

// Get selected conversation
$selected_user = null;
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    // Check if selected user is admin
    $user_query = "SELECT u.user_id, u.username as display_name, u.role, 'Administrator' as title
                FROM users u
                WHERE u.user_id = $1 AND u.role = 'admin'";
    $selected_user_result = pg_query_params($db_connection, $user_query, array($_GET['user']));
    $selected_user = pg_fetch_assoc($selected_user_result);

    // If not admin, check if selected user is a family member of a resident assigned to this staff
    if (!$selected_user) {
        $user_query = "SELECT u.user_id, u.username as display_name, u.role, CONCAT('Family of ', r.first_name, ' ', r.last_name) as title
            FROM users u
            JOIN residents r ON r.family_member_id = u.user_id
            WHERE u.user_id = $1 AND u.role = 'family' AND r.caregiver_id = $2";
        $selected_user_result = pg_query_params($db_connection, $user_query, array($_GET['user'], $_SESSION['staff_id']));
        $selected_user = pg_fetch_assoc($selected_user_result);
    }
}

// Get messages for selected conversation
$messages = array();
if ($selected_user) {
    // Mark messages as read
    $mark_read_query = "UPDATE messages SET read = TRUE WHERE recipient_id = $1 AND sender_id = $2 AND read = FALSE";
    pg_query_params($db_connection, $mark_read_query, array($user_id, $selected_user['user_id']));

    // Get conversation messages
    $messages_query = "SELECT m.*, u.username as sender_name, u.role as sender_role
                  FROM messages m
                  JOIN users u ON m.sender_id = u.user_id
                      WHERE (m.sender_id = $1 AND m.recipient_id = $2) OR (m.sender_id = $2 AND m.recipient_id = $1)
                  ORDER BY m.sent_at ASC";
    $messages_result = pg_query_params($db_connection, $messages_query, array($user_id, $selected_user['user_id']));
    while ($row = pg_fetch_assoc($messages_result)) {
        $messages[] = $row;
    }
}

// Get unread message counts
$unread_query = "SELECT sender_id, COUNT(*) as count FROM messages WHERE recipient_id = $1 AND read = FALSE GROUP BY sender_id";
$unread_result = pg_query_params($db_connection, $unread_query, array($user_id));
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



require_once 'includes/header.php';
?>
    <style>
    body { background-color: #f8f9fc; }
        .messages-container {
            display: flex;
        height: calc(100vh - 200px); /* Adjusted height for navbar and padding */
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        visibility: hidden; /* Hide initially */
    }
    .contact-item { padding: 15px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background-color 0.2s; position: relative; }
    .contact-item:hover { background-color: #e9ecef; }
    .contact-info h5 { margin: 0; font-size: 1rem; }
    .contact-info p { margin: 0; font-size: 0.85rem; }
    .chat-area { flex: 1; display: flex; flex-direction: column; height: 100%;}
    .chat-header { padding: 15px; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa;}
    .messages { flex: 1; overflow-y: auto; padding: 20px; background-color: #f8f9fc; display: flex; flex-direction: column; }
    .message {
        margin: 10px 0;
        padding: 8px 12px;
        border-radius: 10px;
        max-width: 70%;
        position: relative;
        display: flex;
        flex-direction: column;
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
    .message-content { white-space: pre-wrap; color: white; }
    .chat-footer { padding: 15px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
    .notification-dot { position: absolute; top: 5px; right: 5px; width: 10px; height: 10px; background-color: #ff4444; border-radius: 50%; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.2); opacity: 0.8; } 100% { transform: scale(1); opacity: 1; } }
    </style>

    <div class="messages-container">
    <div class="contacts-list" id="contactsList">
            <div class="p-3 border-bottom">
            <input type="text" class="form-control" id="searchContacts" placeholder="Search...">
            </div>
            <?php if ($contacts_result && pg_num_rows($contacts_result) > 0): ?>
            <?php while ($contact = pg_fetch_assoc($contacts_result)): ?>
                <div class="contact-item" data-contact-id="<?php echo htmlspecialchars($contact['contact_id']); ?>">
                        <div class="contact-info">
                            <h5><?php echo htmlspecialchars($contact['contact_name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($contact['title']); ?></p>
                        </div>
                    <?php if (isset($unread_counts[$contact['contact_id']]) && $unread_counts[$contact['contact_id']] > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $unread_counts[$contact['contact_id']]; ?></span>
                        <?php endif; ?>
                    <a href="messages.php?user=<?php echo htmlspecialchars($contact['contact_id']); ?>" class="btn btn-primary btn-sm start-chat">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="p-3 text-center text-muted">No contacts found</div>
            <?php endif; ?>
        <div id="noResults" class="p-3 text-center text-muted" style="display: none;">No contacts found</div>
        </div>

        <div class="chat-area">
            <?php if ($selected_user): ?>
            <div class="chat-header">
                <h5 class="mb-0"><?php echo htmlspecialchars($selected_user['display_name']); ?></h5>
                </div>
                <div class="messages" id="chatMessages">
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                        <div class="message-header">
                            <div>
                            <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                            </div>
                            <span style="font-size: 0.9em; white-space: nowrap;">
                                <?php echo date('M d, Y H:i', strtotime($message['sent_at'])); ?>
                            </span>
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
                </div>
            <div class="chat-footer">
                    <form id="messageForm">
                        <input type="hidden" name="recipient_id" value="<?php echo $selected_user['user_id']; ?>">
                                <div class="input-group">
                        <textarea class="form-control" name="content" placeholder="Type your message..." rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                <div class="text-center text-muted"><i class="fas fa-comments fa-3x mb-3"></i><h5>Select a contact</h5></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const contactsList = document.getElementById('contactsList');
    const searchInput = document.getElementById('searchContacts');
    const contacts = document.querySelectorAll('.contact-item');
    const chatLinks = document.querySelectorAll('.start-chat');

    if (sessionStorage.getItem('contactsSearchStaff')) {
        searchInput.value = sessionStorage.getItem('contactsSearchStaff');
    }

    function filterContacts() {
        const searchText = searchInput.value.toLowerCase();
        let hasVisibleContacts = false;
        contacts.forEach(contact => {
            const name = contact.querySelector('.contact-info h5').textContent.toLowerCase();
            if (name.includes(searchText)) {
                contact.style.display = 'flex';
                hasVisibleContacts = true;
            } else {
                contact.style.display = 'none';
            }
        });
        document.getElementById('noResults').style.display = hasVisibleContacts ? 'none' : 'block';
                }

    searchInput.addEventListener('input', filterContacts);
    filterContacts();

    if (sessionStorage.getItem('contactsScrollTopStaff')) {
        contactsList.scrollTop = parseInt(sessionStorage.getItem('contactsScrollTopStaff'), 10);
    }
    contactsList.style.visibility = 'visible';

    chatLinks.forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem('contactsScrollTopStaff', contactsList.scrollTop);
            sessionStorage.setItem('contactsSearchStaff', searchInput.value);
        });
    });

    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
        fetch('process_message.php', { method: 'POST', body: formData })
        .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                alert(data.message || 'Error sending message.');
                }
        }).catch(error => console.error('Error:', error))
            .finally(() => {
                submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        });
    });

    // Poll for new messages every 5 seconds
    function checkNewMessages() {
        fetch('check_new_messages.php')
            .then(response => response.json())
            .then(data => {
                if (data.hasNewMessages) {
                    // Show a banner or popup
                    let messageText = 'New messages from: ';
                    let senderNames = data.unreadCounts.map(item => item.sender_name);
                    messageText += senderNames.join(', ');
                    let banner = document.getElementById('newMessageBanner');
                    if (!banner) {
                        banner = document.createElement('div');
                        banner.id = 'newMessageBanner';
                        banner.className = 'alert alert-success alert-dismissible fade show';
                        banner.style.position = 'fixed';
                        banner.style.top = '0';
                        banner.style.left = '0';
                        banner.style.right = '0';
                        banner.style.zIndex = '1050';
                        banner.style.margin = '0';
                        banner.style.borderRadius = '0';
                        banner.style.textAlign = 'center';
                        banner.style.backgroundColor = '#28a745';
                        banner.style.color = 'white';
                        banner.innerHTML = '<i class="fas fa-envelope me-2"></i>' +
                            '<span id="newMessageText">' + messageText + '</span>' +
                            '<button type="button" class="btn-close" onclick="this.parentNode.style.display=\'none\'" aria-label="Close"></button>';
                        document.body.appendChild(banner);
                    } else {
                        document.getElementById('newMessageText').textContent = messageText;
                        banner.style.display = 'block';
                    }
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
    setInterval(checkNewMessages, 5000);
    checkNewMessages();
});
    </script>
<?php require_once 'includes/footer.php'; ?>