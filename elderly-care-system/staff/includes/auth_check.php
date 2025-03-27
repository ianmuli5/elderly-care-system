<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to staff login page
    header("Location: /elderly-care-system/staff/login.php");
    exit;
}

// Set staff name in session if not set
if (!isset($_SESSION['staff_name'])) {
    require_once '../../includes/config.php';
    
    $staff_query = "SELECT CONCAT(first_name, ' ', last_name) as full_name 
                    FROM staff 
                    WHERE staff_id = $1";
    $staff_result = pg_query_params($db_connection, $staff_query, array($_SESSION['staff_id']));
    $staff = pg_fetch_assoc($staff_result);
    
    if ($staff) {
        $_SESSION['staff_name'] = $staff['full_name'];
    } else {
        // Invalid staff ID in session
        session_destroy();
        header("Location: /elderly-care-system/staff/login.php");
        exit;
    }
}
?> 