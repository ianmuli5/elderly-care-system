<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload
    function handleFileUpload($file) {
        if (!isset($file['name']) || empty($file['name'])) {
            return null;
        }
        $target_dir = "../uploads/staff/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            $_SESSION['error'] = "File is not an image.";
            return false;
        }
        if ($file['size'] > 5000000) {
            $_SESSION['error'] = "Sorry, your file is too large.";
            return false;
        }
        if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif") {
            $_SESSION['error'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            return false;
        }
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return "uploads/staff/" . $new_filename;
        } else {
            $_SESSION['error'] = "Sorry, there was an error uploading your file.";
            return false;
        }
    }

    $profile_picture = handleFileUpload($_FILES['profile_picture'] ?? []);
    if ($profile_picture === false) {
        header("Location: add_staff.php");
        exit;
    }
    
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = strtolower(trim($_POST['email']));
    $position = $_POST['position'];
    $contact_info = trim($_POST['contact_info']);
    $active = 1; // New staff are active by default
    
    $errors = [];
    
    // Validate names (letters and spaces only)
    if (!preg_match('/^[A-Za-z ]+$/', $first_name)) {
        $errors[] = "First name can only contain letters and spaces.";
    }
    if (!preg_match('/^[A-Za-z ]+$/', $last_name)) {
        $errors[] = "Last name can only contain letters and spaces.";
    }
    
    // Validate username (alphanumeric and underscores only)
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    // Validate position
    $valid_positions = ['nurse', 'caregiver', 'doctor', 'therapist', 'HR officer'];
    if (!in_array($position, $valid_positions)) {
        $errors[] = "Please select a valid position.";
    }
    
    // Validate contact info is not empty
    if (empty($contact_info)) {
        $errors[] = "Contact information is required.";
    }
    
    // Validate National ID
    $national_id = trim($_POST['national_id'] ?? '');
    if (empty($national_id)) {
        $errors[] = "National ID is required.";
    } elseif (!preg_match('/^\d{8}$/', $national_id)) {
        $errors[] = "National ID must be exactly 8 digits.";
    }

    // Date of Employment
    $date_of_employment = $_POST['date_of_employment'] ?? date('Y-m-d');
    if (empty($date_of_employment)) {
        $errors[] = "Date of employment is required.";
    } elseif ($date_of_employment > date('Y-m-d')) {
        $errors[] = "Date of employment cannot be in the future.";
    }

    // Work Permit Number (optional)
    $work_permit_number = trim($_POST['work_permit_number'] ?? '');
    if (!empty($work_permit_number) && !preg_match('/^[A-Za-z0-9]{1,10}$/', $work_permit_number)) {
        $errors[] = "Work Permit Number must be 1-10 alphanumeric characters.";
    }
    
    // Check for unique username/email
    $check_query = "SELECT 1 FROM users WHERE username = $1 OR email = $2";
    $check_result = pg_query_params($db_connection, $check_query, [$username, $email]);
    if (pg_num_rows($check_result) > 0) {
        $errors[] = "Username or email already exists.";
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction to ensure both operations succeed or both fail
        pg_query($db_connection, "BEGIN");
        
        try {
            // Insert into users table
            $insert_user_query = "INSERT INTO users (username, password_hash, email, role) VALUES ($1, $2, $3, 'staff') RETURNING user_id";
            $user_result = pg_query_params($db_connection, $insert_user_query, [$username, $password_hash, $email]);
            
            if (!$user_result) {
                throw new Exception('Error creating user account: ' . pg_last_error($db_connection));
            }
            
            $user_id = pg_fetch_result($user_result, 0, 0);
            
            // Insert into staff table
            $insert_staff_query = "INSERT INTO staff (user_id, first_name, last_name, position, contact_info, profile_picture, active, hiring_date, national_id, date_of_employment, work_permit_number) VALUES ($1, $2, $3, $4, $5, $6, $7, CURRENT_DATE, $8, $9, $10)";
            $staff_result = pg_query_params($db_connection, $insert_staff_query, [$user_id, $first_name, $last_name, $position, $contact_info, $profile_picture, $active, $national_id, $date_of_employment, $work_permit_number]);
            
            if (!$staff_result) {
                throw new Exception('Error adding staff member: ' . pg_last_error($db_connection));
            }
            
            // Commit transaction
            pg_query($db_connection, "COMMIT");
            $_SESSION['success'] = 'Staff member added successfully.';
            header('Location: staff.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            pg_query($db_connection, "ROLLBACK");
            $_SESSION['error'] = $e->getMessage();
            header('Location: add_staff.php');
            exit;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Add New Staff Member</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" enctype="multipart/form-data" style="max-width: 900px; margin: 0 auto;" id="addStaffForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" required 
                       pattern="[A-Za-z ]+" title="Only letters and spaces allowed"
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                       oninput="validateName(this)">
                <div class="form-text">Only letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" required 
                       pattern="[A-Za-z ]+" title="Only letters and spaces allowed"
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                       oninput="validateName(this)">
                <div class="form-text">Only letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required 
                       pattern="[A-Za-z0-9_]+" title="Letters, numbers, and underscores only"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       oninput="validateUsername(this)">
                <div class="form-text">Letters, numbers, and underscores only (min 3 characters)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required 
                       value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>"
                       oninput="validatePassword(this)">
                <div class="form-text">Minimum 6 characters</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       oninput="validateEmail(this)">
                <div class="form-text">Enter a valid email address</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Position</label>
                <select name="position" class="form-select" required 
                        value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                    <option value="">Select Position</option>
                    <option value="nurse" <?php echo (isset($_POST['position']) && $_POST['position'] == 'nurse') ? 'selected' : ''; ?>>Nurse</option>
                    <option value="caregiver" <?php echo (isset($_POST['position']) && $_POST['position'] == 'caregiver') ? 'selected' : ''; ?>>Caregiver</option>
                    <option value="doctor" <?php echo (isset($_POST['position']) && $_POST['position'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                    <option value="therapist" <?php echo (isset($_POST['position']) && $_POST['position'] == 'therapist') ? 'selected' : ''; ?>>Therapist</option>
                    <option value="HR officer" <?php echo (isset($_POST['position']) && $_POST['position'] == 'HR officer') ? 'selected' : ''; ?>>HR Officer</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact Information</label>
                <input type="text" name="contact_info" class="form-control" required 
                       value="<?php echo isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : ''; ?>"
                       oninput="validateContactInfo(this)">
                <div class="form-text">Enter phone number or other contact information</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">National ID</label>
                <input type="text" class="form-control" name="national_id" required pattern="\d{8}" maxlength="8" title="Exactly 8 digits" />
                <div class="form-text">Exactly 8 digits</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of Employment</label>
                <input type="date" class="form-control" name="date_of_employment" value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>" />
                <div class="form-text">Date the staff member started employment (cannot be in the future)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Work Permit Number <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" name="work_permit_number" maxlength="10" pattern="[A-Za-z0-9]{1,10}" title="1-10 alphanumeric characters" />
                <div class="form-text">1-10 alphanumeric characters (if applicable)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Picture</label>
                <input type="file" name="profile_picture" class="form-control" accept="image/*">
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="staff.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Staff Member</button>
        </div>
    </form>
</div>

<script>
function validateName(input) {
    const name = input.value.trim();
    const namePattern = /^[A-Za-z ]+$/;
    
    if (namePattern.test(name) && name.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else if (name.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateUsername(input) {
    const username = input.value.trim();
    const usernamePattern = /^[A-Za-z0-9_]+$/;
    
    if (usernamePattern.test(username) && username.length >= 3) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else if (username.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validatePassword(input) {
    const password = input.value;
    
    if (password.length >= 6) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else if (password.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateEmail(input) {
    const email = input.value.trim();
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (emailPattern.test(email)) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else if (email.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateContactInfo(input) {
    const contactInfo = input.value.trim();
    
    if (contactInfo.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    }
}

// Validate form before submission
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addStaffForm').addEventListener('submit', function(e) {
        const firstName = document.querySelector('input[name="first_name"]').value.trim();
        const lastName = document.querySelector('input[name="last_name"]').value.trim();
        const username = document.querySelector('input[name="username"]').value.trim();
        const password = document.querySelector('input[name="password"]').value;
        const email = document.querySelector('input[name="email"]').value.trim();
        const contactInfo = document.querySelector('input[name="contact_info"]').value.trim();
        
        let hasErrors = false;
        
        // Validate names
        const namePattern = /^[A-Za-z ]+$/;
        if (!namePattern.test(firstName)) {
            alert('First name can only contain letters and spaces.');
            hasErrors = true;
        }
        if (!namePattern.test(lastName)) {
            alert('Last name can only contain letters and spaces.');
            hasErrors = true;
        }
        
        // Validate username
        const usernamePattern = /^[A-Za-z0-9_]+$/;
        if (!usernamePattern.test(username) || username.length < 3) {
            alert('Username must be at least 3 characters and contain only letters, numbers, and underscores.');
            hasErrors = true;
        }
        
        // Validate password
        if (password.length < 6) {
            alert('Password must be at least 6 characters long.');
            hasErrors = true;
        }
        
        // Validate email
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            alert('Please enter a valid email address.');
            hasErrors = true;
        }
        
        // Validate contact info
        if (contactInfo.length === 0) {
            alert('Contact information is required.');
            hasErrors = true;
        }
        
        if (hasErrors) {
            e.preventDefault();
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var employmentInput = document.querySelector('input[name="date_of_employment"]');
    if (employmentInput) {
        employmentInput.max = new Date().toISOString().split('T')[0];
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 