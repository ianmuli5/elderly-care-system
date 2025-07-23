<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get all residents for dropdown
$residents_query = "SELECT resident_id, first_name, last_name FROM residents WHERE status = 'active' ORDER BY first_name, last_name";
$residents_result = pg_query($db_connection, $residents_query);

// Get all staff members for dropdown
$staff_query = "SELECT staff_id, first_name, last_name FROM staff WHERE active = true ORDER BY first_name, last_name";
$staff_result = pg_query($db_connection, $staff_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_id = $_POST['resident_id'];
    $category = $_POST['category'];
    $priority_level = $_POST['priority_level'];
    $alert_level = $_POST['alert_level'];
    $description = trim($_POST['description']);
    $location = trim($_POST['location'] ?? '');
    $staff_id = $_POST['staff_id'] ?: null;
    $response_required_by = !empty($_POST['response_required_by']) ? $_POST['response_required_by'] : null;
    
    $errors = [];
    
    // Validate required fields
    if (empty($resident_id)) {
        $errors[] = "Please select a resident.";
    }
    if (empty($category)) {
        $errors[] = "Please select a category.";
    }
    if (empty($priority_level)) {
        $errors[] = "Please select a priority level.";
    }
    if (empty($alert_level)) {
        $errors[] = "Please select an alert level.";
    }
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    
    // Validate description is not just numbers
    if (!empty($description) && is_numeric($description)) {
        $errors[] = "Description cannot be just numbers. Please provide a detailed description.";
    }
    
    // Validate description length
    if (!empty($description) && strlen($description) < 10) {
        $errors[] = "Description must be at least 10 characters long.";
    }
    
    // Validate location if provided
    if (!empty($location) && is_numeric($location)) {
        $errors[] = "Location cannot be just numbers. Please provide a proper location description.";
    }
    
    // Validate response required by date if provided
    if (!empty($response_required_by)) {
        $response_timestamp = strtotime($response_required_by);
        $current_timestamp = time();
        
        if ($response_timestamp <= $current_timestamp) {
            $errors[] = "Response required by date must be in the future.";
        }
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        $query = "INSERT INTO medical_alerts (resident_id, category, priority_level, alert_level, description, location, staff_id, response_required_by, status, created_at) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, 'pending', CURRENT_TIMESTAMP)";
        $params = [$resident_id, $category, $priority_level, $alert_level, $description, $location, $staff_id, $response_required_by];
        $result = pg_query_params($db_connection, $query, $params);
        if ($result) {
            $_SESSION['success'] = "Alert added successfully.";
            header("Location: alerts.php");
            exit;
        } else {
            $_SESSION['error'] = "Error adding alert: " . pg_last_error($db_connection);
            header("Location: add_alert.php");
            exit;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Add New Alert</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" style="max-width: 900px; margin: 0 auto;" id="addAlertForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Resident</label>
                <select class="form-select" name="resident_id" required 
                        value="<?php echo isset($_POST['resident_id']) ? htmlspecialchars($_POST['resident_id']) : ''; ?>">
                    <option value="">Select Resident</option>
                    <?php 
                    pg_result_seek($residents_result, 0);
                    while ($resident = pg_fetch_assoc($residents_result)): 
                    ?>
                        <option value="<?php echo $resident['resident_id']; ?>" 
                                <?php echo (isset($_POST['resident_id']) && $_POST['resident_id'] == $resident['resident_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <select class="form-select" name="category" required 
                        value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>">
                    <option value="">Select Category</option>
                    <option value="medical_emergency" <?php echo (isset($_POST['category']) && $_POST['category'] == 'medical_emergency') ? 'selected' : ''; ?>>Medical Emergency</option>
                    <option value="medication" <?php echo (isset($_POST['category']) && $_POST['category'] == 'medication') ? 'selected' : ''; ?>>Medication</option>
                    <option value="fall" <?php echo (isset($_POST['category']) && $_POST['category'] == 'fall') ? 'selected' : ''; ?>>Fall</option>
                    <option value="behavioral" <?php echo (isset($_POST['category']) && $_POST['category'] == 'behavioral') ? 'selected' : ''; ?>>Behavioral</option>
                    <option value="dietary" <?php echo (isset($_POST['category']) && $_POST['category'] == 'dietary') ? 'selected' : ''; ?>>Dietary</option>
                    <option value="mobility" <?php echo (isset($_POST['category']) && $_POST['category'] == 'mobility') ? 'selected' : ''; ?>>Mobility</option>
                    <option value="sleep" <?php echo (isset($_POST['category']) && $_POST['category'] == 'sleep') ? 'selected' : ''; ?>>Sleep</option>
                    <option value="vitals" <?php echo (isset($_POST['category']) && $_POST['category'] == 'vitals') ? 'selected' : ''; ?>>Vitals</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Priority Level</label>
                <select class="form-select" name="priority_level" required 
                        value="<?php echo isset($_POST['priority_level']) ? htmlspecialchars($_POST['priority_level']) : ''; ?>">
                    <option value="">Select Priority</option>
                    <option value="critical" <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'critical') ? 'selected' : ''; ?>>Critical</option>
                    <option value="high" <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'high') ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo (isset($_POST['priority_level']) && $_POST['priority_level'] == 'low') ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Alert Level</label>
                <select class="form-select" name="alert_level" required 
                        value="<?php echo isset($_POST['alert_level']) ? htmlspecialchars($_POST['alert_level']) : ''; ?>">
                    <option value="">Select Alert Level</option>
                    <option value="red" <?php echo (isset($_POST['alert_level']) && $_POST['alert_level'] == 'red') ? 'selected' : ''; ?>>Red</option>
                    <option value="yellow" <?php echo (isset($_POST['alert_level']) && $_POST['alert_level'] == 'yellow') ? 'selected' : ''; ?>>Yellow</option>
                    <option value="blue" <?php echo (isset($_POST['alert_level']) && $_POST['alert_level'] == 'blue') ? 'selected' : ''; ?>>Blue</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" required 
                          oninput="validateDescription(this)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                <div class="form-text">Description must be at least 10 characters and cannot be just numbers</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="location" 
                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                       oninput="validateLocation(this)">
                <div class="form-text">Optional: Location cannot be just numbers</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Assigned Staff</label>
                <select class="form-select" name="staff_id" 
                        value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>">
                    <option value="">Unassigned</option>
                    <?php 
                    pg_result_seek($staff_result, 0);
                    while ($staff = pg_fetch_assoc($staff_result)): 
                    ?>
                        <option value="<?php echo $staff['staff_id']; ?>" 
                                <?php echo (isset($_POST['staff_id']) && $_POST['staff_id'] == $staff['staff_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Response Required By</label>
                <input type="datetime-local" class="form-control" name="response_required_by" 
                       value="<?php echo isset($_POST['response_required_by']) ? htmlspecialchars($_POST['response_required_by']) : ''; ?>"
                       onchange="validateResponseDate(this)">
                <div class="form-text">Optional: Must be in the future</div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="alerts.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Alert</button>
        </div>
    </form>
</div>

<script>
function validateDescription(input) {
    const description = input.value.trim();
    const isNumeric = !isNaN(description) && description !== '';
    const isTooShort = description.length > 0 && description.length < 10;
    
    if (isNumeric || isTooShort) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (description.length >= 10) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateLocation(input) {
    const location = input.value.trim();
    const isNumeric = !isNaN(location) && location !== '';
    
    if (isNumeric && location.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (location.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateResponseDate(input) {
    const selectedDate = new Date(input.value);
    const currentDate = new Date();
    
    if (selectedDate <= currentDate && input.value !== '') {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (input.value !== '') {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

// Set minimum date to current date and time
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.querySelector('input[name="response_required_by"]');
    if (dateInput) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        dateInput.min = minDateTime;
    }
    
    // Validate form before submission
    document.getElementById('addAlertForm').addEventListener('submit', function(e) {
        const description = document.querySelector('textarea[name="description"]').value.trim();
        const location = document.querySelector('input[name="location"]').value.trim();
        const responseDate = document.querySelector('input[name="response_required_by"]').value;
        
        let hasErrors = false;
        
        // Check if description is numeric or too short
        if (!isNaN(description) && description !== '') {
            alert('Description cannot be just numbers. Please provide a detailed description.');
            hasErrors = true;
        }
        
        if (description.length < 10) {
            alert('Description must be at least 10 characters long.');
            hasErrors = true;
        }
        
        // Check if location is numeric
        if (!isNaN(location) && location !== '') {
            alert('Location cannot be just numbers. Please provide a proper location description.');
            hasErrors = true;
        }
        
        // Check if response date is in the past
        if (responseDate) {
            const selectedDate = new Date(responseDate);
            const currentDate = new Date();
            if (selectedDate <= currentDate) {
                alert('Response required by date must be in the future.');
                hasErrors = true;
            }
        }
        
        if (hasErrors) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 