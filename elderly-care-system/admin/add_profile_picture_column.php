<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Add profile_picture column to staff table if it doesn't exist
$check_column_query = "SELECT column_name 
                      FROM information_schema.columns 
                      WHERE table_name = 'staff' 
                      AND column_name = 'profile_picture'";
$check_result = pg_query($db_connection, $check_column_query);

if (pg_num_rows($check_result) == 0) {
    $alter_query = "ALTER TABLE staff ADD COLUMN profile_picture VARCHAR(255)";
    $result = pg_query($db_connection, $alter_query);
    
    if ($result) {
        echo "Profile picture column added successfully.";
    } else {
        echo "Error adding profile picture column: " . pg_last_error($db_connection);
    }
} else {
    echo "Profile picture column already exists.";
}
?> 