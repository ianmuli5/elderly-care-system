<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

if (!isset($_GET['resident_id']) || !is_numeric($_GET['resident_id'])) {
    echo '<div class="alert alert-danger">Invalid resident ID.</div>';
    exit;
}
$resident_id = $_GET['resident_id'];

// Fetch resident info
$query = "SELECT r.*, u.username as family_member, CONCAT(s.first_name, ' ', s.last_name) as caregiver
          FROM residents r
          LEFT JOIN users u ON r.family_member_id = u.user_id
          LEFT JOIN staff s ON r.caregiver_id = s.staff_id
          WHERE r.resident_id = $1";
$result = pg_query_params($db_connection, $query, [$resident_id]);
$resident = pg_fetch_assoc($result);
if (!$resident) {
    echo '<div class="alert alert-danger">Resident not found.</div>';
    exit;
}
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Resident Details</h2>
    <div class="mb-3">
        <a href="residents.php" class="btn btn-secondary">Back to List</a>
        <a href="edit_resident.php?resident_id=<?php echo $resident['resident_id']; ?>" class="btn btn-primary">Edit</a>
    </div>
    <div class="row">
        <div class="col-md-4 text-center mb-3">
            <?php if (!empty($resident['profile_picture'])): ?>
                <img src="../<?php echo htmlspecialchars($resident['profile_picture']); ?>" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
            <?php else: ?>
                <img src="../assets/images/default-profile.png" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
            <?php endif; ?>
        </div>
        <div class="col-md-8">
            <h4><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h4>
            <p class="text-muted">
                <?php 
                $dob = new DateTime($resident['date_of_birth']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
                echo $age . ' years old';
                ?>
            </p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($resident['status']); ?></p>
            <p><strong>Admission Date:</strong> <?php echo htmlspecialchars($resident['admission_date'] ?? ''); ?></p>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-6">
            <h5>Personal Information</h5>
            <p><strong>National ID:</strong> <?php echo htmlspecialchars($resident['national_id'] ?? ''); ?></p>
            <p><strong>Passport Number:</strong> <?php echo htmlspecialchars($resident['passport_number'] ?? ''); ?></p>
            <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($resident['date_of_birth'])); ?></p>
            <p><strong>Place of Birth:</strong> <?php echo htmlspecialchars($resident['place_of_birth'] ?? ''); ?></p>
            <p><strong>Previous Address:</strong> <?php echo nl2br(htmlspecialchars($resident['previous_address'] ?? '')); ?></p>
            <p><strong>Next of Kin Name:</strong> <?php echo htmlspecialchars($resident['next_of_kin_name'] ?? ''); ?></p>
            <p><strong>Next of Kin Contact:</strong> <?php echo htmlspecialchars($resident['next_of_kin_contact'] ?? ''); ?></p>
        </div>
        <div class="col-md-6">
            <h5>Medical & Social Information</h5>
            <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($resident['blood_type'] ?? ''); ?></p>
            <p><strong>Allergies:</strong> <?php echo nl2br(htmlspecialchars($resident['allergies'] ?? '')); ?></p>
            <p><strong>Medical Condition:</strong> <?php echo nl2br(htmlspecialchars($resident['medical_condition'] ?? 'None specified')); ?></p>
            <p><strong>Medical Insurance:</strong> <?php echo htmlspecialchars($resident['medical_insurance'] ?? ''); ?></p>
            <p><strong>Primary Doctor:</strong> <?php echo htmlspecialchars($resident['primary_doctor'] ?? ''); ?><?php if (($resident['primary_doctor'] ?? '') === 'Other') { echo ' - ' . htmlspecialchars($resident['primary_doctor_other'] ?? ''); } ?></p>
            <p><strong>Religion:</strong> <?php echo htmlspecialchars($resident['religion'] ?? ''); ?><?php if (($resident['religion'] ?? '') === 'Other') { echo ' - ' . htmlspecialchars($resident['religion_other'] ?? ''); } ?></p>
            <p><strong>Interests:</strong> <?php echo nl2br(htmlspecialchars($resident['interests'] ?? 'None specified')); ?></p>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <h5>Assigned Personnel</h5>
            <p><strong>Family Member:</strong> <?php echo htmlspecialchars($resident['family_member'] ?? 'None assigned'); ?></p>
            <p><strong>Caregiver:</strong> <?php echo htmlspecialchars($resident['caregiver'] ?? 'None assigned'); ?></p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?> 