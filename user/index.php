<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    header("Location: ../index.php");
    exit;
}

// Get user's relatives (residents)
$user_id = $_SESSION['user_id'];
$relatives_query = "SELECT * FROM residents WHERE family_member_id = $1";
$relatives_result = pg_query_params($db_connection, $relatives_query, array($user_id));

// Get active alerts for user's relatives
$alerts_query = "SELECT a.alert_id, a.alert_level, a.description, a.created_at, 
                        a.status, a.location, a.priority_level, a.category,
                        r.first_name, r.last_name 
                FROM medical_alerts a 
                JOIN residents r ON a.resident_id = r.resident_id 
                WHERE r.family_member_id = $1 
                AND a.resolved = FALSE 
                ORDER BY a.created_at DESC";
$alerts_result = pg_query_params($db_connection, $alerts_query, array($user_id));

// Get unread messages
$messages_query = "SELECT COUNT(*) FROM messages WHERE recipient_id = $1 AND read = FALSE";
$messages_result = pg_query_params($db_connection, $messages_query, array($user_id));
$unread_count = pg_fetch_result($messages_result, 0, 0);

// Include the header
require_once 'includes/header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 0.35rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .family-card {
        transition: transform 0.3s;
    }
    
    .family-card:hover {
        transform: translateY(-5px);
    }
    
    .alert-badge-red {
        background-color: #e74a3b;
    }
    
    .alert-badge-yellow {
        background-color: #f6c23e;
    }
    
    .alert-badge-blue {
        background-color: #4e73df;
    }
</style>

<!-- Family members section -->
<h4 class="mt-4 mb-3">Your Family Members in Our Care</h4>

<div class="row">
    <?php if (pg_num_rows($relatives_result) > 0): ?>
        <?php while ($relative = pg_fetch_assoc($relatives_result)): ?>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card family-card h-100">
                    <div class="card-body">
                        <div class="text-center">
                            <?php if (!empty($relative['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($relative['profile_picture']); ?>" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <img src="../assets/images/default-profile.png" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php endif; ?>
                            <h5 class="card-title"><?php echo htmlspecialchars($relative['first_name'] . ' ' . $relative['last_name']); ?></h5>
                            <p class="text-muted">
                                <?php 
                                $dob = new DateTime($relative['date_of_birth']);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                                echo $age . ' years old';
                                ?>
                            </p>
                            
                            <?php 
                            switch($relative['status']) {
                                case 'active':
                                    echo '<span class="badge bg-success">Active Resident</span>';
                                    break;
                                case 'waitlist':
                                    echo '<span class="badge bg-warning">On Waitlist</span>';
                                    break;
                                case 'former':
                                    echo '<span class="badge bg-secondary">Former Resident</span>';
                                    break;
                                default:
                                    echo htmlspecialchars($relative['status']);
                            }
                            ?>
                        </div>
                        
                        <hr>
                        
                        <!-- Get caregiver info -->
                        <?php if ($relative['caregiver_id']): 
                            $caregiver_query = "SELECT * FROM staff WHERE staff_id = $1";
                            $caregiver_result = pg_query_params($db_connection, $caregiver_query, array($relative['caregiver_id']));
                            
                            if ($caregiver_result && $caregiver = pg_fetch_assoc($caregiver_result)):
                        ?>
                            <p><strong>Caregiver:</strong> <?php echo htmlspecialchars($caregiver['first_name'] . ' ' . $caregiver['last_name']); ?></p>
                            <?php if (isset($caregiver['position']) && $caregiver['position']): ?>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($caregiver['position']); ?></p>
                            <?php endif; ?>
                            <?php if (isset($caregiver['contact_info']) && $caregiver['contact_info']): ?>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($caregiver['contact_info']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Caregiver information unavailable</p>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="resident.php?id=<?php echo $relative['resident_id']; ?>" class="btn btn-info">View Details</a>
                            <a href="messages.php?user=1" class="btn btn-outline-primary">Contact Admin</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                You don't have any family members registered with us yet. 
                Please contact the administration for more information.
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Alerts section -->
<div class="card shadow mb-4 mt-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Recent Medical Alerts</h6>
    </div>
    <div class="card-body">
        <?php if (pg_num_rows($alerts_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Alert Level</th>
                            <th>Priority</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($alert = pg_fetch_assoc($alerts_result)): 
                            $alert_class = '';
                            switch($alert['alert_level']) {
                                case 'red':
                                    $alert_class = 'alert-badge-red';
                                    break;
                                case 'yellow':
                                    $alert_class = 'alert-badge-yellow';
                                    break;
                                case 'blue':
                                    $alert_class = 'alert-badge-blue';
                                    break;
                            }

                            // Status badge class
                            $status_class = '';
                            switch($alert['status']) {
                                case 'pending':
                                    $status_class = 'bg-warning';
                                    break;
                                case 'in_progress':
                                    $status_class = 'bg-info';
                                    break;
                                case 'resolved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'escalated':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                            }

                            // Priority badge class
                            $priority_class = '';
                            switch($alert['priority_level']) {
                                case 'critical':
                                    $priority_class = 'bg-danger';
                                    break;
                                case 'high':
                                    $priority_class = 'bg-warning';
                                    break;
                                case 'medium':
                                    $priority_class = 'bg-info';
                                    break;
                                case 'low':
                                    $priority_class = 'bg-secondary';
                                    break;
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></td>
                                <td><span class="badge <?php echo $alert_class; ?>"><?php echo ucfirst($alert['alert_level']); ?></span></td>
                                <td><span class="badge <?php echo $priority_class; ?>"><?php echo ucfirst($alert['priority_level']); ?></span></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $alert['category'])); ?></td>
                                <td><?php echo htmlspecialchars($alert['location'] ?? 'Not specified'); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $alert['status'])); ?></span></td>
                                <td><?php echo htmlspecialchars($alert['description']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No active medical alerts at this time.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 