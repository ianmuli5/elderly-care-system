<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a family member
if (!isLoggedIn() || !hasRole('family')) {
    header("Location: ../index.php");
    exit;
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $feedback_id = intval($_POST['feedback_id']);
    if ($_POST['action'] === 'delete') {
        $query = "DELETE FROM feedback WHERE feedback_id = $1 AND user_id = $2";
        pg_query_params($db_connection, $query, array($feedback_id, $_SESSION['user_id']));
    } elseif ($_POST['action'] === 'edit') {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $public = isset($_POST['public']) ? 'true' : 'false';
        $errors = [];
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Please select a valid rating between 1 and 5.";
        }
        
        // Validate comment
        if (empty($comment)) {
            $errors[] = "Please provide your feedback comment.";
        } elseif (is_numeric($comment)) {
            $errors[] = "Comment cannot be just numbers. Please provide meaningful feedback.";
        } elseif (strlen($comment) < 10) {
            $errors[] = "Comment must be at least 10 characters long.";
        }
        
        if (empty($errors)) {
            $query = "UPDATE feedback SET rating = $1, comment = $2, public = $3 WHERE feedback_id = $4 AND user_id = $5";
            pg_query_params($db_connection, $query, array($rating, $comment, $public, $feedback_id, $_SESSION['user_id']));
        }
    }
    // header("Location: feedback.php");
    // exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $public = isset($_POST['public']) ? 'true' : 'false';
    $user_id = $_SESSION['user_id'];
    
    $errors = [];
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a valid rating between 1 and 5.";
    }
    
    // Validate comment
    if (empty($comment)) {
        $errors[] = "Please provide your feedback comment.";
    } elseif (is_numeric($comment)) {
        $errors[] = "Comment cannot be just numbers. Please provide meaningful feedback.";
    } elseif (strlen($comment) < 10) {
        $errors[] = "Comment must be at least 10 characters long.";
    }
    
    // If no errors, proceed with submission
    if (empty($errors)) {
        $query = "INSERT INTO feedback (user_id, rating, comment, public) VALUES ($1, $2, $3, $4)";
        $result = pg_query_params($db_connection, $query, array($user_id, $rating, $comment, $public));
        
        if ($result) {
            $success_message = "Thank you for your feedback!";
        } else {
            $error_message = "Failed to submit feedback. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get user's previous feedback
$user_id = $_SESSION['user_id'];
$user_feedback_query = "SELECT f.*, u.username, 
                       COALESCE(f.response_status, 'pending') as response_status,
                       f.response_message,
                       f.responded_at
                       FROM feedback f 
                       JOIN users u ON f.user_id = u.user_id 
                       WHERE f.user_id = $1 
                       ORDER BY f.created_at DESC";
$user_feedback_result = pg_query_params($db_connection, $user_feedback_query, array($user_id));

// Get public feedback from other users
$public_feedback_query = "SELECT f.*, u.username, 
                        COALESCE(f.response_status, 'pending') as response_status,
                        f.response_message,
                        f.responded_at
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
                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5">★</label>
                                <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" required 
                                      oninput="validateComment(this)"></textarea>
                            <div class="form-text">Must be at least 10 characters and cannot be just numbers</div>
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
                                    <div class="display-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></small>
                                        <div class="btn-group ms-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editFeedback<?php echo $feedback['feedback_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" action="" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 mb-1"><?php echo htmlspecialchars($feedback['comment']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php echo ($feedback['public'] === 't' || $feedback['public'] === true) ? 'Public' : 'Private'; ?>
                                    </small>
                                    <?php if ($feedback['response_status'] === 'responded'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#responseModal<?php echo $feedback['feedback_id']; ?>">
                                            View Response
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Edit Feedback Modal -->
                            <div class="modal fade" id="editFeedback<?php echo $feedback['feedback_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Feedback</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="rating" value="<?php echo $feedback['rating']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Rating</label>
                                                    <div class="rating">
                                                        <input type="radio" id="star5<?php echo $feedback['feedback_id']; ?>" name="rating<?php echo $feedback['feedback_id']; ?>" value="5" <?php echo $feedback['rating'] == 5 ? 'checked' : ''; ?>><label for="star5<?php echo $feedback['feedback_id']; ?>">★</label>
                                                        <input type="radio" id="star4<?php echo $feedback['feedback_id']; ?>" name="rating<?php echo $feedback['feedback_id']; ?>" value="4" <?php echo $feedback['rating'] == 4 ? 'checked' : ''; ?>><label for="star4<?php echo $feedback['feedback_id']; ?>">★</label>
                                                        <input type="radio" id="star3<?php echo $feedback['feedback_id']; ?>" name="rating<?php echo $feedback['feedback_id']; ?>" value="3" <?php echo $feedback['rating'] == 3 ? 'checked' : ''; ?>><label for="star3<?php echo $feedback['feedback_id']; ?>">★</label>
                                                        <input type="radio" id="star2<?php echo $feedback['feedback_id']; ?>" name="rating<?php echo $feedback['feedback_id']; ?>" value="2" <?php echo $feedback['rating'] == 2 ? 'checked' : ''; ?>><label for="star2<?php echo $feedback['feedback_id']; ?>">★</label>
                                                        <input type="radio" id="star1<?php echo $feedback['feedback_id']; ?>" name="rating<?php echo $feedback['feedback_id']; ?>" value="1" <?php echo $feedback['rating'] == 1 ? 'checked' : ''; ?>><label for="star1<?php echo $feedback['feedback_id']; ?>">★</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="editComment<?php echo $feedback['feedback_id']; ?>" class="form-label">Your Feedback</label>
                                                    <textarea class="form-control" id="editComment<?php echo $feedback['feedback_id']; ?>" 
                                                              name="comment" rows="4" required 
                                                              oninput="validateComment(this)"><?php echo htmlspecialchars($feedback['comment']); ?></textarea>
                                                    <div class="form-text">Must be at least 10 characters and cannot be just numbers</div>
                                                </div>
                                                
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="editPublic<?php echo $feedback['feedback_id']; ?>" name="public" <?php echo ($feedback['public'] === 't' || $feedback['public'] === true) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="editPublic<?php echo $feedback['feedback_id']; ?>">
                                                        Make this feedback public
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Response Modal -->
                            <?php if ($feedback['response_status'] === 'responded'): ?>
                                <div class="modal fade" id="responseModal<?php echo $feedback['feedback_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Admin Response</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['response_message'])); ?></p>
                                                <small class="text-muted d-block mt-2">
                                                    Responded on <?php echo date('M d, Y H:i', strtotime($feedback['responded_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    // Mark response as seen when modal is shown
                                    document.getElementById('responseModal<?php echo $feedback['feedback_id']; ?>').addEventListener('show.bs.modal', function () {
                                        fetch('mark_response_seen.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: 'feedback_id=<?php echo $feedback['feedback_id']; ?>'
                                        });
                                    });
                                </script>
                            <?php endif; ?>
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
                                        <div class="display-rating mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $feedback['rating'] ? 'filled' : ''; ?>">★</span>
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
    justify-content: center;
    align-items: center;
}
.rating input {
    display: none;
}
.rating label {
    font-size: 30px;
    color: #ddd;
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s;
}
.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label {
    color: #ffd700;
}

.display-rating {
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: center;
}

.display-rating .star {
    font-size: 20px;
    color: #ddd;
    padding: 0 2px;
}
    
.feedback-item {
    background-color: #f8f9fc;
}
.star.filled {
    color: #ffd700;
}
</style>

<script>
function validateComment(input) {
    const comment = input.value.trim();
    const isNumeric = !isNaN(comment) && comment !== '';
    const isTooShort = comment.length > 0 && comment.length < 10;
    
    if (isNumeric || isTooShort) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (comment.length >= 10) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

// Validate feedback form before submission
document.addEventListener('DOMContentLoaded', function() {
    const feedbackForm = document.querySelector('form[method="post"]');
    if (feedbackForm && !feedbackForm.querySelector('input[name="action"]')) {
        feedbackForm.addEventListener('submit', function(e) {
            const comment = document.querySelector('#comment').value.trim();
            const rating = document.querySelector('input[name="rating"]:checked');
            
            let hasErrors = false;
            
            if (!rating) {
                alert('Please select a rating.');
                hasErrors = true;
            }
            
            if (!isNaN(comment) && comment !== '') {
                alert('Comment cannot be just numbers. Please provide meaningful feedback.');
                hasErrors = true;
            }
            
            if (comment.length < 10) {
                alert('Comment must be at least 10 characters long.');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
            }
        });
    }
    
    // Validate edit feedback forms
    const editForms = document.querySelectorAll('form[method="post"]');
    editForms.forEach(form => {
        if (form.querySelector('input[name="action"][value="edit"]')) {
            form.addEventListener('submit', function(e) {
                const comment = this.querySelector('textarea[name="comment"]').value.trim();
                const feedbackId = this.querySelector('input[name="feedback_id"]').value;
                const checked = this.querySelector('input[type="radio"][name="rating' + feedbackId + '"]:checked');
                let hasErrors = false;
                if (!checked) {
                    alert('Please select a rating.');
                    hasErrors = true;
                } else {
                    this.querySelector('input[type="hidden"][name="rating"]').value = checked.value;
                }
                if (!isNaN(comment) && comment !== '') {
                    alert('Comment cannot be just numbers. Please provide meaningful feedback.');
                    hasErrors = true;
                }
                if (comment.length < 10) {
                    alert('Comment must be at least 10 characters long.');
                    hasErrors = true;
                }
                if (hasErrors) {
                    e.preventDefault();
                }
            });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 