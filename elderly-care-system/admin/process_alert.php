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
        $required_fields = ['resident_id', 'alert_level', 'description', 'priority_level', 'category'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Please fill in all required fields.";
                header("Location: alerts.php");
                exit;
            }
        }

        // Prepare data for insertion
        $data = array(
            $_POST['resident_id'],
            $_POST['alert_level'],
            $_POST['description'],
            'false', // resolved status as string 'false'
            !empty($_POST['staff_id']) ? $_POST['staff_id'] : null,
            $_POST['priority_level'],
            $_POST['category'],
            $_POST['location'] ?? null,
            $_POST['response_required_by'] ?? null,
            'pending' // initial status
        );

        // Insert new alert with all fields
        $query = "INSERT INTO medical_alerts (
                    resident_id, alert_level, description, resolved, staff_id,
                    priority_level, category, location, response_required_by, status
                ) VALUES ($1, $2, $3, $4::boolean, $5, $6, $7, $8, $9, $10)";
        
        if (pg_query_params($db_connection, $query, $data)) {
            $_SESSION['success'] = "Alert added successfully.";
        } else {
            $_SESSION['error'] = "Error adding alert: " . pg_last_error($db_connection);
        }
    } 
    elseif ($action === 'edit') {
        // Validate required fields
        $required_fields = ['alert_id', 'resident_id', 'alert_level', 'description', 'priority_level', 'category'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Please fill in all required fields.";
                header("Location: alerts.php");
                exit;
            }
        }

        // Prepare data for update
        $data = array(
            $_POST['resident_id'],
            $_POST['alert_level'],
            $_POST['description'],
            $_POST['status'] === 'resolved' ? 'true' : 'false',
            !empty($_POST['staff_id']) ? $_POST['staff_id'] : null,
            $_POST['priority_level'],
            $_POST['category'],
            $_POST['location'] ?? null,
            $_POST['response_required_by'] ?? null,
            $_POST['status'],
            $_POST['alert_id']
        );

        // Update alert with all fields
        $query = "UPDATE medical_alerts SET 
                 resident_id = $1, 
                 alert_level = $2, 
                 description = $3, 
                 resolved = $4::boolean,
                 staff_id = $5,
                 priority_level = $6,
                 category = $7,
                 location = $8,
                 response_required_by = $9,
                 status = $10,
                 last_updated = CURRENT_TIMESTAMP";

        // Add resolution information if status is resolved
        if ($_POST['status'] === 'resolved') {
            $data[] = $_SESSION['user_id']; // resolved_by (assuming user_id is the staff_id)
            $data[] = $_POST['resolution_notes'] ?? null;
            $query .= ", resolved_by = $11, resolution_notes = $12, resolved_at = CURRENT_TIMESTAMP";
        }

        $query .= " WHERE alert_id = " . ($_POST['status'] === 'resolved' ? "$13" : "$11");
        
        if (pg_query_params($db_connection, $query, $data)) {
            $_SESSION['success'] = "Alert updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating alert: " . pg_last_error($db_connection);
        }
    }
}

header("Location: alerts.php");
exit; 