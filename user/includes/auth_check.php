<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is family member
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'family') {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to main login page
    header("Location: /elderly-care-system/index.php");
    exit;
}

// Set user name and resident info in session if not set
if (!isset($_SESSION['user_name']) || !isset($_SESSION['resident_id'])) {
    require_once '../../includes/config.php';
    
    try {
        // Get user info
        $stmt = $pdo->prepare("
            SELECT u.username, r.resident_id, r.first_name, r.last_name 
            FROM users u
            LEFT JOIN residents r ON r.family_member_id = u.user_id
            WHERE u.user_id = ? AND u.role = 'family'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_name'] = $user['username'];
            if ($user['resident_id']) {
                $_SESSION['resident_id'] = $user['resident_id'];
                $_SESSION['resident_name'] = $user['first_name'] . ' ' . $user['last_name'];
            }
        } else {
            // Invalid user ID in session
            session_destroy();
            header("Location: /elderly-care-system/index.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in user auth_check.php: " . $e->getMessage());
        header("Location: /elderly-care-system/user/error.php");
        exit;
    }
}
?> 