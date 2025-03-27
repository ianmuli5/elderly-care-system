<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

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

// Get all events for residents assigned to this staff member
$events_query = "SELECT e.*, 
                        r.first_name as resident_first_name,
                        r.last_name as resident_last_name,
                        CASE WHEN e.event_date >= CURRENT_DATE THEN TRUE ELSE FALSE END as is_upcoming
                 FROM events e
                 JOIN users u ON e.created_by = u.user_id
                 JOIN residents r ON r.family_member_id = u.user_id
                 WHERE r.caregiver_id = $1
                 ORDER BY e.event_date ASC";
$events_result = pg_query_params($db_connection, $events_query, array($_SESSION['staff_id']));

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Elderly Care System</title>
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
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .resident-card {
            transition: transform 0.2s;
        }
        
        .resident-card:hover {
            transform: translateY(-5px);
        }
        
        .alert-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            background-color: #e74a3b;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .profile-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .category-medical { background-color: #e74a3b !important; color: white !important; }
        .category-activity { background-color: #1cc88a !important; color: white !important; }
        .category-visitor { background-color: #4e73df !important; color: white !important; }
        .category-other { background-color: #858796 !important; color: white !important; }

        #allEvents {
            display: none;
        }
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
                        <a class="nav-link active" href="index.php">
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
                                                             class="profile-image mb-3">
                                                    <?php else: ?>
                                                        <img src="../assets/images/default-profile.png" 
                                                             class="profile-image mb-3">
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
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Events</h5>
                            <button class="btn btn-primary btn-sm" onclick="toggleEvents()">
                                <i class="fas fa-expand me-2"></i><span id="toggleButtonText">View All Events</span>
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Upcoming Events List -->
                            <div id="upcomingEvents">
                                <h6 class="mb-3">Upcoming Events</h6>
                                <?php 
                                $has_upcoming = false;
                                pg_result_seek($events_result, 0); // Reset the pointer
                                while ($event = pg_fetch_assoc($events_result)):
                                    if ($event['is_upcoming']):
                                        $has_upcoming = true;
                                ?>
                                    <div class="list-group-item mb-2">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                <p class="mb-1 text-muted">
                                                    <small>
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($event['resident_first_name'] . ' ' . $event['resident_last_name']); ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge category-<?php echo strtolower($event['category'] ?? 'other'); ?>">
                                                    <?php echo ucfirst($event['category'] ?? 'Other'); ?>
                                                </span>
                                                <p class="mb-0"><small><?php echo date('M d, Y g:i A', strtotime($event['event_date'])); ?></small></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endwhile;
                                if (!$has_upcoming):
                                ?>
                                    <p class="text-muted text-center">No upcoming events.</p>
                                <?php endif; ?>
                            </div>

                            <!-- All Events Table -->
                            <div id="allEvents">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Resident</th>
                                                <th>Category</th>
                                                <th>Title</th>
                                                <th>Location</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            pg_result_seek($events_result, 0); // Reset the pointer
                                            while ($event = pg_fetch_assoc($events_result)): 
                                            ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($event['event_date'])); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($event['resident_first_name'] . ' ' . $event['resident_last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge category-<?php echo strtolower($event['category'] ?? 'other'); ?>">
                                                            <?php echo ucfirst($event['category'] ?? 'Other'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($event['location']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEvents() {
            const upcomingEvents = document.getElementById('upcomingEvents');
            const allEvents = document.getElementById('allEvents');
            const toggleButtonText = document.getElementById('toggleButtonText');
            
            if (allEvents.style.display === 'none') {
                upcomingEvents.style.display = 'none';
                allEvents.style.display = 'block';
                toggleButtonText.textContent = 'Show Upcoming Only';
            } else {
                upcomingEvents.style.display = 'block';
                allEvents.style.display = 'none';
                toggleButtonText.textContent = 'View All Events';
            }
        }
    </script>
</body>
</html> 