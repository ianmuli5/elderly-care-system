<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle file upload
function handleFileUpload($file, $old_file = null) {
    if (!isset($file['name']) || empty($file['name'])) {
        return $old_file;
    }

    $target_dir = "../uploads/residents/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Check if image file is actual image or fake image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        $_SESSION['error'] = "File is not an image.";
        return false;
    }

    // Check file size (limit to 5MB)
    if ($file['size'] > 5000000) {
        $_SESSION['error'] = "Sorry, your file is too large.";
        return false;
    }

    // Allow certain file formats
    if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif") {
        $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        return false;
    }

    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Delete old file if exists
        if ($old_file && file_exists("../" . $old_file)) {
            unlink("../" . $old_file);
        }
        return "uploads/residents/" . $new_filename;
    } else {
        $_SESSION['error'] = "Sorry, there was an error uploading your file.";
        return false;
    }
}

function validate_allergies($allergies) {
    return preg_match("/^[A-Za-z \-']{2,30}$/", $allergies);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Validate allergies
        if (!empty($_POST['allergies']) && !validate_allergies($_POST['allergies'])) {
            $_SESSION['error'] = "Allergies: only letters, spaces, hyphens, apostrophes allowed (2–30 characters).";
            header("Location: add_resident.php");
            exit;
        }
        // Handle profile picture upload
        $profile_picture = handleFileUpload($_FILES['profile_picture'] ?? []);
        if ($profile_picture === false) {
            header("Location: residents.php");
            exit;
        }

        // Prepare data for insertion
        $data = array(
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['medical_condition'] ?? null,
            $_POST['interests'] ?? null,
            $_POST['status'],
            $_POST['family_member_id'] ?: null,
            $_POST['caregiver_id'] ?: null,
            $profile_picture,
            $_POST['national_id'] ?? null,
            $_POST['passport_number'] ?? null,
            $_POST['place_of_birth'] ?? null,
            $_POST['previous_address'] ?? null,
            $_POST['blood_type'] ?? null,
            $_POST['allergies'] ?? null,
            $_POST['medical_insurance'] ?? null,
            $_POST['primary_doctor'] ?? null,
            $_POST['primary_doctor_other'] ?? null,
            $_POST['religion'] ?? null,
            $_POST['religion_other'] ?? null,
            $_POST['next_of_kin_name'] ?? null,
            $_POST['next_of_kin_contact'] ?? null
        );
        // Insert new resident
        $query = "INSERT INTO residents (first_name, last_name, date_of_birth, medical_condition, interests, status, family_member_id, caregiver_id, profile_picture, national_id, passport_number, place_of_birth, previous_address, blood_type, allergies, medical_insurance, primary_doctor, primary_doctor_other, religion, religion_other, next_of_kin_name, next_of_kin_contact, admission_date)
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21, $22, CURRENT_DATE)
                  RETURNING resident_id";
        
        $result = pg_query_params($db_connection, $query, $data);
        
        if ($result) {
            $_SESSION['success'] = "Resident added successfully.";
        } else {
            $_SESSION['error'] = "Error adding resident: " . pg_last_error($db_connection);
        }
    }
    
    elseif ($action === 'edit') {
        $resident_id = $_POST['resident_id'];
        
        // Validate allergies
        if (!empty($_POST['allergies']) && !validate_allergies($_POST['allergies'])) {
            $_SESSION['error'] = "Allergies: only letters, spaces, hyphens, apostrophes allowed (2–30 characters).";
            header("Location: edit_resident.php?resident_id=" . urlencode($_POST['resident_id']));
            exit;
        }

        // Get current profile picture
        $current_query = "SELECT profile_picture FROM residents WHERE resident_id = $1";
        $current_result = pg_query_params($db_connection, $current_query, array($resident_id));
        $current_resident = pg_fetch_assoc($current_result);
        
        // Handle profile picture upload
        $profile_picture = handleFileUpload($_FILES['profile_picture'] ?? [], $current_resident['profile_picture']);
        if ($profile_picture === false) {
            header("Location: residents.php");
            exit;
        }

        // Prepare data for update
        $data = array(
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['medical_condition'] ?? null,
            $_POST['interests'] ?? null,
            $_POST['status'],
            $_POST['family_member_id'] ?: null,
            $_POST['caregiver_id'] ?: null,
            $profile_picture,
            $_POST['national_id'] ?? null,
            $_POST['passport_number'] ?? null,
            $_POST['place_of_birth'] ?? null,
            $_POST['previous_address'] ?? null,
            $_POST['blood_type'] ?? null,
            $_POST['allergies'] ?? null,
            $_POST['medical_insurance'] ?? null,
            $_POST['primary_doctor'] ?? null,
            $_POST['primary_doctor_other'] ?? null,
            $_POST['religion'] ?? null,
            $_POST['religion_other'] ?? null,
            $_POST['next_of_kin_name'] ?? null,
            $_POST['next_of_kin_contact'] ?? null,
            $resident_id
        );

        // Update resident
        $query = "UPDATE residents 
                  SET first_name = $1, last_name = $2, date_of_birth = $3, 
                      medical_condition = $4, interests = $5, status = $6,
                      family_member_id = $7, caregiver_id = $8, profile_picture = $9,
                      national_id = $10, passport_number = $11, place_of_birth = $12, previous_address = $13,
                      blood_type = $14, allergies = $15, medical_insurance = $16, primary_doctor = $17, primary_doctor_other = $18,
                      religion = $19, religion_other = $20, next_of_kin_name = $21, next_of_kin_contact = $22
                  WHERE resident_id = $23";
        
        $result = pg_query_params($db_connection, $query, $data);
        
        if ($result) {
            $_SESSION['success'] = "Resident updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating resident: " . pg_last_error($db_connection);
        }
    }
}

header("Location: residents.php");
exit; 