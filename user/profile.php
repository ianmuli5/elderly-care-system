<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    header("Location: ../index.php");
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = $1";
$user_result = pg_query_params($db_connection, $user_query, array($user_id));
$user = pg_fetch_assoc($user_result);

// Get resident information
$resident_query = "SELECT r.*, s.first_name as staff_first_name, s.last_name as staff_last_name 
                   FROM residents r 
                   LEFT JOIN staff s ON r.caregiver_id = s.staff_id 
                   WHERE r.family_member_id = $1";
$resident_result = pg_query_params($db_connection, $resident_query, array($user_id));
$resident = pg_fetch_assoc($resident_result);

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone_number = trim($_POST['phone_number']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Phone number validation
        if (!preg_match('/^\d{10}$/', $phone_number)) {
            $error_message = "Phone number must be exactly 10 digits.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else if (!password_verify($current_password, $user['password_hash'])) {
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
                        $update_query = "UPDATE users SET username = $1, email = $2, phone_number = $3, password_hash = $4 WHERE user_id = $5";
                        $result = pg_query_params($db_connection, $update_query, array($username, $email, $phone_number, $password_hash, $user_id));
                    }
                } else {
                    $update_query = "UPDATE users SET username = $1, email = $2, phone_number = $3 WHERE user_id = $4";
                    $result = pg_query_params($db_connection, $update_query, array($username, $email, $phone_number, $user_id));
                }
                if (isset($result) && $result) {
                    $success_message = "Profile updated successfully";
                    $_SESSION['username'] = $username;
                } else if (!isset($error_message)) {
                    $error_message = "Failed to update profile";
                }
            }
        }
    }
    if (isset($_POST['delete_account'])) {
        // Delete user and all related data
        $delete_query = "DELETE FROM users WHERE user_id = $1";
        $delete_result = pg_query_params($db_connection, $delete_query, array($user_id));
        if ($delete_result) {
            session_destroy();
            header('Location: ../index.php');
            exit;
        } else {
            $error_message = "Failed to delete account.";
        }
    }
}

// Include the header
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
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$" maxlength="100" value="<?php echo htmlspecialchars($user['email']); ?>" required title="Enter a valid email address">
                            <div class="form-text">Enter a valid email address (e.g., user@example.com)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" pattern="\d{10}" maxlength="10" minlength="10" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required title="Enter a 10-digit phone number">
                            <div class="form-text">Enter a 10-digit phone number (numbers only)</div>
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
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                        <button type="submit" name="delete_account" class="btn btn-danger">Delete My Account</button>
                    </form>

                    <?php if ($resident): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Resident Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($resident['date_of_birth'])); ?></p>
                                        <p><strong>Status:</strong> <span class="badge bg-<?php echo $resident['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($resident['status']); ?>
                                        </span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Assigned Caregiver:</strong> 
                                            <?php echo $resident['staff_first_name'] && $resident['staff_last_name'] 
                                                ? htmlspecialchars($resident['staff_first_name'] . ' ' . $resident['staff_last_name'])
                                                : 'Not assigned'; ?>
                                        </p>
                                        <p><strong>Admission Date:</strong> 
                                            <?php echo $resident['admission_date'] 
                                                ? date('F d, Y', strtotime($resident['admission_date']))
                                                : 'Not specified'; ?>
                                        </p>
                                        <p><strong>Medical Condition:</strong> <?php echo htmlspecialchars($resident['medical_condition'] ?? 'Not specified'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 