<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Add staff_id column to medical_alerts table
$alter_query = "ALTER TABLE medical_alerts ADD COLUMN IF NOT EXISTS staff_id INTEGER REFERENCES staff(staff_id)";
$result = pg_query($db_connection, $alter_query);

if (!$result) {
    die("Error adding staff_id column: " . pg_last_error($db_connection));
}

// Update the alerts query to include staff information
$update_query = "UPDATE medical_alerts SET staff_id = NULL WHERE staff_id IS NOT NULL";
$result = pg_query($db_connection, $update_query);

if (!$result) {
    die("Error updating alerts: " . pg_last_error($db_connection));
}

// Add user_id column to staff table
$query = "ALTER TABLE staff ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(user_id)";
$result = pg_query($db_connection, $query);

if ($result) {
    echo "Successfully added user_id column to staff table.";
} else {
    echo "Error adding user_id column: " . pg_last_error($db_connection);
}

echo "Staff ID column has been added to medical_alerts table successfully!";
?> 