<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    header("Location: ../index.php");
    exit;
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $public = isset($_POST['public']) ? true : false;
    $user_id = $_SESSION['user_id'];
    
    // Validate rating
    if ($rating >= 1 && $rating <= 5) {
        $query = "INSERT INTO feedback (user_id, rating, comment, public) VALUES ($1, $2, $3, $4)";
        $result = pg_query_params($db_connection, $query, array($user_id, $rating, $comment, $public));
        
        if ($result) {
            $success_message = "Thank you for your feedback!";
        } else {
            $error_message = "Failed to submit feedback. Please try again.";
        }
    } else {
        $error_message = "Please select a valid rating.";
    }
}

// Get user's previous feedback
$user_id = $_SESSION['user_id'];
$user_feedback_query = "SELECT * FROM feedback WHERE user_id = $1 ORDER BY created_at DESC";
$user_feedback_result = pg_query_params($db_connection, $user_feedback_query, array($user_id));

// Get public feedback from other users
$public_feedback_query = "SELECT f.*, u.username 
                        FROM feedback f 
                        JOIN users u ON f.user_id = u.user_id 
                        WHERE f.public = true 
                        AND f.user_id != $1 
                        ORDER BY f.created_at DESC";
$public_feedback_result = pg_query_params($db_connection, $public_feedback_query, array($user_id));

// Include the header
require_once 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Feedback Form -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Share Your Feedback</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                    <label for="star<?php echo $i; ?>">☆</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="public" name="public">
                            <label class="form-check-label" for="public">Make this feedback public</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Your Previous Feedback -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Your Previous Feedback</h6>
                </div>
                <div class="card-body">
                    <?php if (pg_num_rows($user_feedback_result) > 0): ?>
                        <?php while ($feedback = pg_fetch_assoc($user_feedback_result)): ?>
                            <div class="feedback-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">☆</span>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></small>
                                </div>
                                <p class="mt-2 mb-1"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                                <small class="text-muted">
                                    <?php echo $feedback['public'] ? 'Public' : 'Private'; ?>
                                </small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">You haven't submitted any feedback yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Public Feedback from Others -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">What Others Are Saying</h6>
                </div>
                <div class="card-body">
                    <?php if (pg_num_rows($public_feedback_result) > 0): ?>
                        <div class="row">
                            <?php while ($feedback = pg_fetch_assoc($public_feedback_result)): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="feedback-item p-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong><?php echo htmlspecialchars($feedback['username']); ?></strong>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></small>
                                        </div>
                                        <div class="rating mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">☆</span>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="mb-0"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No public feedback available yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }
    
    .rating input {
        display: none;
    }
    
    .rating label {
        font-size: 30px;
        color: #ddd;
        padding: 5px;
        cursor: pointer;
        transition: color 0.2s;
    }
    
    .rating label:hover,
    .rating label:hover ~ label,
    .rating input:checked ~ label {
        color: #ffd700;
    }
    
    .star {
        font-size: 20px;
        color: #ddd;
    }
    
    .star.filled {
        color: #ffd700;
    }
    
    .feedback-item {
        background-color: #f8f9fc;
    }
</style>

<?php require_once 'includes/footer.php'; ?> 