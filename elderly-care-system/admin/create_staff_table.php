<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Drop existing staff table if it exists
$drop_query = "DROP TABLE IF EXISTS staff CASCADE";
$result = pg_query($db_connection, $drop_query);

if (!$result) {
    die("Error dropping staff table: " . pg_last_error($db_connection));
}

// Add profile_picture column to staff table
$alter_query = "ALTER TABLE staff ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255)";
pg_query($db_connection, $alter_query);

// Create staff table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS staff (
    staff_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(user_id),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL,
    experience TEXT,
    contact_info VARCHAR(100) NOT NULL,
    hiring_date DATE NOT NULL,
    profile_picture VARCHAR(255)
)";

$result = pg_query($db_connection, $query);

if ($result) {
    echo "Staff table created successfully or already exists.";
} else {
    echo "Error creating staff table: " . pg_last_error($db_connection);
}
?> 