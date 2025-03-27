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
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="fas fa-plus"></i> Add New Event
        </button>
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
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewEventModal<?php echo $event['event_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editEventModal<?php echo $event['event_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="events.php?delete=<?php echo $event['event_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- View Event Modal -->
                                <div class="modal fade" id="viewEventModal<?php echo $event['event_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Event Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <p><?php echo htmlspecialchars($event['title']); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Date & Time</label>
                                                    <p><?php echo date('M d, Y H:i', strtotime($event['event_date'])); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Location</label>
                                                    <p><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Created By</label>
                                                    <p><?php echo htmlspecialchars($event['created_by_username']); ?></p>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editEventModal<?php echo $event['event_id']; ?>" data-bs-dismiss="modal">
                                                    Edit Event
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Event Modal -->
                                <div class="modal fade" id="editEventModal<?php echo $event['event_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Event</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="process_event.php" method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Title</label>
                                                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Date & Time</label>
                                                        <input type="datetime-local" name="event_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Location</label>
                                                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Event</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_event.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date & Time</label>
                        <input type="datetime-local" name="event_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 