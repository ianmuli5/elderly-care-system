<?php
require_once '../includes/config.php';

// Handle resident deletion first, before any output
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $resident_id = $_GET['delete'];
    $delete_query = "DELETE FROM residents WHERE resident_id = $1";
    pg_query_params($db_connection, $delete_query, array($resident_id));
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
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResidentModal">
            <i class="fas fa-plus"></i> Add New Resident
        </button>
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
                                        <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#viewResidentModal<?php echo $resident['resident_id']; ?>">
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
                                    <td><?php echo htmlspecialchars($resident['family_member'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($resident['caregiver'] ?? 'None'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editResidentModal<?php echo $resident['resident_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $resident['resident_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this resident?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Edit Resident Modal -->
                                <div class="modal fade" id="editResidentModal<?php echo $resident['resident_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Resident</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="process_resident.php" method="POST" enctype="multipart/form-data">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="resident_id" value="<?php echo $resident['resident_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Profile Picture</label>
                                                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                                        <?php if (!empty($resident['profile_picture'])): ?>
                                                            <small class="text-muted">Current: <?php echo htmlspecialchars($resident['profile_picture']); ?></small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">First Name</label>
                                                        <input type="text" class="form-control" name="first_name" 
                                                               value="<?php echo htmlspecialchars($resident['first_name']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" name="last_name" 
                                                               value="<?php echo htmlspecialchars($resident['last_name']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Date of Birth</label>
                                                        <input type="date" class="form-control" name="date_of_birth" 
                                                               value="<?php echo $resident['date_of_birth']; ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Medical Condition</label>
                                                        <textarea class="form-control" name="medical_condition" rows="3"><?php echo htmlspecialchars($resident['medical_condition'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Interests</label>
                                                        <textarea class="form-control" name="interests" rows="3"><?php echo htmlspecialchars($resident['interests'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="active" <?php echo $resident['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="waitlist" <?php echo $resident['status'] === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                                                            <option value="former" <?php echo $resident['status'] === 'former' ? 'selected' : ''; ?>>Former</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Family Member</label>
                                                        <select class="form-select" name="family_member_id">
                                                            <option value="">None</option>
                                                            <?php 
                                                            pg_result_seek($family_result, 0);
                                                            while ($family = pg_fetch_assoc($family_result)): 
                                                            ?>
                                                                <option value="<?php echo $family['user_id']; ?>" 
                                                                        <?php echo $resident['family_member_id'] == $family['user_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($family['username']); ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Caregiver</label>
                                                        <select class="form-select" name="caregiver_id">
                                                            <option value="">None</option>
                                                            <?php 
                                                            pg_result_seek($staff_result, 0);
                                                            while ($staff = pg_fetch_assoc($staff_result)): 
                                                            ?>
                                                                <option value="<?php echo $staff['staff_id']; ?>" 
                                                                        <?php echo $resident['caregiver_id'] == $staff['staff_id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- View Resident Modal -->
                                <div class="modal fade" id="viewResidentModal<?php echo $resident['resident_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Resident Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-3">
                                                        <?php if (!empty($resident['profile_picture'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($resident['profile_picture']); ?>" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 150px; height: 150px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <img src="../assets/images/default-profile.png" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 150px; height: 150px; object-fit: cover;">
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
                                                        <p>
                                                            <strong>Status:</strong> 
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
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h5>Personal Information</h5>
                                                        <p><strong>Date of Birth:</strong> <?php echo date('F d, Y', strtotime($resident['date_of_birth'])); ?></p>
                                                        <p><strong>Medical Condition:</strong> <?php echo nl2br(htmlspecialchars($resident['medical_condition'] ?? 'None specified')); ?></p>
                                                        <p><strong>Interests:</strong> <?php echo nl2br(htmlspecialchars($resident['interests'] ?? 'None specified')); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h5>Assigned Personnel</h5>
                                                        <p><strong>Family Member:</strong> <?php echo htmlspecialchars($resident['family_member'] ?? 'None assigned'); ?></p>
                                                        <p><strong>Caregiver:</strong> <?php echo htmlspecialchars($resident['caregiver'] ?? 'None assigned'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editResidentModal<?php echo $resident['resident_id']; ?>"
                                                        data-bs-dismiss="modal">
                                                    <i class="fas fa-edit"></i> Edit Resident
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

<!-- Add Resident Modal -->
<div class="modal fade" id="addResidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Resident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_resident.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="date_of_birth" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Medical Condition</label>
                        <textarea class="form-control" name="medical_condition" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Interests</label>
                        <textarea class="form-control" name="interests" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="active">Active</option>
                            <option value="waitlist">Waitlist</option>
                            <option value="former">Former</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Family Member</label>
                        <select class="form-select" name="family_member_id">
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

                    <div class="mb-3">
                        <label class="form-label">Caregiver</label>
                        <select class="form-select" name="caregiver_id">
                            <option value="">None</option>
                            <?php 
                            pg_result_seek($staff_result, 0);
                            while ($staff = pg_fetch_assoc($staff_result)): 
                            ?>
                                <option value="<?php echo $staff['staff_id']; ?>">
                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Resident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 