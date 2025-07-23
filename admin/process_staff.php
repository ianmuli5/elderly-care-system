<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Function to handle file upload
function handleFileUpload($file) {
    global $db_connection;
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $target_dir = "../uploads/staff/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        return false;
    }

    if ($file['size'] > 5000000) { // 5MB limit
        $_SESSION['error'] = "Sorry, your file is too large. Maximum size is 5MB.";
        return false;
    }

    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return 'uploads/staff/' . $new_filename;
    } else {
        $_SESSION['error'] = "Sorry, there was an error uploading your file.";
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'position', 'contact_info', 'email', 'username', 'password'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: staff.php");
        exit;
    }
    
    // Start transaction
    pg_query($db_connection, "BEGIN");
    
    try {
        // First create user account
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $user_query = "INSERT INTO users (username, password_hash, email, role) VALUES ($1, $2, $3, 'staff') RETURNING user_id";
        $user_result = pg_query_params($db_connection, $user_query, array($_POST['username'], $password, $_POST['email']));
        
        if (!$user_result) {
            throw new Exception("Error creating user account: " . pg_last_error($db_connection));
        }
        
        $user_row = pg_fetch_assoc($user_result);
        $user_id = $user_row['user_id'];
        
        // Handle profile picture upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $profile_picture = handleFileUpload($_FILES['profile_picture']);
            if ($profile_picture === false) {
                throw new Exception("Error uploading profile picture");
            }
        }
        
        // Then create staff record
        $staff_query = "INSERT INTO staff (user_id, first_name, last_name, position, contact_info, hiring_date, profile_picture, active, national_id, date_of_employment, work_permit_number) 
                       VALUES ($1, $2, $3, $4, $5, CURRENT_DATE, $6, TRUE, $7, $8, $9)";
        $staff_result = pg_query_params($db_connection, $staff_query, array(
            $user_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['position'],
            $_POST['contact_info'],
            $profile_picture,
            $_POST['national_id'],
            $_POST['date_of_employment'],
            $_POST['work_permit_number'] ?? null
        ));
        
        if (!$staff_result) {
            throw new Exception("Error creating staff record: " . pg_last_error($db_connection));
        }
        
        // Commit transaction
        pg_query($db_connection, "COMMIT");
        $_SESSION['success'] = "Staff member added successfully. They can log in using their username and password.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        pg_query($db_connection, "ROLLBACK");
        $_SESSION['error'] = "Error adding staff member: " . $e->getMessage();
        
        // Delete uploaded file if it exists
        if (isset($profile_picture) && file_exists('../' . $profile_picture)) {
            unlink('../' . $profile_picture);
        }
    }
    
    header("Location: staff.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log the received data
    error_log("Received POST data: " . print_r($_POST, true));
    error_log("Received FILES data: " . print_r($_FILES, true));
    
    if ($action === 'edit') {
        // Validate date_of_employment is not in the future
        if (!empty($_POST['date_of_employment']) && $_POST['date_of_employment'] > date('Y-m-d')) {
            $_SESSION['error'] = 'Date of employment cannot be in the future.';
            header('Location: edit_staff.php?staff_id=' . urlencode($_POST['staff_id']));
            exit;
        }
        // Start transaction
        pg_query($db_connection, "BEGIN");
        
        try {
            // Get current staff status
            $current_query = "SELECT staff_id, first_name, last_name, active::text as active_text FROM staff WHERE staff_id = $1";
            $current_result = pg_query_params($db_connection, $current_query, array($_POST['staff_id']));
            $current_staff = pg_fetch_assoc($current_result);
            
            // Handle profile picture upload
            $profile_picture = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                // Check if profile_picture column exists
                $check_column_query = "SELECT column_name 
                                     FROM information_schema.columns 
                                     WHERE table_name = 'staff' 
                                     AND column_name = 'profile_picture'";
                $check_result = pg_query($db_connection, $check_column_query);
                
                if (pg_num_rows($check_result) > 0) {
                    // Get the old profile picture to delete it later
                    $old_picture_query = "SELECT profile_picture FROM staff WHERE staff_id = $1";
                    $old_picture_result = pg_query_params($db_connection, $old_picture_query, array($_POST['staff_id']));
                    if ($old_picture_result) {
                        $old_picture = pg_fetch_assoc($old_picture_result);
                        
                        // Delete old profile picture if it exists
                        if (!empty($old_picture['profile_picture']) && file_exists('../' . $old_picture['profile_picture'])) {
                            unlink('../' . $old_picture['profile_picture']);
                        }
                    }
                }
                
                $profile_picture = handleFileUpload($_FILES['profile_picture']);
                if ($profile_picture === false) {
                    throw new Exception("Error uploading profile picture");
                }
            }

            // Update staff member
            $data = array(
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['position'],
                $_POST['contact_info'],
                $_POST['national_id'],
                $_POST['date_of_employment'],
                $_POST['work_permit_number'] ?? null
            );

            $new_status = $_POST['active'] === '1' ? "'true'" : "'false'";
            $query = "UPDATE staff SET 
                     first_name = $1, 
                     last_name = $2, 
                     position = $3, 
                     contact_info = $4,
                     national_id = $5,
                     date_of_employment = $6,
                     work_permit_number = $7,
                     active = " . $new_status . "
                     WHERE staff_id = $" . (count($data) + 1);
            
            $data[] = $_POST['staff_id'];

            // Add profile picture to update if it was uploaded and column exists
            if ($profile_picture !== null && pg_num_rows($check_result) > 0) {
                $query = str_replace("WHERE", ", profile_picture = $" . (count($data) + 1) . " WHERE", $query);
                $data[] = $profile_picture;
            }
            
            if (!pg_query_params($db_connection, $query, $data)) {
                throw new Exception("Error updating staff record: " . pg_last_error($db_connection));
            }

            // Update email and password in users table if provided
            if (!empty($_POST['email']) || !empty($_POST['password'])) {
                $user_update_query = "UPDATE users u SET ";
                $user_update_params = array();
                $set_clauses = array();
                $param_index = 1;
            if (!empty($_POST['email'])) {
                    $set_clauses[] = "email = $" . $param_index;
                    $user_update_params[] = $_POST['email'];
                    $param_index++;
                }
                if (!empty($_POST['password'])) {
                    if (strlen($_POST['password']) < 6) {
                        throw new Exception("Password must be at least 6 characters long.");
                    }
                    $set_clauses[] = "password_hash = $" . $param_index;
                    $user_update_params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $param_index++;
                }
                $user_update_query .= implode(", ", $set_clauses);
                $user_update_query .= " FROM staff s WHERE s.user_id = u.user_id AND s.staff_id = $" . $param_index;
                $user_update_params[] = $_POST['staff_id'];
                if (!pg_query_params($db_connection, $user_update_query, $user_update_params)) {
                    throw new Exception("Error updating user credentials: " . pg_last_error($db_connection));
                }
            }
            
            // Commit transaction
            pg_query($db_connection, "COMMIT");
            $_SESSION['success'] = "Staff member updated successfully.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            pg_query($db_connection, "ROLLBACK");
            $_SESSION['error'] = "Error updating staff member: " . $e->getMessage();
            
            // Delete uploaded file if there was an error
            if (isset($profile_picture) && file_exists('../' . $profile_picture)) {
                unlink('../' . $profile_picture);
            }
        }
    }
}

header("Location: staff.php");
exit; 