<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $location = $_POST['location'] ?? null;
    $created_by = $_SESSION['user_id'];
    
    $errors = [];
    
    // Validate title is not a number
    if (is_numeric($title)) {
        $errors[] = "Title cannot be a number. Please enter a descriptive title.";
    }
    
    // Validate title is not empty
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    // Validate date is not in the past
    $event_timestamp = strtotime($event_date);
    $current_timestamp = time();
    
    if ($event_timestamp <= $current_timestamp) {
        $errors[] = "Event date and time must be in the future.";
    }
    
    // Validate description is not just numbers
    if (!empty($description) && is_numeric($description)) {
        $errors[] = "Description cannot be just numbers. Please provide a detailed description.";
    }
    
    // Validate location is not just numbers
    if (!empty($location) && is_numeric($location)) {
        $errors[] = "Location cannot be just numbers. Please provide a proper location description.";
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        $query = "INSERT INTO events (title, description, event_date, location, created_by) VALUES ($1, $2, $3, $4, $5)";
        $params = [$title, $description, $event_date, $location, $created_by];
        $result = pg_query_params($db_connection, $query, $params);
        if ($result) {
            $_SESSION['success'] = "Event added successfully.";
            header("Location: events.php");
            exit;
        } else {
            $_SESSION['error'] = "Error adding event: " . pg_last_error($db_connection);
            header("Location: add_event.php");
            exit;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Add New Event</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="" method="POST" style="max-width: 900px; margin: 0 auto;" id="addEventForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required 
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                       oninput="validateTitle(this)">
                <div class="form-text">Title must be descriptive and cannot be just numbers (e.g., "25th Anniversary" is allowed)</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date & Time</label>
                <input type="datetime-local" name="event_date" class="form-control" required 
                       value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : ''; ?>"
                       onchange="validateDate(this)">
                <div class="form-text">Date and time must be in the future</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" 
                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                       oninput="validateLocation(this)">
                <div class="form-text">Optional: Cannot be just numbers (e.g., "Room 101" is allowed)</div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" required 
                          oninput="validateDescription(this)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                <div class="form-text">Cannot be just numbers (e.g., "Event on 15th March" is allowed)</div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="events.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Event</button>
        </div>
    </form>
</div>

<script>
function validateTitle(input) {
    const title = input.value.trim();
    const isNumeric = !isNaN(title) && title !== '';
    
    if (isNumeric) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (title.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateDescription(input) {
    const description = input.value.trim();
    const isNumeric = !isNaN(description) && description !== '';
    
    if (isNumeric) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (description.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateLocation(input) {
    const location = input.value.trim();
    const isNumeric = !isNaN(location) && location !== '';
    
    if (isNumeric && location.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else if (location.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function validateDate(input) {
    const selectedDate = new Date(input.value);
    const currentDate = new Date();
    
    if (selectedDate <= currentDate) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    } else {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    }
}

// Set minimum date to current date and time
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.querySelector('input[name="event_date"]');
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    
    const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    dateInput.min = minDateTime;
    
    // Validate form before submission
    document.getElementById('addEventForm').addEventListener('submit', function(e) {
        const title = document.querySelector('input[name="title"]').value.trim();
        const description = document.querySelector('textarea[name="description"]').value.trim();
        const location = document.querySelector('input[name="location"]').value.trim();
        const eventDate = document.querySelector('input[name="event_date"]').value;
        
        let hasErrors = false;
        
        // Check if title is numeric
        if (!isNaN(title) && title !== '') {
            alert('Title cannot be just numbers. Please enter a descriptive title.');
            hasErrors = true;
        }
        
        // Check if description is numeric
        if (!isNaN(description) && description !== '') {
            alert('Description cannot be just numbers. Please provide a detailed description.');
            hasErrors = true;
        }
        
        // Check if location is numeric
        if (!isNaN(location) && location !== '') {
            alert('Location cannot be just numbers. Please provide a proper location description.');
            hasErrors = true;
        }
        
        // Check if date is in the past
        if (eventDate) {
            const selectedDate = new Date(eventDate);
            const currentDate = new Date();
            if (selectedDate <= currentDate) {
                alert('Event date and time must be in the future.');
                hasErrors = true;
            }
        }
        
        if (hasErrors) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 