<?php
require_once '../includes/config.php';

// Check if user is logged in and is a family member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'family') {
    header("Location: ../index.php");
    exit;
}

// Get resident ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$resident_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get resident details with security check (only allow viewing if the resident belongs to this family member)
$resident_query = "SELECT r.*, 
                    s.first_name as caregiver_first_name, 
                    s.last_name as caregiver_last_name,
                    s.position as caregiver_position,
                    s.contact_info as caregiver_contact,
                    s.staff_id as caregiver_id
                  FROM residents r
                  LEFT JOIN staff s ON r.caregiver_id = s.staff_id
                  WHERE r.resident_id = $1 AND r.family_member_id = $2";
$resident_result = pg_query_params($db_connection, $resident_query, array($resident_id, $user_id));

if (pg_num_rows($resident_result) === 0) {
    header("Location: index.php");
    exit;
}

$resident = pg_fetch_assoc($resident_result);

// Get recent alerts for this resident
$alerts_query = "SELECT * FROM medical_alerts 
                WHERE resident_id = $1 
                ORDER BY created_at DESC 
                LIMIT 5";
$alerts_result = pg_query_params($db_connection, $alerts_query, array($resident_id));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Details - Elderly Care Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }
        
        .sidebar {
            min-height: 100vh;
            background-color: #1cc88a;
            padding-top: 1rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem 1.5rem;
            font-size: 0.85rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .profile-image {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .info-card {
            height: 100%;
        }
        
        .alert-badge-red { background-color: #e74a3b; }
        .alert-badge-yellow { background-color: #f6c23e; }
        .alert-badge-blue { background-color: #4e73df; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky">
                    <div class="text-center p-3 mb-3">
                        <h5 class="text-white">Elderly Care System</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope me-2"></i>Messages
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2">Resident Details</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <div class="row">
                    <!-- Profile Section -->
                    <div class="col-md-4 mb-4">
                        <div class="card text-center">
                            <div class="card-body">
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

                        <!-- Caregiver Information -->
                        <?php if ($resident['caregiver_id']): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Caregiver Information</h5>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($resident['caregiver_first_name'] . ' ' . $resident['caregiver_last_name']); ?></h6>
                                <?php if ($resident['caregiver_position']): ?>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($resident['caregiver_position']); ?></p>
                                <?php endif; ?>
                                <?php if ($resident['caregiver_contact']): ?>
                                    <p class="mb-3"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($resident['caregiver_contact']); ?></p>
                                <?php endif; ?>
                                <a href="messages.php?user=<?php echo $resident['caregiver_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope me-2"></i>Contact Caregiver
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details Section -->
                    <div class="col-md-8">
                        <div class="row">
                            <!-- Medical Condition -->
                            <div class="col-md-12 mb-4">
                                <div class="card info-card">
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
                            </div>

                            <!-- Interests -->
                            <div class="col-md-12 mb-4">
                                <div class="card info-card">
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

                            <!-- Recent Alerts -->
                            <div class="col-md-12 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Recent Medical Alerts</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (pg_num_rows($alerts_result) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th>Alert Level</th>
                                                            <th>Description</th>
                                                            <th>Date</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($alert = pg_fetch_assoc($alerts_result)): 
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
                                                            <tr>
                                                                <td><span class="badge bg-<?php echo $alert_class; ?>"><?php echo ucfirst($alert['alert_level']); ?></span></td>
                                                                <td><?php echo htmlspecialchars($alert['description']); ?></td>
                                                                <td><?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?></td>
                                                                <td>
                                                                    <?php if ($alert['resolved']): ?>
                                                                        <span class="badge bg-success">Resolved</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning">Pending</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-center text-muted">No recent alerts.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 