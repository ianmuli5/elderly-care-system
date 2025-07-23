<?php
require_once '../includes/config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Find user by token and check expiry
        $query = "SELECT user_id, reset_token, token_expiry FROM users WHERE reset_token = $1 AND token_expiry > NOW() AND role = 'staff'";
        $result = pg_query_params($db_connection, $query, array($token));
        if (pg_num_rows($result) === 1) {
            $user = pg_fetch_assoc($result);
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            // Update password and clear token
            $update = pg_query_params($db_connection, "UPDATE users SET password_hash = $1, reset_token = NULL, token_expiry = NULL WHERE user_id = $2", array($password_hash, $user['user_id']));
            if ($update) {
                $success = 'Password has been reset successfully. You can now <a href=\'login.php\'>login</a>.';
            } else {
                $error = 'Error updating password. Please try again.';
            }
        } else {
            $error = 'Invalid or expired reset token.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Staff Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow mt-5">
                    <div class="card-body">
                        <h3 class="mb-4 text-center">Reset Password</h3>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if (empty($success)): ?>
                        <form method="post" action="">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        <?php endif; ?>
                        <div class="mt-3 text-center">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 