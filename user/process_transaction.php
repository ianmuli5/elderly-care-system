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
        $amount = trim($_POST['amount']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        $related_resident_id = $_POST['related_resident_id'];
        
        $errors = [];
        
        // Validate required fields
        $required_fields = ['amount', 'category', 'description', 'related_resident_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Please fill in all required fields.";
                break;
            }
        }
        
        // Validate amount is numeric and positive
        if (!empty($amount)) {
            if (!is_numeric($amount)) {
                $errors[] = "Amount must be a valid number.";
            } elseif (floatval($amount) <= 0) {
                $errors[] = "Amount must be greater than zero.";
            }
        }
        
        // Validate description is not just numbers
        if (!empty($description) && is_numeric($description)) {
            $errors[] = "Description cannot be just numbers. Please provide a meaningful description.";
        }
        
        // Validate description length
        if (!empty($description) && strlen($description) < 5) {
            $errors[] = "Description must be at least 5 characters long.";
        }
        
        // Verify the resident belongs to the logged-in user
        if (!empty($related_resident_id)) {
            $resident_query = "SELECT resident_id FROM residents WHERE resident_id = $1 AND family_member_id = $2";
            $resident_result = pg_query_params($db_connection, $resident_query, array($related_resident_id, $_SESSION['user_id']));
            
            if (pg_num_rows($resident_result) === 0) {
                $errors[] = "Invalid resident selected.";
            }
        }
        
        // If no errors, proceed with database insertion
        if (empty($errors)) {
            // Prepare data for insertion
            $data = array(
                $amount,
                $description,
                $_POST['type'],
                $category,
                $related_resident_id,
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
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    }
}

header("Location: transactions.php");
exit; 