<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: /elderly-care-system/index.php");
    exit;
}

// Set user role if not set
if (!isset($_SESSION['user_role'])) {
    require_once 'config.php';
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_role'] = $user['role'];
        } else {
            // Invalid user ID in session
            session_destroy();
            header("Location: /elderly-care-system/index.php");
            exit;
        }
    } catch (PDOException $e) {
        // Log error and redirect to error page
        error_log("Database error in auth_check.php: " . $e->getMessage());
        header("Location: /elderly-care-system/error.php");
        exit;
    }
}

// Set user's name in session if not set
if (!isset($_SESSION['staff_name']) && $_SESSION['user_role'] === 'staff') {
    require_once 'config.php';
    
    try {
        $stmt = $pdo->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as full_name 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['staff_name'] = $user['full_name'];
        }
    } catch (PDOException $e) {
        error_log("Database error getting staff name in auth_check.php: " . $e->getMessage());
    }
}

// Function to check if user has required role
function checkUserRole($required_role) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
        header("Location: /elderly-care-system/unauthorized.php");
        exit;
    }
}
?> 