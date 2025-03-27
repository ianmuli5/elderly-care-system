<?php
require_once '../includes/config.php';

// Handle staff deletion first, before any output
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $staff_id = $_GET['delete'];
    
    // Start transaction
    pg_query($db_connection, "BEGIN");
    
    try {
        // Get the user_id associated with this staff member
        $user_query = "SELECT user_id FROM staff WHERE staff_id = $1";
        $user_result = pg_query_params($db_connection, $user_query, array($staff_id));
        $user_row = pg_fetch_assoc($user_result);
        
        if ($user_row) {
            // First delete the staff record
            $delete_staff_query = "DELETE FROM staff WHERE staff_id = $1";
            if (!pg_query_params($db_connection, $delete_staff_query, array($staff_id))) {
                throw new Exception("Error deleting staff record");
            }
            
            // Then delete the user account
            $delete_user_query = "DELETE FROM users WHERE user_id = $1";
            if (!pg_query_params($db_connection, $delete_user_query, array($user_row['user_id']))) {
                throw new Exception("Error deleting user account");
            }
        }
        
        // Commit transaction
        pg_query($db_connection, "COMMIT");
        $_SESSION['success'] = "Staff member deleted successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        pg_query($db_connection, "ROLLBACK");
        $_SESSION['error'] = "Error deleting staff member: " . $e->getMessage();
    }
    
    header("Location: staff.php");
    exit;
}

require_once 'includes/header.php';

// Get all staff members with their user information
$staff_query = "SELECT s.*, u.email, u.username 
                FROM staff s 
                LEFT JOIN users u ON s.user_id = u.user_id 
                ORDER BY s.staff_id DESC";
$staff_result = pg_query($db_connection, $staff_query);

// Debug information
if (!$staff_result) {
    error_log("Error fetching staff: " . pg_last_error($db_connection));
    echo '<div class="alert alert-danger">Error loading staff members. Please check the error logs.</div>';
}

// Get staff positions for dropdown
$positions = array(
    'doctor' => 'Doctor',
    'nurse' => 'Nurse',
    'caregiver' => 'Caregiver',
    'cleaner' => 'Cleaner',
    'admin' => 'Administrator',
    'other' => 'Other'
);
?>

<div class="container-fluid">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Staff Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus"></i> Add New Staff Member
        </button>
    </div>

    <!-- Staff Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($staff_result) > 0): ?>
                            <?php while ($staff = pg_fetch_assoc($staff_result)): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (!empty($staff['profile_picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($staff['profile_picture']); ?>" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="../assets/img/default-avatar.png" 
                                                 class="rounded-circle" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#viewStaffModal<?php echo $staff['staff_id']; ?>">
                                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(htmlspecialchars($staff['position'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($staff['contact_info']); ?><br>
                                        <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php if ($staff['active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editStaffModal<?php echo $staff['staff_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $staff['staff_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this staff member?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Edit Staff Modal -->
                                <div class="modal fade" id="editStaffModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Staff Member</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="process_staff.php" method="POST" enctype="multipart/form-data">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Profile Picture</label>
                                                        <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                                        <?php if (!empty($staff['profile_picture'])): ?>
                                                            <small class="text-muted">Current: <?php echo htmlspecialchars($staff['profile_picture']); ?></small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">First Name</label>
                                                        <input type="text" class="form-control" name="first_name" 
                                                               value="<?php echo htmlspecialchars($staff['first_name']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" name="last_name" 
                                                               value="<?php echo htmlspecialchars($staff['last_name']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Position</label>
                                                        <select class="form-select" name="position" required>
                                                            <?php foreach ($positions as $value => $label): ?>
                                                                <option value="<?php echo $value; ?>" 
                                                                        <?php echo $staff['position'] === $value ? 'selected' : ''; ?>>
                                                                    <?php echo $label; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Contact Information</label>
                                                        <input type="text" class="form-control" name="contact_info" 
                                                               value="<?php echo htmlspecialchars($staff['contact_info']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" class="form-control" name="email" 
                                                               value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="active" required>
                                                            <option value="1" <?php echo $staff['active'] ? 'selected' : ''; ?>>Active</option>
                                                            <option value="0" <?php echo !$staff['active'] ? 'selected' : ''; ?>>Inactive</option>
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

                                <!-- View Staff Modal -->
                                <div class="modal fade" id="viewStaffModal<?php echo $staff['staff_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Staff Member Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-3">
                                                        <?php if (!empty($staff['profile_picture'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($staff['profile_picture']); ?>" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 150px; height: 150px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <img src="../assets/images/default-profile.png" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 150px; height: 150px; object-fit: cover;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <h4><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
                                                        <p>
                                                            <strong>Position:</strong> 
                                                            <span class="badge bg-info">
                                                                <?php echo ucfirst(htmlspecialchars($staff['position'])); ?>
                                                            </span>
                                                        </p>
                                                        <p>
                                                            <strong>Status:</strong> 
                                                            <?php if ($staff['active']): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Inactive</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h5>Contact Information</h5>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['contact_info']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h5>Assigned Residents</h5>
                                                        <?php
                                                        $residents_query = "SELECT first_name, last_name, status 
                                                                         FROM residents 
                                                                         WHERE caregiver_id = $1";
                                                        $residents_result = pg_query_params($db_connection, $residents_query, array($staff['staff_id']));
                                                        
                                                        if (pg_num_rows($residents_result) > 0) {
                                                            echo '<ul class="list-unstyled">';
                                                            while ($resident = pg_fetch_assoc($residents_result)) {
                                                                $status_class = '';
                                                                switch($resident['status']) {
                                                                    case 'active':
                                                                        $status_class = 'success';
                                                                        break;
                                                                    case 'waitlist':
                                                                        $status_class = 'warning';
                                                                        break;
                                                                    case 'former':
                                                                        $status_class = 'secondary';
                                                                        break;
                                                                }
                                                                echo '<li class="mb-2">';
                                                                echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']);
                                                                echo ' <span class="badge bg-' . $status_class . '">' . ucfirst($resident['status']) . '</span>';
                                                                echo '</li>';
                                                            }
                                                            echo '</ul>';
                                                        } else {
                                                            echo '<p class="text-muted">No residents assigned</p>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editStaffModal<?php echo $staff['staff_id']; ?>"
                                                        data-bs-dismiss="modal">
                                                    <i class="fas fa-edit"></i> Edit Staff Member
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No staff members found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_staff.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select" required>
                            <option value="">Select Position</option>
                            <option value="nurse">Nurse</option>
                            <option value="caregiver">Caregiver</option>
                            <option value="doctor">Doctor</option>
                            <option value="therapist">Therapist</option>
                            <option value="administrative">Administrative</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Information</label>
                        <input type="text" name="contact_info" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 