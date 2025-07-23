<?php
session_start();

// Determine redirect URL based on login status
$redirect_url = isset($_SESSION['user_id']) && $_SESSION['role'] === 'family' 
    ? '/elderly-care-system/user/dashboard.php' 
    : '/elderly-care-system/index.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Family Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        
        .error-container {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-exclamation-triangle error-icon"></i>
        <h2 class="mb-4">Unauthorized Access</h2>
        <p class="mb-4">You do not have permission to access this page. Please ensure you are logged in with your family member account.</p>
        <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>Return to <?php echo isset($_SESSION['user_id']) && $_SESSION['role'] === 'family' ? 'Dashboard' : 'Login'; ?>
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 