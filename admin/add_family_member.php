<?php
require_once 'includes/header.php';
?>
<div class="container-fluid py-4">
    <h2 class="mb-4">Add New Family Member</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <form action="process_family.php" method="POST" style="max-width: 500px; margin: 0 auto;">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required pattern="[A-Za-z ]+" minlength="3" maxlength="50" title="3–50 letters and spaces only">
            <div class="form-text">3–50 letters and spaces only</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$" maxlength="100" title="Enter a valid email address">
            <div class="form-text">Enter a valid email address (e.g., user@example.com)</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required minlength="6" maxlength="255">
            <div class="form-text">Minimum 6 characters</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" class="form-control" name="confirm_password" required minlength="6" maxlength="255">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" class="form-control" name="phone_number" required pattern="\d{10}" maxlength="10" minlength="10" title="10 digits only">
            <div class="form-text">Enter a valid phone number (exactly 10 digits, numbers only)</div>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="residents.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Family Member</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?> 