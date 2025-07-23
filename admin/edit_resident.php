<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

if (!isset($_GET['resident_id']) || !is_numeric($_GET['resident_id'])) {
    echo '<div class="alert alert-danger">Invalid resident ID.</div>';
    exit;
}
$resident_id = $_GET['resident_id'];

// Fetch resident info
$query = "SELECT * FROM residents WHERE resident_id = $1";
$result = pg_query_params($db_connection, $query, [$resident_id]);
$resident = pg_fetch_assoc($result);
if (!$resident) {
    echo '<div class="alert alert-danger">Resident not found.</div>';
    exit;
}
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
?>
<script>
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
function validate_passport_number($num) {
    return preg_match('/^[A-Z]{1}\d{7,9}$/', $num);
}
function validate_medical_insurance($val) {
    return preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{5,45}$/', $val);
}
function validate_interests($val) {
    return preg_match("/^[A-Za-z ,]+$/", $val);
}
</script>
<style>
.is-invalid { border-color: #dc3545 !important; }
.is-valid { border-color: #198754 !important; }
</style>
<div class="container-fluid py-4">
    <h2 class="mb-4">Edit Resident</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="process_resident.php" method="POST" enctype="multipart/form-data" style="max-width: 900px; margin: 0 auto;" onsubmit="return validateResidentForm();">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="resident_id" value="<?php echo $resident['resident_id']; ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" name="first_name" required pattern="[A-Za-z ]+" minlength="2" maxlength="50" title="2–50 letters and spaces allowed" value="<?php echo htmlspecialchars($resident['first_name']); ?>" oninput="validateField(this)">
                <div class="form-text">2–50 letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" name="last_name" required pattern="[A-Za-z ]+" minlength="2" maxlength="50" title="2–50 letters and spaces allowed" value="<?php echo htmlspecialchars($resident['last_name']); ?>" oninput="validateField(this)">
                <div class="form-text">2–50 letters and spaces allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">National ID</label>
                <input type="text" class="form-control" name="national_id" maxlength="30" pattern="\d{7,8}" title="7-8 digits only" value="<?php echo htmlspecialchars($resident['national_id'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">7-8 digits only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Passport Number <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" name="passport_number" maxlength="10" pattern="^[A-Z]{1}\d{7,9}$" title="1 uppercase letter followed by 7–9 numbers (e.g., A1234567)" value="<?php echo htmlspecialchars($resident['passport_number'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">1 uppercase letter followed by 7–9 numbers (e.g., A1234567)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" name="date_of_birth" required value="<?php echo htmlspecialchars($resident['date_of_birth']); ?>" oninput="validateField(this)">
                <div class="form-text">Date of birth cannot be in the future.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Place of Birth</label>
                <input type="text" class="form-control" name="place_of_birth" maxlength="100" minlength="2" pattern="[A-Za-z \-']+" title="2–100 letters, spaces, hyphens, apostrophes allowed" value="<?php echo htmlspecialchars($resident['place_of_birth'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">2–100 letters, spaces, hyphens, apostrophes allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Previous Address</label>
                <textarea class="form-control" name="previous_address" rows="2" oninput="validateField(this)"><?php echo htmlspecialchars($resident['previous_address'] ?? ''); ?></textarea>
                <div class="form-text">Letters, numbers, spaces, hyphens, apostrophes allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Picture</label>
                <input type="file" class="form-control" name="profile_picture" accept="image/*" oninput="validateField(this)">
                <div class="form-text">Only image files (jpg, jpeg, png, gif)</div>
                <?php if (!empty($resident['profile_picture'])): ?>
                    <small class="text-muted">Current: <?php echo htmlspecialchars($resident['profile_picture']); ?></small>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Blood Type</label>
                <select class="form-select" name="blood_type" oninput="validateField(this)">
                    <option value="">Select</option>
                    <?php $blood_types = ["A+","A-","B+","B-","AB+","AB-","O+","O-"]; foreach($blood_types as $bt): ?>
                        <option value="<?php echo $bt; ?>" <?php echo ($resident['blood_type'] ?? '') === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select a blood type</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Allergies</label>
                <textarea class="form-control" name="allergies" rows="2" placeholder="List any allergies" pattern="[A-Za-z \-']{2,30}" minlength="2" maxlength="30" title="2–30 letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)"><?php echo htmlspecialchars($resident['allergies'] ?? ''); ?></textarea>
                <div class="form-text">2–30 letters, spaces, hyphens, apostrophes allowed (e.g., Penicillin, O’Reilly’s, gluten-free).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Medical Condition</label>
                <textarea class="form-control" name="medical_condition" rows="3" placeholder="E.g., Diabetes, Hypertension, etc." pattern="[A-Za-z \-']+" title="Only letters, spaces, hyphens, apostrophes allowed" oninput="validateField(this)"><?php echo htmlspecialchars($resident['medical_condition'] ?? ''); ?></textarea>
                <div class="form-text">Letters, numbers, spaces, hyphens only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Medical Insurance</label>
                <textarea class="form-control" name="medical_insurance" rows="2" minlength="5" maxlength="45" pattern="^(?=.*[A-Za-z])(?=.*[0-9]).{5,45}$" placeholder="NHIF, 12345678, 2025-12-31, Comprehensive" title="Include provider name and policy number. Example: NHIF, 12345678, 2025-12-31, Comprehensive" oninput="validateField(this)"><?php echo htmlspecialchars($resident['medical_insurance'] ?? ''); ?></textarea>
                <div class="form-text">Up to 45 characters. Format: Provider Name, Policy Number, Expiry Date, Plan Name (e.g., NHIF, 12345678, 2025-12-31, Comprehensive)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Primary Doctor (Facility)</label>
                <select class="form-select" name="primary_doctor" id="primary_doctor_select" onchange="toggleDoctorOther()" oninput="validateField(this)">
                    <option value="">Select</option>
                    <?php if ($doctor_result && pg_num_rows($doctor_result) > 0):
                        while ($doc = pg_fetch_assoc($doctor_result)):
                            $doc_name = $doc['first_name'] . ' ' . $doc['last_name']; ?>
                            <option value="<?php echo htmlspecialchars($doc_name); ?>" <?php echo ($resident['primary_doctor'] ?? '') === $doc_name ? 'selected' : ''; ?>><?php echo htmlspecialchars($doc_name . ' (Doctor)'); ?></option>
                        <?php endwhile;
                    endif; ?>
                    <option value="Other" <?php echo ($resident['primary_doctor'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other (specify below)</option>
                </select>
                <div class="form-text">Select a primary doctor or specify "Other"</div>
            </div>
            <div class="col-md-6" id="primary_doctor_other_field" style="display:<?php echo ($resident['primary_doctor'] ?? '') === 'Other' ? '' : 'none'; ?>;">
                <label class="form-label">Other Doctor (Name & Contact)</label>
                <input type="text" class="form-control" name="primary_doctor_other" maxlength="100" value="<?php echo htmlspecialchars($resident['primary_doctor_other'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">Enter the name and contact of the other doctor</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Religion</label>
                <select class="form-select" name="religion" id="religion_select" onchange="toggleReligionOther()" oninput="validateField(this)">
                    <option value="">Select</option>
                    <?php $religions = ["Christian","Muslim","Hindu","Buddhist","Jewish","None","Other"]; foreach($religions as $rel): ?>
                        <option value="<?php echo $rel; ?>" <?php echo ($resident['religion'] ?? '') === $rel ? 'selected' : ''; ?>><?php echo $rel; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select a religion or specify "Other"</div>
            </div>
            <div class="col-md-6" id="religion_other_field" style="display:<?php echo ($resident['religion'] ?? '') === 'Other' ? '' : 'none'; ?>;">
                <label class="form-label">Other Religion</label>
                <input type="text" class="form-control" name="religion_other" maxlength="50" pattern="[A-Za-z \-']+" title="Only letters, spaces, hyphens, apostrophes allowed" value="<?php echo htmlspecialchars($resident['religion_other'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">Only letters, spaces, hyphens, apostrophes allowed</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next of Kin Name</label>
                <input type="text" class="form-control" name="next_of_kin_name" minlength="2" maxlength="100" pattern="[A-Za-z ]+" title="2–100 letters and spaces only" value="<?php echo htmlspecialchars($resident['next_of_kin_name'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">2–100 letters and spaces only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next of Kin Contact</label>
                <input type="text" class="form-control" name="next_of_kin_contact" minlength="10" maxlength="15" pattern="[+]?\d{10,15}" title="10–15 digits, may start with +" value="<?php echo htmlspecialchars($resident['next_of_kin_contact'] ?? ''); ?>" oninput="validateField(this)">
                <div class="form-text">10–15 digits, may start with +</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Interests</label>
                <textarea class="form-control" name="interests" rows="3" pattern="[A-Za-z ,]+" title="Only letters, spaces, commas allowed" oninput="validateField(this)"><?php echo htmlspecialchars($resident['interests'] ?? ''); ?></textarea>
                <div class="form-text">Interests (optional, letters, spaces, commas only)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" required oninput="validateField(this)">
                    <option value="active" <?php echo $resident['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="waitlist" <?php echo $resident['status'] === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                    <option value="former" <?php echo $resident['status'] === 'former' ? 'selected' : ''; ?>>Former</option>
                </select>
                <div class="form-text">Select a status</div>
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
                            <option value="<?php echo $family['user_id']; ?>" <?php echo ($resident['family_member_id'] == $family['user_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($family['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text">Select a family member or none</div>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Caregiver</label>
                <select class="form-select" name="caregiver_id" oninput="validateField(this)">
                    <option value="">None</option>
                    <?php if ($caregiver_result && pg_num_rows($caregiver_result) > 0):
                        while ($staff = pg_fetch_assoc($caregiver_result)): ?>
                            <option value="<?php echo $staff['staff_id']; ?>" <?php echo ($resident['caregiver_id'] == $staff['staff_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (Caregiver)'); ?></option>
                        <?php endwhile;
                    endif; ?>
                </select>
                <div class="form-text">Select a caregiver or none</div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="residents.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?> 