<?php
require_once '../includes/config.php';

// Handle alert deletion first, before any output
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $alert_id = $_GET['delete'];
    $delete_query = "DELETE FROM medical_alerts WHERE alert_id = $1";
    pg_query_params($db_connection, $delete_query, array($alert_id));
    header("Location: alerts.php");
    exit;
}

require_once 'includes/header.php';

// Get all alerts with resident and staff information
$alerts_query = "SELECT a.*, 
                    r.first_name as resident_first_name, 
                    r.last_name as resident_last_name,
                    s.first_name as staff_first_name,
                    s.last_name as staff_last_name,
                    s.staff_id,
                    rs.first_name as resolved_by_first_name,
                    rs.last_name as resolved_by_last_name
                FROM medical_alerts a
                JOIN residents r ON a.resident_id = r.resident_id
                LEFT JOIN staff s ON a.staff_id = s.staff_id
                LEFT JOIN staff rs ON a.resolved_by = rs.staff_id
                ORDER BY 
                    CASE a.priority_level
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    a.created_at DESC";
$alerts_result = pg_query($db_connection, $alerts_query);

if (!$alerts_result) {
    die("Error fetching alerts: " . pg_last_error($db_connection));
}

// Get all residents for dropdown
$residents_query = "SELECT resident_id, first_name, last_name FROM residents WHERE status = 'active' ORDER BY first_name, last_name";
$residents_result = pg_query($db_connection, $residents_query);

// Get all staff members for dropdown
$staff_query = "SELECT staff_id, first_name, last_name FROM staff WHERE active = true ORDER BY first_name, last_name";
$staff_result = pg_query($db_connection, $staff_query);
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
        <h1 class="h3 mb-0 text-gray-800">Medical Alerts</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAlertModal">
            <i class="fas fa-plus"></i> Add New Alert
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Alert Level</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Assigned Staff</th>
                            <th>Status</th>
                            <th>Response Required By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($alerts_result) > 0): ?>
                            <?php while ($alert = pg_fetch_assoc($alerts_result)): 
                                $alert_class = '';
                                switch ($alert['alert_level']) {
                                    case 'red':
                                        $alert_class = 'danger';
                                        break;
                                    case 'yellow':
                                        $alert_class = 'warning';
                                        break;
                                    case 'blue':
                                        $alert_class = 'info';
                                        break;
                                }

                                $priority_class = '';
                                switch ($alert['priority_level']) {
                                    case 'critical':
                                        $priority_class = 'danger';
                                        break;
                                    case 'high':
                                        $priority_class = 'warning';
                                        break;
                                    case 'medium':
                                        $priority_class = 'info';
                                        break;
                                    case 'low':
                                        $priority_class = 'secondary';
                                        break;
                                }

                                $status_class = '';
                                switch ($alert['status']) {
                                    case 'pending':
                                        $status_class = 'danger';
                                        break;
                                    case 'in_progress':
                                        $status_class = 'warning';
                                        break;
                                    case 'awaiting_review':
                                        $status_class = 'info';
                                        break;
                                    case 'resolved':
                                        $status_class = 'success';
                                        break;
                                    case 'escalated':
                                        $status_class = 'danger';
                                        break;
                                    case 'follow_up':
                                        $status_class = 'warning';
                                        break;
                                }
                            ?>
                                <tr class="table-<?php echo $alert_class; ?>">
                                    <td><?php echo htmlspecialchars($alert['resident_first_name'] . ' ' . $alert['resident_last_name']); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($alert['category']))); ?></td>
                                    <td><span class="badge bg-<?php echo $priority_class; ?>"><?php echo ucfirst(htmlspecialchars($alert['priority_level'])); ?></span></td>
                                    <td><span class="badge bg-<?php echo $alert_class; ?>"><?php echo ucfirst(htmlspecialchars($alert['alert_level'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($alert['description']); ?></td>
                                    <td><?php echo htmlspecialchars($alert['location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($alert['staff_first_name'] . ' ' . $alert['staff_last_name'] ?? 'Unassigned'); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($alert['status']))); ?></span></td>
                                    <td><?php echo $alert['response_required_by'] ? date('M d, Y H:i', strtotime($alert['response_required_by'])) : 'N/A'; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewAlertModal<?php echo $alert['alert_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editAlertModal<?php echo $alert['alert_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="alerts.php?delete=<?php echo $alert['alert_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this alert?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- View Alert Modal -->
                                <div class="modal fade" id="viewAlertModal<?php echo $alert['alert_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Alert Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Resident</label>
                                                    <p><?php echo htmlspecialchars($alert['resident_first_name'] . ' ' . $alert['resident_last_name']); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Category</label>
                                                    <p><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($alert['category']))); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Priority Level</label>
                                                    <p><span class="badge bg-<?php echo $priority_class; ?>"><?php echo ucfirst(htmlspecialchars($alert['priority_level'])); ?></span></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Alert Level</label>
                                                    <p><span class="badge bg-<?php echo $alert_class; ?>"><?php echo ucfirst(htmlspecialchars($alert['alert_level'])); ?></span></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <p><?php echo nl2br(htmlspecialchars($alert['description'])); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Location</label>
                                                    <p><?php echo htmlspecialchars($alert['location'] ?? 'N/A'); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Assigned Staff</label>
                                                    <p><?php echo htmlspecialchars($alert['staff_first_name'] . ' ' . $alert['staff_last_name'] ?? 'Unassigned'); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <p><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($alert['status']))); ?></span></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Response Required By</label>
                                                    <p><?php echo $alert['response_required_by'] ? date('M d, Y H:i', strtotime($alert['response_required_by'])) : 'N/A'; ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Created At</label>
                                                    <p><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></p>
                                                </div>
                                                <?php if ($alert['resolved']): ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">Resolved By</label>
                                                        <p><?php echo htmlspecialchars($alert['resolved_by_first_name'] . ' ' . $alert['resolved_by_last_name']); ?></p>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Resolved At</label>
                                                        <p><?php echo date('M d, Y H:i', strtotime($alert['resolved_at'])); ?></p>
                                                    </div>
                                                    <?php if ($alert['resolution_notes']): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Resolution Notes</label>
                                                            <p><?php echo nl2br(htmlspecialchars($alert['resolution_notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editAlertModal<?php echo $alert['alert_id']; ?>" data-bs-dismiss="modal">
                                                    Edit Alert
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Alert Modal -->
                                <div class="modal fade" id="editAlertModal<?php echo $alert['alert_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Alert</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="process_alert.php" method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="alert_id" value="<?php echo $alert['alert_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Resident</label>
                                                        <select name="resident_id" class="form-select" required>
                                                            <?php 
                                                            pg_result_seek($residents_result, 0);
                                                            while ($resident = pg_fetch_assoc($residents_result)): 
                                                            ?>
                                                                <option value="<?php echo $resident['resident_id']; ?>" <?php echo ($resident['resident_id'] == $alert['resident_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Alert Level</label>
                                                        <select name="alert_level" class="form-select" required>
                                                            <option value="red" <?php echo ($alert['alert_level'] == 'red') ? 'selected' : ''; ?>>Red</option>
                                                            <option value="yellow" <?php echo ($alert['alert_level'] == 'yellow') ? 'selected' : ''; ?>>Yellow</option>
                                                            <option value="blue" <?php echo ($alert['alert_level'] == 'blue') ? 'selected' : ''; ?>>Blue</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Priority Level</label>
                                                        <select name="priority_level" class="form-select" required>
                                                            <option value="critical" <?php echo ($alert['priority_level'] == 'critical') ? 'selected' : ''; ?>>Critical (Immediate Response Required)</option>
                                                            <option value="high" <?php echo ($alert['priority_level'] == 'high') ? 'selected' : ''; ?>>High (Response within 15 minutes)</option>
                                                            <option value="medium" <?php echo ($alert['priority_level'] == 'medium') ? 'selected' : ''; ?>>Medium (Response within 1 hour)</option>
                                                            <option value="low" <?php echo ($alert['priority_level'] == 'low') ? 'selected' : ''; ?>>Low (Response within 4 hours)</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select name="category" class="form-select" required>
                                                            <option value="medical_emergency" <?php echo ($alert['category'] == 'medical_emergency') ? 'selected' : ''; ?>>Medical Emergency</option>
                                                            <option value="medication" <?php echo ($alert['category'] == 'medication') ? 'selected' : ''; ?>>Medication</option>
                                                            <option value="fall" <?php echo ($alert['category'] == 'fall') ? 'selected' : ''; ?>>Fall Detection</option>
                                                            <option value="behavioral" <?php echo ($alert['category'] == 'behavioral') ? 'selected' : ''; ?>>Behavioral Changes</option>
                                                            <option value="dietary" <?php echo ($alert['category'] == 'dietary') ? 'selected' : ''; ?>>Dietary Concerns</option>
                                                            <option value="mobility" <?php echo ($alert['category'] == 'mobility') ? 'selected' : ''; ?>>Mobility Issues</option>
                                                            <option value="sleep" <?php echo ($alert['category'] == 'sleep') ? 'selected' : ''; ?>>Sleep Pattern Changes</option>
                                                            <option value="vitals" <?php echo ($alert['category'] == 'vitals') ? 'selected' : ''; ?>>Vital Signs Monitoring</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($alert['description']); ?></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Location</label>
                                                        <input type="text" name="location" class="form-control" placeholder="Where was the alert triggered?" value="<?php echo htmlspecialchars($alert['location'] ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Response Required By</label>
                                                        <input type="datetime-local" name="response_required_by" class="form-control" value="<?php echo $alert['response_required_by'] ? date('Y-m-d\TH:i', strtotime($alert['response_required_by'])) : ''; ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Assigned Staff</label>
                                                        <select name="staff_id" class="form-select">
                                                            <option value="">Unassigned</option>
                                                            <?php 
                                                            pg_result_seek($staff_result, 0);
                                                            while ($staff = pg_fetch_assoc($staff_result)): 
                                                            ?>
                                                                <option value="<?php echo $staff['staff_id']; ?>" <?php echo ($staff['staff_id'] == $alert['staff_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="pending" <?php echo ($alert['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="in_progress" <?php echo ($alert['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="awaiting_review" <?php echo ($alert['status'] == 'awaiting_review') ? 'selected' : ''; ?>>Awaiting Review</option>
                                                            <option value="resolved" <?php echo ($alert['status'] == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                                            <option value="escalated" <?php echo ($alert['status'] == 'escalated') ? 'selected' : ''; ?>>Escalated</option>
                                                            <option value="follow_up" <?php echo ($alert['status'] == 'follow_up') ? 'selected' : ''; ?>>Follow Up Required</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <?php if ($alert['status'] == 'resolved'): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Resolution Notes</label>
                                                            <textarea name="resolution_notes" class="form-control" rows="3"><?php echo htmlspecialchars($alert['resolution_notes'] ?? ''); ?></textarea>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Alert</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No alerts found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Alert Modal -->
<div class="modal fade" id="addAlertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_alert.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Resident</label>
                        <select class="form-select" name="resident_id" required>
                            <option value="">Select Resident</option>
                            <?php 
                            pg_result_seek($residents_result, 0);
                            while ($resident = pg_fetch_assoc($residents_result)): 
                            ?>
                                <option value="<?php echo $resident['resident_id']; ?>">
                                    <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select Category</option>
                            <option value="medical_emergency">Medical Emergency</option>
                            <option value="medication">Medication</option>
                            <option value="fall">Fall</option>
                            <option value="behavioral">Behavioral</option>
                            <option value="dietary">Dietary</option>
                            <option value="mobility">Mobility</option>
                            <option value="sleep">Sleep</option>
                            <option value="vitals">Vitals</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Priority Level</label>
                        <select class="form-select" name="priority_level" required>
                            <option value="">Select Priority</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alert Level</label>
                        <select class="form-select" name="alert_level" required>
                            <option value="">Select Alert Level</option>
                            <option value="red">Red</option>
                            <option value="yellow">Yellow</option>
                            <option value="blue">Blue</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assigned Staff</label>
                        <select class="form-select" name="staff_id">
                            <option value="">Unassigned</option>
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

                    <div class="mb-3">
                        <label class="form-label">Response Required By</label>
                        <input type="datetime-local" class="form-control" name="response_required_by">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Alert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 