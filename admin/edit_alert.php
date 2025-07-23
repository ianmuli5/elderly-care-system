<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get alert ID
$alert_id = isset($_GET['alert_id']) ? (int)$_GET['alert_id'] : 0;
if (!$alert_id) {
    header('Location: alerts.php');
    exit;
}

// Fetch alert data
$alert_query = "SELECT * FROM medical_alerts WHERE alert_id = $1";
$alert_result = pg_query_params($db_connection, $alert_query, [$alert_id]);
$alert = pg_fetch_assoc($alert_result);
if (!$alert) {
    $_SESSION['error'] = 'Alert not found.';
    header('Location: alerts.php');
    exit;
}

// Get all residents for dropdown
$residents_query = "SELECT resident_id, first_name, last_name FROM residents WHERE status = 'active' ORDER BY first_name, last_name";
$residents_result = pg_query($db_connection, $residents_query);

// Get all staff members for dropdown
$staff_query = "SELECT staff_id, first_name, last_name FROM staff WHERE active = true ORDER BY first_name, last_name";
$staff_result = pg_query($db_connection, $staff_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_id = $_POST['resident_id'];
    $category = $_POST['category'];
    $priority_level = $_POST['priority_level'];
    $alert_level = $_POST['alert_level'];
    $description = $_POST['description'];
    $location = $_POST['location'] ?? null;
    $staff_id = $_POST['staff_id'] ?: null;
    $response_required_by = !empty($_POST['response_required_by']) ? $_POST['response_required_by'] : null;
    $status = $_POST['status'];

    $query = "UPDATE medical_alerts SET resident_id=$1, category=$2, priority_level=$3, alert_level=$4, description=$5, location=$6, staff_id=$7, response_required_by=$8, status=$9 WHERE alert_id=$10";
    $params = [$resident_id, $category, $priority_level, $alert_level, $description, $location, $staff_id, $response_required_by, $status, $alert_id];
    $result = pg_query_params($db_connection, $query, $params);
    if ($result) {
        $_SESSION['success'] = "Alert updated successfully.";
        header("Location: alerts.php");
        exit;
    } else {
        $_SESSION['error'] = "Error updating alert: " . pg_last_error($db_connection);
        header("Location: edit_alert.php?alert_id=$alert_id");
        exit;
    }
}
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Edit Alert</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo nl2br(htmlspecialchars($_SESSION['error'])); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" style="max-width: 900px; margin: 0 auto;">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Resident</label>
                <select class="form-select" name="resident_id" required>
                    <option value="">Select Resident</option>
                    <?php 
                    pg_result_seek($residents_result, 0);
                    while ($resident = pg_fetch_assoc($residents_result)): 
                    ?>
                        <option value="<?php echo $resident['resident_id']; ?>" <?php if ($alert['resident_id'] == $resident['resident_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <select class="form-select" name="category" required>
                    <option value="">Select Category</option>
                    <option value="medical_emergency" <?php if ($alert['category'] == 'medical_emergency') echo 'selected'; ?>>Medical Emergency</option>
                    <option value="medication" <?php if ($alert['category'] == 'medication') echo 'selected'; ?>>Medication</option>
                    <option value="fall" <?php if ($alert['category'] == 'fall') echo 'selected'; ?>>Fall</option>
                    <option value="behavioral" <?php if ($alert['category'] == 'behavioral') echo 'selected'; ?>>Behavioral</option>
                    <option value="dietary" <?php if ($alert['category'] == 'dietary') echo 'selected'; ?>>Dietary</option>
                    <option value="mobility" <?php if ($alert['category'] == 'mobility') echo 'selected'; ?>>Mobility</option>
                    <option value="sleep" <?php if ($alert['category'] == 'sleep') echo 'selected'; ?>>Sleep</option>
                    <option value="vitals" <?php if ($alert['category'] == 'vitals') echo 'selected'; ?>>Vitals</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Priority Level</label>
                <select class="form-select" name="priority_level" required>
                    <option value="">Select Priority</option>
                    <option value="critical" <?php if ($alert['priority_level'] == 'critical') echo 'selected'; ?>>Critical</option>
                    <option value="high" <?php if ($alert['priority_level'] == 'high') echo 'selected'; ?>>High</option>
                    <option value="medium" <?php if ($alert['priority_level'] == 'medium') echo 'selected'; ?>>Medium</option>
                    <option value="low" <?php if ($alert['priority_level'] == 'low') echo 'selected'; ?>>Low</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Alert Level</label>
                <select class="form-select" name="alert_level" required>
                    <option value="">Select Alert Level</option>
                    <option value="red" <?php if ($alert['alert_level'] == 'red') echo 'selected'; ?>>Red</option>
                    <option value="yellow" <?php if ($alert['alert_level'] == 'yellow') echo 'selected'; ?>>Yellow</option>
                    <option value="blue" <?php if ($alert['alert_level'] == 'blue') echo 'selected'; ?>>Blue</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars($alert['description']); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($alert['location']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Assigned Staff</label>
                <select class="form-select" name="staff_id">
                    <option value="">Unassigned</option>
                    <?php 
                    pg_result_seek($staff_result, 0);
                    while ($staff = pg_fetch_assoc($staff_result)): 
                    ?>
                        <option value="<?php echo $staff['staff_id']; ?>" <?php if ($alert['staff_id'] == $staff['staff_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Response Required By</label>
                <input type="datetime-local" class="form-control" name="response_required_by" value="<?php echo $alert['response_required_by'] ? date('Y-m-d\TH:i', strtotime($alert['response_required_by'])) : ''; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" required>
                    <option value="pending" <?php if ($alert['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="acknowledged" <?php if ($alert['status'] == 'acknowledged') echo 'selected'; ?>>Acknowledged</option>
                    <option value="in_progress" <?php if ($alert['status'] == 'in_progress') echo 'selected'; ?>>In Progress</option>
                    <option value="resolved" <?php if ($alert['status'] == 'resolved') echo 'selected'; ?>>Resolved</option>
                    <option value="closed" <?php if ($alert['status'] == 'closed') echo 'selected'; ?>>Closed</option>
                </select>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="alerts.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Alert</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?> 