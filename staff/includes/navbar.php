<?php
// staff/includes/navbar.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get total unread count for navbar badge
$messages_count = 0;
if (isset($_SESSION['user_id'])) {
    $total_unread_query = "SELECT COUNT(*) as unread_count 
                           FROM messages 
                           WHERE recipient_id = $1 
                             AND read = FALSE";
    $total_unread_result = pg_query_params($db_connection, $total_unread_query, array($_SESSION['user_id']));
    if ($total_unread_result) {
        $messages_count = pg_fetch_assoc($total_unread_result)['unread_count'];
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="fas fa-user-md me-2"></i>Staff Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="staffNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                        <i class="fas fa-envelope me-2"></i>Messages
                        <?php if ($messages_count > 0): ?>
                            <span class="badge bg-danger ms-1 rounded-pill"><?php echo $messages_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['staff_name'])): ?>
                    <a class="navbar-text me-3 text-white text-decoration-none" href="profile.php">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['staff_name']); ?>
                    </a>
                <?php endif; ?>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav> 