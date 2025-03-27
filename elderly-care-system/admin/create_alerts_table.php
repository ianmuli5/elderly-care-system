<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Drop the existing medical_alerts table if it exists
$drop_query = "DROP TABLE IF EXISTS medical_alerts";
$result = pg_query($db_connection, $drop_query);

if (!$result) {
    die("Error dropping table: " . pg_last_error($db_connection));
}

// Create the medical_alerts table with all required columns
$create_query = "CREATE TABLE medical_alerts (
    alert_id SERIAL PRIMARY KEY,
    resident_id INTEGER REFERENCES residents(resident_id),
    staff_id INTEGER REFERENCES staff(staff_id),
    alert_level VARCHAR(10) NOT NULL,
    description TEXT NOT NULL,
    resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$result = pg_query($db_connection, $create_query);

if (!$result) {
    die("Error creating table: " . pg_last_error($db_connection));
}

echo "Medical alerts table has been created successfully!";
?> 