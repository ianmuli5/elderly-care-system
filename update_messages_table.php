<?php
require_once 'includes/config.php';

// Add priority and category columns to messages table
$alter_query = "ALTER TABLE messages 
                ADD COLUMN IF NOT EXISTS priority VARCHAR(10) DEFAULT 'normal',
                ADD COLUMN IF NOT EXISTS category VARCHAR(20) DEFAULT 'general',
                ADD CONSTRAINT valid_priority CHECK (priority IN ('normal', 'high', 'urgent')),
                ADD CONSTRAINT valid_category CHECK (category IN ('general', 'medical', 'payment', 'visitation', 'other'))";

$result = pg_query($db_connection, $alter_query);

if ($result) {
    echo "Messages table updated successfully";
} else {
    echo "Error updating messages table: " . pg_last_error($db_connection);
} 