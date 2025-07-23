<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}

// Get resident ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$resident_id = $_GET['id'];
$staff_id = $_SESSION['staff_id'];

// Get resident details with security check (only allow viewing if the resident is assigned to this staff member)
$resident_query = "SELECT r.*, 
                         u.username as family_username,
                         u.email as family_email,
                         (SELECT COUNT(*) FROM medical_alerts 
                          WHERE resident_id = r.resident_id 
                          AND resolved = FALSE) as unresolved_alerts
                  FROM residents r
                  LEFT JOIN users u ON r.family_member_id = u.user_id
                  WHERE r.resident_id = $1 
                  AND r.caregiver_id = $2";
$resident_result = pg_query_params($db_connection, $resident_query, array($resident_id, $staff_id));

if (pg_num_rows($resident_result) === 0) {
    header("Location: index.php");
    exit;
}

$resident = pg_fetch_assoc($resident_result);

// Get recent alerts for this resident
$alerts_query = "SELECT * FROM medical_alerts 
                WHERE resident_id = $1 
                ORDER BY created_at DESC 
                LIMIT 10";
$alerts_result = pg_query_params($db_connection, $alerts_query, array($resident_id));

// Get unread message count for the navbar
$messages_query = "SELECT COUNT(*) as unread_count 
                  FROM messages 
                  WHERE recipient_id = (
                      SELECT user_id 
                      FROM staff 
                      WHERE staff_id = $1
                  ) 
                  AND read = FALSE";
$messages_result = pg_query_params($db_connection, $messages_query, array($staff_id));
$messages_count = pg_fetch_assoc($messages_result)['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Details - Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            padding-top: 56px;
        }
        
        .navbar {
            background-color: #4e73df;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .nav-link:hover {
            color: white !important;
        }

        .nav-link.active {
            color: white !important;
            font-weight: bold;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .profile-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert-badge-red { background-color: #e74a3b; }
        .alert-badge-yellow { background-color: #f6c23e; }
        .alert-badge-blue { background-color: #4e73df; }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-resolved { background-color: #1cc88a; }
        .status-pending { background-color: #f6c23e; }
        .status-urgent { background-color: #e74a3b; }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-user-md me-2"></i>Staff Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-2"></i>Messages
                            <?php if ($messages_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $messages_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-calendar me-2"></i>Events
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['staff_name']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
            <h1 class="h2">Resident Details</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="row">
            <!-- Profile Section -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php if (!empty($resident['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($resident['profile_picture']); ?>" 
                                 class="rounded-circle profile-image mb-3">
                        <?php else: ?>
                            <img src="../assets/images/default-profile.png" 
                                 class="rounded-circle profile-image mb-3">
                        <?php endif; ?>
                        
                        <h4><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h4>
                        <p class="text-muted">
                            <?php 
                            $dob = new DateTime($resident['date_of_birth']);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                            echo $age . ' years old';
                            ?>
                        </p>
                        
                        <?php if ($resident['unresolved_alerts'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $resident['unresolved_alerts']; ?> unresolved alert<?php echo $resident['unresolved_alerts'] > 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        switch($resident['status']) {
                            case 'active':
                                echo '<span class="badge bg-success">Active Resident</span>';
                                break;
                            case 'waitlist':
                                echo '<span class="badge bg-warning">On Waitlist</span>';
                                break;
                            case 'former':
                                echo '<span class="badge bg-secondary">Former Resident</span>';
                                break;
                        }
                        ?>
                    </div>
                </div>

                <!-- Family Contact Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Family Contact</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($resident['family_username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($resident['family_email']); ?></p>
                        <a href="messages.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-envelope me-2"></i>Contact Admin
                        </a>
                    </div>
                </div>
            </div>

            <!-- Details Section -->
            <div class="col-md-8">
                <!-- Medical Condition -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Medical Condition</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($resident['medical_condition'])): ?>
                            <?php echo nl2br(htmlspecialchars($resident['medical_condition'])); ?>
                        <?php else: ?>
                            <p class="text-muted">No medical conditions recorded.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Medical Alerts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (pg_num_rows($alerts_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Level</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($alert = pg_fetch_assoc($alerts_result)): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge alert-badge-<?php echo strtolower($alert['alert_level']); ?>">
                                                        <?php echo ucfirst($alert['alert_level']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($alert['category']); ?></td>
                                                <td><?php echo htmlspecialchars($alert['description']); ?></td>
                                                <td>
                                                    <div class="status-indicator status-<?php echo $alert['resolved'] ? 'resolved' : ($alert['priority_level'] === 'urgent' ? 'urgent' : 'pending'); ?>"></div>
                                                    <?php echo ucfirst($alert['status']); ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent alerts.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Interests & Activities -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Interests & Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($resident['interests'])): ?>
                            <?php echo nl2br(htmlspecialchars($resident['interests'])); ?>
                        <?php else: ?>
                            <p class="text-muted">No interests recorded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 