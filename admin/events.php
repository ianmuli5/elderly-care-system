<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = $_GET['delete'];
    $delete_query = "DELETE FROM events WHERE event_id = $1";
    pg_query_params($db_connection, $delete_query, array($event_id));
    header("Location: events.php");
    exit;
}

// Get all events with creator information
$events_query = "SELECT e.*, u.username as created_by_username 
                FROM events e 
                JOIN users u ON e.created_by = u.user_id 
                ORDER BY e.event_date DESC";
$events_result = pg_query($db_connection, $events_query);

if (!$events_result) {
    die("Error fetching events: " . pg_last_error($db_connection));
}
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
        <h1 class="h3 mb-0 text-gray-800">Events</h1>
        <a href="add_event.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Event
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($events_result) > 0): ?>
                            <?php while ($event = pg_fetch_assoc($events_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($event['description'])); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($event['created_by_username']); ?></td>
                                    <td>
                                        <a href="edit_event.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="events.php?delete=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No events found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 