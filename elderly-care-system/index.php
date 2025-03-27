<?php
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: user/index.php");
    }
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Check credentials
        $query = "SELECT * FROM users WHERE username = $1";
        $result = pg_query_params($db_connection, $query, array($username));
        
        if (pg_num_rows($result) === 1) {
            $user = pg_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Successful login - create session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: user/index.php");
                }
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elderly Care Monitoring System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('assets/images/elderly-care-bg.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
        }
        
        .hero-section {
            padding-top: 150px;
            padding-bottom: 150px;
            color: white;
        }
        
        .login-card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row hero-section">
            <div class="col-md-6">
                <h1 class="display-4">Elderly Care Monitoring System</h1>
                <p class="lead">A comprehensive solution for monitoring and managing care for elderly residents.</p>
                <hr class="my-4">
                <p>Providing quality care through technology.</p>
            </div>
            
            <div class="col-md-5 offset-md-1">
                <div class="login-card">
                    <h2 class="text-center mb-4">Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="nav nav-tabs mb-3" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" 
                                data-bs-target="#login-pane" type="button" role="tab" 
                                aria-controls="login-pane" aria-selected="true">Login</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" 
                                data-bs-target="#register-pane" type="button" role="tab" 
                                aria-controls="register-pane" aria-selected="false">Register</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="loginTabsContent">
                        <!-- Login Form -->
                        <div class="tab-pane fade show active" id="login-pane" role="tabpanel" aria-labelledby="login-tab">
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
                                    <button type="submit" class="btn btn-primary">Login</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Registration Form -->
                        <div class="tab-pane fade" id="register-pane" role="tabpanel" aria-labelledby="register-tab">
                            <form method="post" action="register.php">
                                <div class="mb-3">
                                    <label for="reg-username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="reg-username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reg-password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="reg-password" name="password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm-password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm-password" name="confirm_password" required>
                                </div>
                                
                                <input type="hidden" name="role" value="family">
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">Register</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-white text-center py-3 fixed-bottom">
        <div class="container">
            <p class="mb-0">© <?php echo date('Y'); ?> Elderly Care Monitoring System</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 