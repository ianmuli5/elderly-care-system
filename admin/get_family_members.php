<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
// Only return family members that still exist in the users table
$result = pg_query($db_connection, "SELECT user_id, username FROM users WHERE role = 'family' ORDER BY username");
$family = array();
while ($row = pg_fetch_assoc($result)) {
    $family[] = $row;
}
echo json_encode($family); 