<?php
require_once 'includes/header.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}

// Get assigned residents
$residents_query = "SELECT r.*, 
                          CASE WHEN ma.unresolved_count > 0 THEN TRUE ELSE FALSE END as has_alerts
                   FROM residents r
                   LEFT JOIN (
                       SELECT resident_id, COUNT(*) as unresolved_count
                       FROM medical_alerts
                       WHERE resolved = FALSE
                       GROUP BY resident_id
                   ) ma ON r.resident_id = ma.resident_id
                   WHERE r.caregiver_id = $1 AND r.status = 'active'
                   ORDER BY r.first_name, r.last_name";
$residents_result = pg_query_params($db_connection, $residents_query, array($_SESSION['staff_id']));

// Get all upcoming events (same as user view)
$events_query = "SELECT e.*, u.username as created_by_username 
                FROM events e 
                JOIN users u ON e.created_by = u.user_id 
                WHERE e.event_date >= CURRENT_TIMESTAMP 
                ORDER BY e.event_date ASC";
$events_result = pg_query($db_connection, $events_query);

// Get unread message count
$messages_query = "SELECT COUNT(*) as unread_count 
                  FROM messages 
                  WHERE recipient_id = (
                      SELECT user_id 
                      FROM staff 
                      WHERE staff_id = $1
                  ) 
                  AND read = FALSE";
$messages_result = pg_query_params($db_connection, $messages_query, array($_SESSION['staff_id']));
$messages_count = pg_fetch_assoc($messages_result)['unread_count'];

require_once 'includes/header.php';
?>

    <div class="container-fluid">
        <!-- Main content -->
        <main class="px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">Welcome, <?php echo htmlspecialchars($_SESSION['staff_name']); ?></h1>
            </div>

            <!-- Assigned Residents -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Your Assigned Residents</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (pg_num_rows($residents_result) > 0): ?>
                                    <?php while ($resident = pg_fetch_assoc($residents_result)): ?>
                                        <div class="col-md-4 mb-4">
                                            <div class="card resident-card h-100">
                                                <?php if ($resident['has_alerts']): ?>
                                                    <div class="alert-indicator"></div>
                                                <?php endif; ?>
                                                <div class="card-body text-center">
                                                    <?php if (!empty($resident['profile_picture'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($resident['profile_picture']); ?>" 
                                                         class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <img src="../assets/images/default-profile.png" 
                                                         class="rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                    <?php endif; ?>
                                                    
                                                    <h5><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h5>
                                                    <p class="text-muted mb-3">
                                                        <?php 
                                                        $dob = new DateTime($resident['date_of_birth']);
                                                        $now = new DateTime();
                                                        echo $now->diff($dob)->y . ' years old';
                                                        ?>
                                                    </p>
                                                    <a href="resident.php?id=<?php echo $resident['resident_id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-user me-2"></i>View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <p class="text-muted text-center">No residents assigned yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="mb-4">Upcoming Events</h3>
                    <div class="row">
                        <?php 
                        pg_result_seek($events_result, 0);
                        $has_upcoming = false;
                        while ($event = pg_fetch_assoc($events_result)):
                            $has_upcoming = true;
                        ?>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                        <div class="mt-3">
                                            <p class="mb-1">
                                                <i class="fas fa-calendar-alt text-primary"></i>
                                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-clock text-primary"></i>
                                                <?php echo date('H:i', strtotime($event['event_date'])); ?>
                                            </p>
                                            <?php if ($event['location']): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-map-marker-alt text-primary"></i>
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <small class="text-muted">
                                            Posted by <?php echo htmlspecialchars($event['created_by_username']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php if (!$has_upcoming): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    No upcoming events found.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
require_once 'includes/footer.php';
?>