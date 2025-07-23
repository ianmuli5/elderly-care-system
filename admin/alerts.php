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
        <a href="add_alert.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Alert
        </a>
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
                                    case 'acknowledged':
                                        $status_class = 'primary';
                                        break;
                                    case 'closed':
                                        $status_class = 'secondary';
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
                                        <a href="edit_alert.php?alert_id=<?php echo $alert['alert_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="alerts.php?delete=<?php echo $alert['alert_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this alert?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
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

<?php require_once 'includes/footer.php'; ?> 