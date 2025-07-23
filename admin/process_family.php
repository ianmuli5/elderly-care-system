<?php
require_once '../includes/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Basic validation
    if (!$username || !$email || !$password || !$confirm_password) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: residents.php');
        exit;
    }
    if (!preg_match('/^[A-Za-z ]+$/', $username)) {
        $_SESSION['error'] = 'Username can only contain letters and spaces.';
        header('Location: residents.php');
        exit;
    }
    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: residents.php');
        exit;
    }
    if (!preg_match('/^\d{10,20}$/', $phone_number)) {
        $_SESSION['error'] = 'Phone number must be 10–20 digits.';
        header('Location: residents.php');
        exit;
    }

    // Check for unique username/email
    $check_query = "SELECT 1 FROM users WHERE username = $1 OR email = $2";
    $check_result = pg_query_params($db_connection, $check_query, [$username, $email]);
    if (pg_num_rows($check_result) > 0) {
        $_SESSION['error'] = 'Username or email already exists.';
        header('Location: residents.php');
        exit;
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new family member
    $insert_query = "INSERT INTO users (username, password_hash, email, phone_number, role) VALUES ($1, $2, $3, $4, 'family') RETURNING user_id";
    $result = pg_query_params($db_connection, $insert_query, [$username, $password_hash, $email, $phone_number]);
    if ($result && pg_fetch_result($result, 0, 0)) {
        $_SESSION['success'] = 'Family member registered successfully.';
    } else {
        $_SESSION['error'] = 'Error registering family member.';
    }
    header('Location: residents.php');
    exit;
} else {
    header('Location: residents.php');
    exit;
} 