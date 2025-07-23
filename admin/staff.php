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
$staff_query = "SELECT s.*, u.email, u.username, s.active::text as active_text 
                FROM staff s 
                LEFT JOIN users u ON s.user_id = u.user_id 
                ORDER BY s.staff_id DESC";
$staff_result = pg_query($db_connection, $staff_query);

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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Staff Management</h1>
        <div>
            <a href="add_staff.php" class="btn btn-primary me-2">
                <i class="fas fa-plus"></i> Add New Staff Member
            </a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteStaffModal">
                <i class="fas fa-trash"></i> Delete Staff Member
            </button>
        </div>
    </div>

    <!-- Delete Staff Member Modal -->
    <div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-labelledby="deleteStaffModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteStaffModalLabel">Delete Staff Member</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="get" action="staff.php" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
            <div class="modal-body">
              <div class="mb-3">
                <label for="deleteStaffSelect" class="form-label">Select Staff Member</label>
                <select class="form-select" id="deleteStaffSelect" name="delete" required>
                  <option value="" disabled selected>Select staff member</option>
                  <?php 
                  // Re-query staff for dropdown (since main query may be exhausted)
                  $dropdown_result = pg_query($db_connection, "SELECT staff_id, first_name, last_name FROM staff ORDER BY first_name, last_name");
                  while ($row = pg_fetch_assoc($dropdown_result)): ?>
                    <option value="<?php echo $row['staff_id']; ?>">
                      <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete</button>
            </div>
          </form>
        </div>
      </div>
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
                            <th>National ID</th>
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
                                        <?php if ($staff['active_text'] === 'true'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($staff['national_id'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <a href="edit_staff.php?staff_id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $staff['staff_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this staff member?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

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
                                                            <img src="../assets/img/default-avatar.png" 
                                                                 class="rounded-circle mb-3" 
                                                                 style="width: 150px; height: 150px; object-fit: cover;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <h4><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
                                                        <p class="text-muted"><?php echo ucfirst(htmlspecialchars($staff['position'])); ?></p>
                                                
                                                <hr>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                                <p><strong>Contact:</strong><br>
                                                                <?php echo htmlspecialchars($staff['contact_info']); ?></p>
                                                                
                                                                <p><strong>Email:</strong><br>
                                                                <?php echo htmlspecialchars($staff['email'] ?? 'N/A'); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                                <p><strong>Status:</strong><br>
                                                                <?php if ($staff['active_text'] === 'true'): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                <?php endif; ?></p>
                                                                
                                                                <p><strong>Username:</strong><br>
                                                                <?php echo htmlspecialchars($staff['username'] ?? 'N/A'); ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>National ID:</strong><br>
                                                        <?php echo htmlspecialchars($staff['national_id'] ?? ''); ?></p>
                                                        <p><strong>Date of Employment:</strong><br>
                                                        <?php echo htmlspecialchars($staff['date_of_employment'] ?? ''); ?></p>
                                                        <p><strong>Work Permit Number:</strong><br>
                                                        <?php echo htmlspecialchars($staff['work_permit_number'] ?? ''); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="edit_staff.php?staff_id=<?php echo $staff['staff_id']; ?>" class="btn btn-primary">Edit</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No staff members found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 