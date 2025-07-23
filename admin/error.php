<?php
session_start();

// Determine where to redirect based on login status
$redirect_url = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'
    ? '/elderly-care-system/admin/index.php'
    : '/elderly-care-system/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .error-icon {
            font-size: 4rem;
            color: #e74a3b;
            margin-bottom: 1rem;
        }
        
        .error-code {
            font-size: 1.5rem;
            color: #5a5c69;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="error-container">
                    <i class="fas fa-exclamation-triangle error-icon"></i>
                    <div class="error-code">500</div>
                    <h1 class="h4 mb-4">System Error</h1>
                    <p class="mb-4">Sorry, something went wrong in the admin portal. Please try again later.</p>
                    <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i><?php echo isset($_SESSION['user_id']) ? 'Return to Dashboard' : 'Return to Login'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 