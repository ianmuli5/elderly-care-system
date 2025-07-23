<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $error = '';
    $success = '';
    
    // Check if email exists for any user
    $query = "SELECT user_id, username, role FROM users WHERE email = $1";
    $result = pg_query_params($db_connection, $query, array($email));
    if (pg_num_rows($result) === 1) {
        $user = pg_fetch_assoc($result);
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+3 hours'));
        // Save token and expiry in users table
        $update = pg_query_params($db_connection, "UPDATE users SET reset_token = $1, token_expiry = $2 WHERE user_id = $3", array($token, $expiry, $user['user_id']));
        // Send email with reset link
        $reset_link = sprintf(
            '%s://%s%s/reset_password.php?token=%s',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            dirname($_SERVER['REQUEST_URI']),
            $token
        );
        $subject = 'Password Reset Request';
        $message = "Hello,\n\nA password reset was requested for your account. If you did not request this, you can ignore this email.\n\nTo reset your password, click the link below (valid for 3 hours):\n$reset_link\n\nThank you.";
        $headers = 'From: no-reply@elderly-care-system.local';
        // Suppress mail() warning for local development
        @mail($email, $subject, $message, $headers);
        $success = '<div class="alert alert-success text-center"><h5>Password Reset Instructions</h5><p>To change your password:</p><ol class="text-start d-inline-block" style="text-align:left;"><li>Click the link below.</li><li>Enter your new password on the page that opens.</li><li>Login with your new password.</li></ol></div>';
        $success .= '<div class="d-flex justify-content-center"><div class="alert alert-info text-center" style="max-width:500px;"><strong>Password Reset Link:</strong><br><a href="' . htmlspecialchars($reset_link) . '" class="fw-bold" style="word-break:break-all;">' . htmlspecialchars($reset_link) . '</a></div></div>';
    } else {
        $error = 'No account found with that email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow mt-5">
                    <div class="card-body">
                        <h3 class="mb-4 text-center">Forgot Password</h3>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success)): ?>
                            <?php echo $success; ?>
                        <?php endif; ?>
                        <?php if (empty($success)): ?>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                            </div>
                        </form>
                        <?php endif; ?>
                        <div class="mt-3 text-center">
                            <a href="index.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 