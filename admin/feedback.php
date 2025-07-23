<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !hasRole('admin')) {
    header("Location: ../index.php");
    exit;
}

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $feedback_id = intval($_POST['feedback_id']);
    
    if ($_POST['action'] === 'hide') {
        // Hide inappropriate feedback
        $query = "UPDATE feedback SET public = false WHERE feedback_id = $1";
        pg_query_params($db_connection, $query, array($feedback_id));
    } elseif ($_POST['action'] === 'delete') {
        // Delete inappropriate feedback
        $query = "DELETE FROM feedback WHERE feedback_id = $1";
        pg_query_params($db_connection, $query, array($feedback_id));
    } elseif ($_POST['action'] === 'respond') {
        // Send a message to the user about their feedback
        $user_id = intval($_POST['user_id']);
        $message = trim($_POST['response_message']);
        
        if (!empty($message)) {
            // Store response in feedback table only
            $query = "UPDATE feedback SET response_status = 'responded', response_message = $1, responded_at = CURRENT_TIMESTAMP WHERE feedback_id = $2";
            pg_query_params($db_connection, $query, array($message, $feedback_id));
        }
    }
    
    header("Location: feedback.php");
    exit;
}

// Get all feedback with user information
$feedback_query = "SELECT f.*, u.username, u.email, 
                  COALESCE(f.response_status, 'pending') as response_status,
                  f.response_message,
                  f.responded_at
                  FROM feedback f 
                  JOIN users u ON f.user_id = u.user_id 
                  ORDER BY f.created_at DESC";
$feedback_result = pg_query($db_connection, $feedback_query);

// Get feedback statistics
$stats_query = "SELECT 
                COUNT(*) as total_feedback,
                AVG(rating) as avg_rating,
                COUNT(*) FILTER (WHERE public = true) as public_feedback,
                COUNT(*) FILTER (WHERE rating >= 4) as positive_feedback,
                COUNT(*) FILTER (WHERE rating <= 2) as negative_feedback
                FROM feedback";
$stats_result = pg_query($db_connection, $stats_query);
$stats = pg_fetch_assoc($stats_result);

// Fix for deprecated number_format(null)
$avg_rating = $stats['avg_rating'] !== null ? $stats['avg_rating'] : 0;

// Include the header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Feedback Management</h1>
    </div>

    <!-- Feedback Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Feedback</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_feedback']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Average Rating</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($avg_rating, 1); ?> / 5
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Public Feedback</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['public_feedback']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-eye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Negative Feedback</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['negative_feedback']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">User Feedback</h6>
        </div>
        <div class="card-body">
            <?php if (pg_num_rows($feedback_result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Visibility</th>
                                <th>Response Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($feedback = pg_fetch_assoc($feedback_result)): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($feedback['username']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($feedback['email']); ?></small>
                                    </td>
                                    <td>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($feedback['comment']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($feedback['public'] === 't' || $feedback['public'] === true) ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ($feedback['public'] === 't' || $feedback['public'] === true) ? 'Public' : 'Private'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($feedback['response_status'] === 'responded'): ?>
                                            <span class="badge bg-success">Responded</span>
                                            <small class="d-block text-muted">
                                                <?php echo date('M d, Y H:i', strtotime($feedback['responded_at'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#respondModal<?php echo $feedback['feedback_id']; ?>">
                                            Respond
                                        </button>
                                        <?php if ($feedback['public']): ?>
                                            <form method="post" action="" class="d-inline">
                                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                                <input type="hidden" name="action" value="hide">
                                                <button type="submit" class="btn btn-sm btn-warning">Hide</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Respond Modal -->
                                <div class="modal fade" id="respondModal<?php echo $feedback['feedback_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Respond to Feedback</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $feedback['user_id']; ?>">
                                                    <input type="hidden" name="action" value="respond">
                                                    
                                                    <div class="mb-3">
                                                        <label for="responseMessage<?php echo $feedback['feedback_id']; ?>" 
                                                               class="form-label">Your Response</label>
                                                        <textarea class="form-control" 
                                                                  id="responseMessage<?php echo $feedback['feedback_id']; ?>" 
                                                                  name="response_message" rows="4" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Send Response</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No feedback available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .rating {
        display: flex;
        gap: 2px;
    }
    
    .star {
        font-size: 20px;
        color: #ddd;
    }
    
    .star.filled {
        color: #ffd700;
    }
    
    .border-left-primary {
        border-left: 4px solid #4e73df !important;
    }
    
    .border-left-success {
        border-left: 4px solid #1cc88a !important;
    }
    
    .border-left-info {
        border-left: 4px solid #36b9cc !important;
    }
    
    .border-left-warning {
        border-left: 4px solid #f6c23e !important;
    }
</style>

<?php require_once 'includes/footer.php'; ?> 