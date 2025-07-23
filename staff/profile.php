<?php
require_once '../includes/config.php';
require_once 'includes/auth_check.php';

// Get staff user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = $1 AND role = 'staff'";
$user_result = pg_query_params($db_connection, $user_query, array($user_id));
$user = pg_fetch_assoc($user_result);

// Get staff profile information
$staff_query = "SELECT * FROM staff WHERE user_id = $1";
$staff_result = pg_query_params($db_connection, $staff_query, array($user_id));
$staff = pg_fetch_assoc($staff_result);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $position = trim($_POST['position']);
        $contact_info = trim($_POST['contact_info']);

        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            $error_message = "Current password is incorrect";
        } else {
            // Check if username or email is already taken
            $check_query = "SELECT * FROM users WHERE (username = $1 OR email = $2) AND user_id != $3";
            $check_result = pg_query_params($db_connection, $check_query, array($username, $email, $user_id));
            if (pg_num_rows($check_result) > 0) {
                $error_message = "Username or email already exists";
            } else {
                // Update user information
                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        $error_message = "New passwords do not match";
                    } else {
                        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_user_query = "UPDATE users SET username = $1, email = $2, password_hash = $3 WHERE user_id = $4";
                        $result_user = pg_query_params($db_connection, $update_user_query, array($username, $email, $password_hash, $user_id));
                    }
                } else {
                    $update_user_query = "UPDATE users SET username = $1, email = $2 WHERE user_id = $3";
                    $result_user = pg_query_params($db_connection, $update_user_query, array($username, $email, $user_id));
                }

                // Update staff profile information
                $update_staff_query = "UPDATE staff SET position = $1, contact_info = $2 WHERE user_id = $3";
                $result_staff = pg_query_params($db_connection, $update_staff_query, array($position, $contact_info, $user_id));

                if ($result_user && $result_staff) {
                    $success_message = "Profile updated successfully";
                    $_SESSION['username'] = $username;
                    $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                } else {
                    $error_message = "Failed to update profile";
                }
            }
        }
    }
}

require_once 'includes/header.php';
?>
<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">My Profile</h4>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form method="POST" class="mb-4">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($staff['position']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact_info" class="form-label">Contact Information</label>
                            <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($staff['contact_info']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?> 