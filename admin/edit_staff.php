<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

if (!isset($_GET['staff_id']) || !is_numeric($_GET['staff_id'])) {
    echo '<div class="alert alert-danger">Invalid staff ID.</div>';
    exit;
}
$staff_id = $_GET['staff_id'];

// Fetch staff and user info
$query = "SELECT s.*, u.email, u.username, s.active::text as active_text FROM staff s LEFT JOIN users u ON s.user_id = u.user_id WHERE s.staff_id = $1";
$result = pg_query_params($db_connection, $query, [$staff_id]);
$staff = pg_fetch_assoc($result);
if (!$staff) {
    echo '<div class="alert alert-danger">Staff member not found.</div>';
    exit;
}
$positions = array(
    'doctor' => 'Doctor',
    'nurse' => 'Nurse',
    'caregiver' => 'Caregiver',
    'cleaner' => 'Cleaner',
    'hr_officer' => 'HR Officer',
    'other' => 'Other'
);
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Edit Staff Member</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo nl2br(htmlspecialchars($_SESSION['error'])); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="process_staff.php" method="POST" enctype="multipart/form-data" style="max-width: 900px; margin: 0 auto;">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($staff['username']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="6">
                <div class="form-text">Leave blank to keep current password. Password must be at least 6 characters.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Position</label>
                <select name="position" class="form-select" required>
                    <?php foreach ($positions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $staff['position'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact Information</label>
                <input type="text" name="contact_info" class="form-control" value="<?php echo htmlspecialchars($staff['contact_info']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Picture</label>
                <input type="file" name="profile_picture" class="form-control" accept="image/*">
                <?php if (!empty($staff['profile_picture'])): ?>
                    <small class="text-muted">Current: <?php echo htmlspecialchars($staff['profile_picture']); ?></small>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="active" class="form-select" required>
                    <option value="1" <?php echo $staff['active_text'] === 'true' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $staff['active_text'] === 'false' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">National ID</label>
                <input type="text" class="form-control" name="national_id" value="<?php echo htmlspecialchars($staff['national_id'] ?? ''); ?>" required pattern="\d{8}" maxlength="8" title="Exactly 8 digits" />
                <div class="form-text">Exactly 8 digits</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of Employment</label>
                <input type="date" class="form-control" name="date_of_employment" value="<?php echo htmlspecialchars($staff['date_of_employment'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>" />
                <div class="form-text">Date the staff member started employment (cannot be in the future)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Work Permit Number <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control" name="work_permit_number" value="<?php echo htmlspecialchars($staff['work_permit_number'] ?? ''); ?>" maxlength="10" pattern="[A-Za-z0-9]{1,10}" title="1-10 alphanumeric characters" />
                <div class="form-text">1-10 alphanumeric characters (if applicable)</div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="staff.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var employmentInput = document.querySelector('input[name="date_of_employment"]');
    if (employmentInput) {
        employmentInput.max = new Date().toISOString().split('T')[0];
    }
});
</script> 