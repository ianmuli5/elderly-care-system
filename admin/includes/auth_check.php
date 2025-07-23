<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to main login page
    header("Location: /elderly-care-system/index.php");
    exit;
}

// Set admin name in session if not set
if (!isset($_SESSION['admin_name'])) {
    require_once '../../includes/config.php';
    
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $_SESSION['admin_name'] = $admin['username'];
        } else {
            // Invalid admin ID in session
            session_destroy();
            header("Location: /elderly-care-system/index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in admin auth_check.php: " . $e->getMessage());
        header("Location: /elderly-care-system/admin/error.php");
        exit;
    }
}
?> 