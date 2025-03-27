<?php
require_once 'includes/header.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get dashboard stats
$residents_query = "SELECT COUNT(*) FROM residents";
$residents_result = pg_query($db_connection, $residents_query);
$residents_count = pg_fetch_result($residents_result, 0, 0);

$staff_query = "SELECT COUNT(*) FROM staff";
$staff_result = pg_query($db_connection, $staff_query);
$staff_count = pg_fetch_result($staff_result, 0, 0);

$alerts_query = "SELECT COUNT(*) FROM medical_alerts WHERE resolved = FALSE";
$alerts_result = pg_query($db_connection, $alerts_query);
$alerts_count = pg_fetch_result($alerts_result, 0, 0);
?>

<div class="container-fluid">
    <!-- Dashboard cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card stat-card-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Residents</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $residents_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card stat-card-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Staff Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $staff_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-md fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card stat-card-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Active Alerts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $alerts_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent alerts section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Recent Alerts</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Alert Level</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $alerts_query = "SELECT a.*, r.first_name, r.last_name 
                                        FROM medical_alerts a 
                                        JOIN residents r ON a.resident_id = r.resident_id 
                                        WHERE a.resolved = FALSE 
                                        ORDER BY a.created_at DESC 
                                        LIMIT 5";
                        $alerts_result = pg_query($db_connection, $alerts_query);
                        
                        if (pg_num_rows($alerts_result) > 0) {
                            while ($alert = pg_fetch_assoc($alerts_result)) {
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
                                ?>
                                <tr class="table-<?php echo $alert_class; ?>">
                                    <td><?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($alert['alert_level'])); ?></td>
                                    <td><?php echo htmlspecialchars($alert['description']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                                    <td>
                                        <a href="alerts.php?id=<?php echo $alert['alert_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php 
                            }
                        } else {
                            echo '<tr><td colspan="5" class="text-center">No alerts found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Upcoming Events section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Events</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $events_query = "SELECT * FROM events 
                                        WHERE event_date >= CURRENT_TIMESTAMP 
                                        ORDER BY event_date ASC 
                                        LIMIT 5";
                        $events_result = pg_query($db_connection, $events_query);
                        
                        if (pg_num_rows($events_result) > 0) {
                            while ($event = pg_fetch_assoc($events_result)) {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo htmlspecialchars($event['description']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="events.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                            <?php 
                            }
                        } else {
                            echo '<tr><td colspan="5" class="text-center">No upcoming events found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent residents section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Recent Residents</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Family Member</th>
                            <th>Caregiver</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $residents_query = "SELECT r.*, 
                                            u.username as family_member, 
                                            CONCAT(s.first_name, ' ', s.last_name) as caregiver
                                         FROM residents r
                                         LEFT JOIN users u ON r.family_member_id = u.user_id
                                         LEFT JOIN staff s ON r.caregiver_id = s.staff_id
                                         ORDER BY r.resident_id DESC
                                         LIMIT 5";
                        $residents_result = pg_query($db_connection, $residents_query);
                        
                        if (pg_num_rows($residents_result) > 0) {
                            while ($resident = pg_fetch_assoc($residents_result)) {
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></td>
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
                                        <a href="residents.php?id=<?php echo $resident['resident_id']; ?>" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            <?php 
                            }
                        } else {
                            echo '<tr><td colspan="5" class="text-center">No residents found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 