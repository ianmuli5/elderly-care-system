<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);
    
    $query = "SELECT email FROM users WHERE username = $1";
    $result = pg_query_params($db_connection, $query, array($username));
    
    if (pg_num_rows($result) === 1) {
        $user = pg_fetch_assoc($result);
        echo json_encode(['email' => $user['email']]);
    } else {
        echo json_encode(['email' => null]);
    }
} else {
    echo json_encode(['email' => null]);
}
?> 