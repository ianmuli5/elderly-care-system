<?php
require_once '../includes/config.php';

// Handle resident deletion first, before any output
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $resident_id = $_GET['delete'];
    pg_query($db_connection, "BEGIN");
    try {
        // Get the family_member_id for this resident
        $family_query = "SELECT family_member_id FROM residents WHERE resident_id = $1";
        $family_result = pg_query_params($db_connection, $family_query, array($resident_id));
        $family_row = pg_fetch_assoc($family_result);
        $family_member_id = $family_row ? $family_row['family_member_id'] : null;

        // Delete the resident
        $delete_query = "DELETE FROM residents WHERE resident_id = $1";
        if (!pg_query_params($db_connection, $delete_query, array($resident_id))) {
            throw new Exception("Error deleting resident record");
        }

        // If there was a family member, check if they have any other residents
        if ($family_member_id) {
            $other_residents_query = "SELECT COUNT(*) FROM residents WHERE family_member_id = $1";
            $other_residents_result = pg_query_params($db_connection, $other_residents_query, array($family_member_id));
            $other_count = pg_fetch_result($other_residents_result, 0, 0);
            if ($other_count == 0) {
                // Delete the family member user account
                $delete_user_query = "DELETE FROM users WHERE user_id = $1";
                if (!pg_query_params($db_connection, $delete_user_query, array($family_member_id))) {
                    throw new Exception("Error deleting family member user account");
                }
            }
        }
        pg_query($db_connection, "COMMIT");
        $_SESSION['success'] = "Resident deleted successfully.";
    } catch (Exception $e) {
        pg_query($db_connection, "ROLLBACK");
        $_SESSION['error'] = "Error deleting resident: " . $e->getMessage();
    }
    header("Location: residents.php");
    exit;
}

require_once 'includes/header.php';

// Get all residents with their family members and caregivers
$residents_query = "SELECT r.*, 
                    u.username as family_member, 
                    CONCAT(s.first_name, ' ', s.last_name) as caregiver
                 FROM residents r
                 LEFT JOIN users u ON r.family_member_id = u.user_id
                 LEFT JOIN staff s ON r.caregiver_id = s.staff_id
                 ORDER BY r.resident_id DESC";
$residents_result = pg_query($db_connection, $residents_query);

// Get all family members for the dropdown
$family_query = "SELECT user_id, username FROM users WHERE role = 'family'";
$family_result = pg_query($db_connection, $family_query);

// Get all staff members for the dropdown
$staff_query = "SELECT staff_id, first_name, last_name FROM staff";
$staff_result = pg_query($db_connection, $staff_query);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Residents Management</h1>
        <div>
            <a href="add_resident.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Resident
            </a>
            <a href="add_family_member.php" class="btn btn-outline-secondary ms-2">Add New Family Member</a>
        </div>
    </div>

    <!-- Residents Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Admission Date</th>
                            <th>Family Member</th>
                            <th>Caregiver</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($residents_result) > 0): ?>
                            <?php while ($resident = pg_fetch_assoc($residents_result)): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (!empty($resident['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($resident['profile_picture']); ?>" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="../assets/images/default-profile.png" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_resident.php?resident_id=<?php echo $resident['resident_id']; ?>" class="text-primary">
                                            <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        switch($resident['status']) {
                                            case 'active':
                                                echo '<span class="badge bg-success">Active</span>';
                                                break;
                                            case 'waitlist':
                                                echo '<span class="badge bg-warning">Waitlist</span>';
                                                break;
                                            case 'former':
                                                echo '<span class="badge bg-secondary">Former</span>';
                                                break;
                                            default:
                                                echo htmlspecialchars($resident['status']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($resident['admission_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($resident['family_member'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($resident['caregiver'] ?? 'None'); ?></td>
                                    <td>
                                        <a href="edit_resident.php?resident_id=<?php echo $resident['resident_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $resident['resident_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this resident?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No residents found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Family Member Modal -->
<div class="modal fade" id="addFamilyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Family Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_family.php" method="POST" id="addFamilyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Family Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php 
        echo nl2br(htmlspecialchars($_SESSION['success']));
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php 
        echo nl2br(htmlspecialchars($_SESSION['error']));
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?> 