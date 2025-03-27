<?php
require_once '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if staff is already logged in
if (isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Check credentials against staff table
        $query = "SELECT s.*, u.username, u.password_hash 
                 FROM staff s 
                 JOIN users u ON s.user_id = u.user_id 
                 WHERE u.username = $1";
        $result = pg_query_params($db_connection, $query, array($username));
        
        if (pg_num_rows($result) === 1) {
            $staff = pg_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $staff['password_hash'])) {
                // Successful login - create session
                $_SESSION['staff_id'] = $staff['staff_id'];
                $_SESSION['staff_name'] = $staff['first_name'] . ' ' . $staff['last_name'];
                $_SESSION['staff_role'] = 'staff';
                $_SESSION['user_id'] = $staff['user_id'];
                
                // Redirect to the stored URL if it exists, otherwise to index
                if (isset($_SESSION['redirect_url'])) {
                    $redirect = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect);
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Staff member not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Elderly Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('../assets/images/elderly-care-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header i {
            font-size: 3rem;
            color: #4e73df;
            margin-bottom: 15px;
        }
        
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-user-md"></i>
                <h2>Staff Login</h2>
                <p class="text-muted">Access your dashboard, resident information, messages, and events</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 