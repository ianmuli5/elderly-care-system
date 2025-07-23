<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elderly Care System - User Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background-color: #1cc88a;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: white !important;
        }
        .nav-link.active {
            color: white !important;
            font-weight: bold;
        }
        .user-profile {
            color: white;
        }
        .user-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 8px;
        }
        body {
            padding-top: 60px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home me-2"></i>Elderly Care System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" 
                           href="messages.php">
                            Messages
                            <?php if (hasNewMessages($db_connection, $_SESSION['user_id'])): ?>
                                <span class="badge bg-danger rounded-pill ms-1">New</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                            <i class="fas fa-money-bill me-1"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" href="events.php">
                            <i class="fas fa-calendar me-1"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>" 
                           href="feedback.php">
                            Feedback
                            <?php if (hasNewFeedbackResponses($db_connection, $_SESSION['user_id'])): ?>
                                <span class="badge bg-danger rounded-pill ms-1">New</span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                <div class="user-profile">
                    <a href="profile.php" class="text-white text-decoration-none">
                        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <a href="../logout.php" class="text-white text-decoration-none ms-3">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">
        <div class="row">
            <main class="col-12">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo ucfirst(str_replace('.php', '', basename($_SERVER['PHP_SELF']))); ?></h1>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Add audio element for notifications -->
    <audio id="notificationSound" preload="auto">
        <source src="../assets/sounds/notification.mp3" type="audio/mpeg">
    </audio>
    
    <script>
        // Function to play notification sound
        function playNotificationSound() {
            const audio = document.getElementById('notificationSound');
            // Reset the audio to start
            audio.currentTime = 0;
            
            // Try to play the sound
            const playPromise = audio.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('Sound played successfully');
                }).catch(error => {
                    console.log('Error playing sound:', error);
                    // If autoplay was blocked, show a notification
                    if (error.name === 'NotAllowedError') {
                        alert('Please allow audio notifications for this site to hear new message alerts.');
                    }
                });
            }
        }

        // Track last message IDs to prevent repeated notifications
        let lastMessageIds = new Set();
        let lastResponseCount = 0;
        
        // Function to check for new messages
        function checkNewMessages() {
            $.ajax({
                url: 'check_new_messages.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    console.log('Checking messages:', data);
                    if (data.hasNewMessages && data.unreadCounts.length > 0) {
                        // Get current message IDs from the response
                        const currentMessageIds = new Set(data.unreadCounts.map(item => item.message_id));
                        
                        // Find truly new messages (ones we haven't seen before)
                        const newMessages = Array.from(currentMessageIds).filter(id => !lastMessageIds.has(id));
                        
                        if (newMessages.length > 0) {
                            console.log('New messages detected:', newMessages);
                            playNotificationSound();
                            // Update our set of seen message IDs
                            lastMessageIds = currentMessageIds;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error checking messages:', error);
                }
            });
            
            $.ajax({
                url: 'check_new_feedback.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    console.log('Checking feedback:', data);
                    if (data.hasNewResponses && data.count > lastResponseCount) {
                        console.log('New feedback response detected!');
                        playNotificationSound();
                        lastResponseCount = data.count;
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error checking feedback:', error);
                }
            });
        }

        // Check immediately when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initial check for messages
            checkNewMessages();
        });
        
        // Then check every 5 seconds
        setInterval(checkNewMessages, 5000);
    </script>
</body>
</html> 