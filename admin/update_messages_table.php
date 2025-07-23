<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Add reply_to_id column if it doesn't exist
$check_reply_column = "SELECT column_name 
                      FROM information_schema.columns 
                      WHERE table_name = 'messages' 
                      AND column_name = 'reply_to_id'";
$result = pg_query($db_connection, $check_reply_column);

if (pg_num_rows($result) === 0) {
    $add_reply_column = "ALTER TABLE messages ADD COLUMN reply_to_id INTEGER REFERENCES messages(message_id)";
    if (pg_query($db_connection, $add_reply_column)) {
        echo "Added reply_to_id column successfully<br>";
    } else {
        echo "Error adding reply_to_id column: " . pg_last_error($db_connection) . "<br>";
    }
}

// Add edited column if it doesn't exist
$check_edited_column = "SELECT column_name 
                       FROM information_schema.columns 
                       WHERE table_name = 'messages' 
                       AND column_name = 'edited'";
$result = pg_query($db_connection, $check_edited_column);

if (pg_num_rows($result) === 0) {
    $add_edited_column = "ALTER TABLE messages ADD COLUMN edited BOOLEAN DEFAULT FALSE";
    if (pg_query($db_connection, $add_edited_column)) {
        echo "Added edited column successfully<br>";
    } else {
        echo "Error adding edited column: " . pg_last_error($db_connection) . "<br>";
    }
}

echo "Database update completed. You can now return to the messages page.";
?> 