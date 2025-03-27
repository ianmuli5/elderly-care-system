<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Validate required fields
        $required_fields = ['amount', 'category', 'description', 'related_resident_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Please fill in all required fields.";
                header("Location: transactions.php");
                exit;
            }
        }

        // Verify the resident belongs to the logged-in user
        $resident_query = "SELECT resident_id FROM residents WHERE resident_id = $1 AND family_member_id = $2";
        $resident_result = pg_query_params($db_connection, $resident_query, array($_POST['related_resident_id'], $_SESSION['user_id']));
        
        if (pg_num_rows($resident_result) === 0) {
            $_SESSION['error'] = "Invalid resident selected.";
            header("Location: transactions.php");
            exit;
        }

        // Prepare data for insertion
        $data = array(
            $_POST['amount'],
            $_POST['description'],
            $_POST['type'],
            $_POST['category'],
            $_POST['related_resident_id'],
            $_SESSION['user_id']
        );

        // Insert new transaction
        $query = "INSERT INTO transactions (amount, description, type, category, related_resident_id, created_by) 
                 VALUES ($1, $2, $3, $4, $5, $6)";
        
        if (pg_query_params($db_connection, $query, $data)) {
            $_SESSION['success'] = "Payment submitted successfully.";
        } else {
            $_SESSION['error'] = "Error submitting payment: " . pg_last_error($db_connection);
        }
    }
}

header("Location: transactions.php");
exit; 