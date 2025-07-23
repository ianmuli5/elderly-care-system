<?php
require_once '../includes/config.php';

// Add completion tracking columns to events table
$alter_query = "ALTER TABLE events 
                ADD COLUMN IF NOT EXISTS completed BOOLEAN DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS completion_notes TEXT,
                ADD COLUMN IF NOT EXISTS completed_by INTEGER REFERENCES staff(staff_id),
                ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP";

$result = pg_query($db_connection, $alter_query);

if ($result) {
    echo "Events table updated successfully";
} else {
    echo "Error updating events table: " . pg_last_error($db_connection);
}
?> 