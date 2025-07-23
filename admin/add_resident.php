<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get all family members for the dropdown
$family_query = "SELECT user_id, username FROM users WHERE role = 'family'";
$family_result = pg_query($db_connection, $family_query);

// Get all staff members for the dropdown
$staff_query = "SELECT staff_id, first_name, last_name FROM staff";
$staff_result = pg_query($db_connection, $staff_query);

// Get all doctors for the primary doctor dropdown
$doctor_query = "SELECT staff_id, first_name, last_name FROM staff WHERE LOWER(position) = 'doctor' ORDER BY first_name, last_name";
$doctor_result = pg_query($db_connection, $doctor_query);

// Get all non-doctor staff for the caregiver dropdown
$caregiver_query = "SELECT staff_id, first_name, last_name FROM staff WHERE LOWER(position) = 'caregiver' ORDER BY first_name, last_name";
$caregiver_result = pg_query($db_connection, $caregiver_query);

// Server-side validation helper
function validate_name($name) {
    return preg_match('/^[A-Za-z ]+$/', $name);
}

function validate_dob($dob) {
    $today = date('Y-m-d');
    return ($dob <= $today);
}

function validate_national_id($id) {
    return preg_match('/^\d{7,8}$/', $id);
}
function validate_passport_number($num) {
    return preg_match('/^[A-Z]{1}\d{7,9}$/', $num);
}
function validate_place_name($name) {
    return preg_match("/^[A-Za-z \-']+$/", $name);
}
function validate_allergies($allergies) {
    return preg_match("/^[A-Za-z \-']{2,30}$/", $allergies);
}
function validate_medical_insurance($val) {
    return preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{5,45}$/', $val);
}
function validate_contact($contact) {
    return preg_match('/^[+]?\d{10,15}$/', $contact);
}
function validate_children_info($info) {
    return preg_match("/^[A-Za-z0-9 ,\-']+$/", $info);
}
function validate_letters_only($val) {
    return preg_match("/^[A-Za-z \-']+$/", $val);
}
function validate_interests($val) {
    return preg_match("/^[A-Za-z ,]+$/", $val);
}

$error_msgs = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    // ... other fields ...

    // Validate names
    if (!validate_name($first_name)) {
        $error_msgs[] = "First name can only contain letters and spaces.";
    }
    if (!validate_name($last_name)) {
        $error_msgs[] = "Last name can only contain letters and spaces.";
    }
    // Validate date of birth
    if (!validate_dob($date_of_birth)) {
        $error_msgs[] = "Date of birth cannot be in the future.";
    }
    // Validate National ID
    if (!empty($_POST['national_id']) && !validate_national_id($_POST['national_id'])) {
        $error_msgs[] = "National ID must be 7-8 digits only.";
    }
    // Validate Passport Number
    if (!empty($_POST['passport_number']) && !validate_passport_number($_POST['passport_number'])) {
        $error_msgs[] = "Passport number must be 1 uppercase letter followed by 7–9 numbers (e.g., A1234567).";
    }
    // Validate Place of Birth
    if (!empty($_POST['place_of_birth']) && !validate_place_name($_POST['place_of_birth'])) {
        $error_msgs[] = "Place of birth: only letters, spaces, hyphens, apostrophes allowed.";
    }
    // Validate Allergies
    if (!empty($_POST['allergies']) && !validate_allergies($_POST['allergies'])) {
        $error_msgs[] = "Allergies: only letters, spaces, hyphens, apostrophes allowed (2–30 characters).";
    }
    // Validate Medical Insurance
    if (!empty($_POST['medical_insurance']) && !validate_medical_insurance($_POST['medical_insurance'])) {
        $error_msgs[] = "Medical insurance: include provider name and policy number (e.g., NHIF, 12345678, 2025-12-31, Comprehensive).";
    }
    // Validate Spouse Name
    if (!empty($_POST['spouse_name']) && !validate_place_name($_POST['spouse_name'])) {
        $error_msgs[] = "Spouse name: only letters, spaces, hyphens, apostrophes allowed.";
    }
    // Validate Spouse Contact
    if (!empty($_POST['spouse_contact']) && !validate_contact($_POST['spouse_contact'])) {
        $error_msgs[] = "Spouse contact: 10-15 digits, may start with +.";
    }
    // Validate Next of Kin Name
    if (!empty($_POST['next_of_kin_name']) && !preg_match('/^[A-Za-z ]+$/', $_POST['next_of_kin_name'])) {
        $error_msgs[] = "Next of kin name: only letters and spaces allowed.";
    }
    // Validate Next of Kin Contact
    if (!empty($_POST['next_of_kin_contact']) && !validate_contact($_POST['next_of_kin_contact'])) {
        $error_msgs[] = "Next of kin contact: 10-15 digits, may start with +.";
    }
    // Validate Children Info
    if (!empty($_POST['children_info']) && !validate_children_info($_POST['children_info'])) {
        $error_msgs[] = "Children info: names and numbers, commas, hyphens, apostrophes allowed.";
    }
    // Validate Religion Other
    if (!empty($_POST['religion_other']) && !validate_place_name($_POST['religion_other'])) {
        $error_msgs[] = "Other religion: only letters, spaces, hyphens, apostrophes allowed.";
    }
    // Validate Medical Condition
    if (!empty($_POST['medical_condition']) && !validate_letters_only($_POST['medical_condition'])) {
        $error_msgs[] = "Medical condition: only letters, spaces, hyphens, apostrophes allowed.";
    }
    // Validate Interests
    if (!empty($_POST['interests']) && !validate_interests($_POST['interests'])) {
        $error_msgs[] = "Interests: only letters, spaces, commas allowed.";
    }
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($_POST['status'])) {
        $error_msgs[] = "Please fill in all required fields.";
    }
    // If errors, show them
    if (!empty($error_msgs)) {
        $_SESSION['error'] = implode("<br>", $error_msgs);
    } else {
        // Handle file upload (reuse logic from process_resident.php)
        function handleFileUpload($file) {
            if (!isset($file['name']) || empty($file['name'])) {
                return null;
            }
            $target_dir = "../uploads/residents/";
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
                return "uploads/residents/" . $new_filename;
            } else {
                $_SESSION['error'] = "Sorry, there was an error uploading your file.";
                return false;
            }
        }

        $profile_picture = handleFileUpload($_FILES['profile_picture'] ?? []);
        if ($profile_picture === false) {
            header("Location: add_resident.php");
            exit;
        }
        $data = array(
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['national_id'] ?? null,
            $_POST['passport_number'] ?? null,
            $_POST['date_of_birth'],
            $_POST['place_of_birth'] ?? null,
            $_POST['previous_address'] ?? null,
            $_POST['blood_type'] ?? null,
            $_POST['allergies'] ?? null,
            $_POST['medical_condition'] ?? null,
            $_POST['medical_insurance'] ?? null,
            $_POST['primary_doctor'] ?? null,
            $_POST['primary_doctor_other'] ?? null,
            $_POST['religion'] ?? null,
            $_POST['religion_other'] ?? null,
            $_POST['next_of_kin_name'] ?? null,
            $_POST['next_of_kin_contact'] ?? null,
            $_POST['interests'] ?? null,
            $_POST['status'],
            $_POST['family_member_id'] ?: null,
            $_POST['caregiver_id'] ?: null,
            $profile_picture
        );
        $query = "INSERT INTO residents (first_name, last_name, national_id, passport_number, date_of_birth, place_of_birth, previous_address, blood_type, allergies, medical_condition, medical_insurance, primary_doctor, primary_doctor_other, religion, religion_other, next_of_kin_name, next_of_kin_contact, interests, status, family_member_id, caregiver_id, profile_picture, admission_date)
                  VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21, $22, CURRENT_DATE)";
        $result = pg_query_params($db_connection, $query, $data);
        if ($result) {
            $_SESSION['success'] = "Resident added successfully.";
            header("Location: residents.php");
            exit;
        } else {
            $_SESSION['error'] = "Error adding resident: " . pg_last_error($db_connection);
            header("Location: add_resident.php");
            exit;
        }
    }
}
?>
<script>
// Client-side validation for names and date of birth
function validateResidentForm() {
    let valid = true;
    let firstName = document.forms[0]["first_name"].value.trim();
    let lastName = document.forms[0]["last_name"].value.trim();
    let dob = document.forms[0]["date_of_birth"].value;
    let today = new Date().toISOString().split('T')[0];
    let namePattern = /^[A-Za-z ]+$/;
    let errorMsg = "";
    if (!namePattern.test(firstName)) {
        errorMsg += "First name can only contain letters and spaces.\n";
        valid = false;
    }
    if (!namePattern.test(lastName)) {
        errorMsg += "Last name can only contain letters and spaces.\n";
        valid = false;
    }
    if (dob > today) {
        errorMsg += "Date of birth cannot be in the future.\n";
        valid = false;
    }
    if (!valid) {
        alert(errorMsg);
    }
    return valid;
}
window.addEventListener('DOMContentLoaded', function() {
    var dobInput = document.querySelector('input[name="date_of_birth"]');
    if (dobInput) {
        dobInput.max = new Date().toISOString().split('T')[0];
    }
});
function toggleDoctorOther() {
    var select = document.getElementById('primary_doctor_select');
    var otherField = document.getElementById('primary_doctor_other_field');
    if (select.value === 'Other') {
        otherField.style.display = '';
    } else {
        otherField.style.display = 'none';
    }
}
function toggleReligionOther() {
    var select = document.getElementById('religion_select');
    var otherField = document.getElementById('religion_other_field');
    if (select.value === 'Other') {
        otherField.style.display = '';
    } else {
        otherField.style.display = 'none';
    }
}
function validateField(input) {
    const pattern = input.getAttribute('pattern');
    if (!pattern) return;
    const regex = new RegExp('^' + pattern + '$');
    if (input.value === "") {
        input.classList.remove('is-invalid', 'is-valid');
        return;
    }
    if (regex.test(input.value)) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[pattern], textarea[pattern]').forEach(function(input) {
        input.addEventListener('input', function() { validateField(input); });
    });
});
</script>
<style>
.is-invalid { border-color: #dc3545 !important; }
.is-valid { border-color: #198754 !important; }
</style>
<div class="container-fluid py-4">
    <h2 class="mb-4">Add New Resident</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" enctype="multipart/form-data" style="max-width: 900px; margin: 0 auto;" onsubmit="return validateResidentForm();">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" name="first_name" required pattern="[A-Za-z ]+" minlength="2" maxlength="50" title="2–50 letters and spaces allowed" oninput="validateField(this)">
                <div class="form-text">2–50 letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" name="last_name" required pattern="[A-Za-z ]+" minlength="2" maxlength="50" title="2–50 letters and spaces allowed" oninput="validateField(this)">
                <div class="form-text">2–50 letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">National ID</label>
                <input type="text" class="form-control" name="national_id" maxlength="30" pattern="\d{7,8}" title="7-8 digits only" oninput="validateField(this)">
                <div class="form-text">7-8 digits only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Passport Number <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" name="passport_number" maxlength="10" pattern="^[A-Z]{1}\d{7,9}$" title="1 uppercase letter followed by 7–9 numbers (e.g., A1234567)" oninput="validateField(this)">
                <div class="form-text">1 uppercase letter followed by 7–9 numbers (e.g., A1234567)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" name="date_of_birth" required oninput="validateField(this)">
                <div class="form-text">Date of birth cannot be in the future.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Place of Birth</label>
                <input type="text" class="form-control" name="place_of_birth" maxlength="100" minlength="2" pattern="[A-Za-z \-']+" title="2–100 letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)">
                <div class="form-text">2–100 letters, spaces, hyphens, apostrophes allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Previous Address</label>
                <textarea class="form-control" name="previous_address" rows="2" oninput="validateField(this)"></textarea>
                <div class="form-text">Previous address (optional)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Picture</label>
                <input type="file" class="form-control" name="profile_picture" accept="image/*" oninput="validateField(this)">
                <div class="form-text">Only JPG, JPEG, PNG & GIF files allowed (max 5MB)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Blood Type</label>
                <select class="form-select" name="blood_type" oninput="validateField(this)">
                    <option value="">Select</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
                <div class="form-text">Blood type (optional)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Allergies</label>
                <textarea class="form-control" name="allergies" rows="2" placeholder="List any allergies" pattern="[A-Za-z \-']{2,30}" minlength="2" maxlength="30" title="2–30 letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)"></textarea>
                <div class="form-text">2–30 letters, spaces, hyphens, apostrophes allowed (e.g., Penicillin, O’Reilly’s, gluten-free).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Medical Condition</label>
                <textarea class="form-control" name="medical_condition" rows="3" placeholder="E.g., Diabetes, Hypertension, etc." pattern="[A-Za-z \-']+" title="Only letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)"></textarea>
                <div class="form-text">Medical condition (optional)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Medical Insurance</label>
                <textarea class="form-control" name="medical_insurance" rows="2" minlength="5" maxlength="45" pattern="^(?=.*[A-Za-z])(?=.*[0-9]).{5,45}$" placeholder="NHIF, 12345678, 2025-12-31, Comprehensive" title="Include provider name and policy number. Example: NHIF, 12345678, 2025-12-31, Comprehensive" oninput="validateField(this)"></textarea>
                <div class="form-text">Up to 45 characters. Format: Provider Name, Policy Number, Expiry Date, Plan Name (e.g., NHIF, 12345678, 2025-12-31, Comprehensive)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Primary Doctor (Facility)</label>
                <select class="form-select" name="primary_doctor" id="primary_doctor_select" onchange="toggleDoctorOther()" oninput="validateField(this)">
                    <option value="">Select</option>
                    <?php if ($doctor_result && pg_num_rows($doctor_result) > 0):
                        while ($doc = pg_fetch_assoc($doctor_result)): ?>
                            <option value="<?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name'] . ' (Doctor)'); ?></option>
                        <?php endwhile;
                    endif; ?>
                    <option value="Other">Other (specify below)</option>
                </select>
                <div class="form-text">Primary doctor (optional)</div>
            </div>
            <div class="col-md-6" id="primary_doctor_other_field" style="display:none;">
                <label class="form-label">Other Doctor (Name & Contact)</label>
                <input type="text" class="form-control" name="primary_doctor_other" maxlength="100" oninput="validateField(this)">
                <div class="form-text">Other doctor's name and contact (optional)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Religion</label>
                <select class="form-select" name="religion" id="religion_select" onchange="toggleReligionOther()" oninput="validateField(this)">
                    <option value="">Select</option>
                    <option value="Christian">Christian</option>
                    <option value="Muslim">Muslim</option>
                    <option value="Hindu">Hindu</option>
                    <option value="Buddhist">Buddhist</option>
                    <option value="Jewish">Jewish</option>
                    <option value="None">None</option>
                    <option value="Other">Other (specify below)</option>
                </select>
                <div class="form-text">Religion (optional)</div>
            </div>
            <div class="col-md-6" id="religion_other_field" style="display:none;">
                <label class="form-label">Other Religion</label>
                <input type="text" class="form-control" name="religion_other" maxlength="50" pattern="[A-Za-z \-']+" title="Only letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)">
                <div class="form-text">Only letters, spaces, hyphens, apostrophes allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next of Kin Name</label>
                <input type="text" class="form-control" name="next_of_kin_name" minlength="2" maxlength="100" pattern="[A-Za-z ]+" title="2–100 letters and spaces only" oninput="validateField(this)">
                <div class="form-text">2–100 letters and spaces only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next of Kin Contact</label>
                <input type="text" class="form-control" name="next_of_kin_contact" minlength="10" maxlength="15" pattern="[+]?\d{10,15}" title="10–15 digits, may start with +" oninput="validateField(this)">
                <div class="form-text">10–15 digits, may start with +</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Interests</label>
                <textarea class="form-control" name="interests" rows="3" pattern="[A-Za-z ,]+" title="Only letters, spaces, commas allowed" oninput="validateField(this)"></textarea>
                <div class="form-text">Interests (optional, letters, spaces, commas only)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" required oninput="validateField(this)">
                    <option value="active">Active</option>
                    <option value="waitlist">Waitlist</option>
                    <option value="former">Former</option>
                </select>
                <div class="form-text">Required</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Family Member</label>
                <div class="input-group">
                    <select class="form-select" name="family_member_id" id="family_member_id_select" oninput="validateField(this)">
                        <option value="">None</option>
                        <?php 
                        pg_result_seek($family_result, 0);
                        while ($family = pg_fetch_assoc($family_result)): 
                        ?>
                            <option value="<?php echo $family['user_id']; ?>">
                                <?php echo htmlspecialchars($family['username']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-text">Family member (optional)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Caregiver</label>
                <select class="form-select" name="caregiver_id" oninput="validateField(this)">
                    <option value="">None</option>
                    <?php if ($caregiver_result && pg_num_rows($caregiver_result) > 0):
                        while ($staff = pg_fetch_assoc($caregiver_result)): ?>
                            <option value="<?php echo $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (Caregiver)'); ?></option>
                        <?php endwhile;
                    endif; ?>
                </select>
                <div class="form-text">Caregiver (optional)</div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="residents.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Resident</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?> 