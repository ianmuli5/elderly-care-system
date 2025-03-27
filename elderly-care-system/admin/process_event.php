<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Validate required fields
        $required_fields = ['title', 'description', 'event_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Please fill in all required fields.";
                header("Location: events.php");
                exit;
            }
        }

        // Prepare data for insertion
        $data = array(
            $_POST['title'],
            $_POST['description'],
            $_POST['event_date'],
            $_POST['location'] ?? null,
            $_SESSION['user_id']
        );

        // Insert new event
        $query = "INSERT INTO events (title, description, event_date, location, created_by) 
                 VALUES ($1, $2, $3, $4, $5)";
        
        if (pg_query_params($db_connection, $query, $data)) {
            $_SESSION['success'] = "Event added successfully.";
        } else {
            $_SESSION['error'] = "Error adding event: " . pg_last_error($db_connection);
        }
    } 
    elseif ($action === 'edit') {
        // Validate required fields
        $required_fields = ['event_id', 'title', 'description', 'event_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Please fill in all required fields.";
                header("Location: events.php");
                exit;
            }
        }

        // Prepare data for update
        $data = array(
            $_POST['title'],
            $_POST['description'],
            $_POST['event_date'],
            $_POST['location'] ?? null,
            $_POST['event_id']
        );

        // Update event
        $query = "UPDATE events SET 
                 title = $1, 
                 description = $2, 
                 event_date = $3, 
                 location = $4 
                 WHERE event_id = $5";
        
        if (pg_query_params($db_connection, $query, $data)) {
            $_SESSION['success'] = "Event updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating event: " . pg_last_error($db_connection);
        }
    }
}

header("Location: events.php");
exit; 