<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if a user has a specific role
 * @param string $role The role to check for
 * @return bool True if user has the role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require a specific role to access a page
 * @param string $role The required role
 * @return void
 */
function requireRole($role) {
    if (!isLoggedIn() || !hasRole($role)) {
        header("Location: ../index.php");
        exit;
    }
}

/**
 * Get the current user's ID
 * @return int|null The user's ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the current user's username
 * @return string|null The username or null if not logged in
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get the current user's role
 * @return string|null The role or null if not logged in
 */
function getCurrentRole() {
    return $_SESSION['role'] ?? null;
} 