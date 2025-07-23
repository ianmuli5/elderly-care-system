<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get all upcoming events
$events_query = "SELECT e.*, u.username as created_by_username 
                FROM events e 
                JOIN users u ON e.created_by = u.user_id 
                WHERE e.event_date >= CURRENT_TIMESTAMP 
                ORDER BY e.event_date ASC";
$events_result = pg_query($db_connection, $events_query);

if (!$events_result) {
    die("Error fetching events: " . pg_last_error($db_connection));
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Upcoming Events</h1>
    </div>

    <div class="row">
        <?php if (pg_num_rows($events_result) > 0): ?>
            <?php while ($event = pg_fetch_assoc($events_result)): ?>
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
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No upcoming events found.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 