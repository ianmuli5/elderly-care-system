<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get all residents for the logged-in user
$residents_query = "SELECT resident_id, first_name, last_name FROM residents WHERE family_member_id = $1 AND status = 'active' ORDER BY first_name, last_name";
$residents_result = pg_query_params($db_connection, $residents_query, array($_SESSION['user_id']));

if (pg_num_rows($residents_result) === 0) {
    // Redirect to a page indicating no active residents or show a message
    // For now, let's show a message on the page itself.
}

// Determine the selected resident
$selected_resident_id = null;
if (pg_num_rows($residents_result) > 0) {
    $selected_resident_id = isset($_GET['resident_id']) ? intval($_GET['resident_id']) : pg_fetch_result($residents_result, 0, 'resident_id');

    // Verify the selected resident belongs to the user to prevent unauthorized access
    $is_valid_resident = false;
    pg_result_seek($residents_result, 0); // Rewind result set
    while ($row = pg_fetch_assoc($residents_result)) {
        if ($row['resident_id'] == $selected_resident_id) {
            $is_valid_resident = true;
            break;
        }
    }
    if (!$is_valid_resident) {
        // Fallback to the first resident if the provided ID is invalid or doesn't belong to the user
        pg_result_seek($residents_result, 0);
        $selected_resident_id = pg_fetch_result($residents_result, 0, 'resident_id');
    }
}


// Get all transactions for the selected resident
$transactions_query = "SELECT t.*, u.username as created_by_username
                    FROM transactions t
                    JOIN users u ON t.created_by = u.user_id
                    WHERE t.related_resident_id = $1
                    ORDER BY t.date DESC";
$transactions_result = pg_query_params($db_connection, $transactions_query, array($selected_resident_id));

if (!$transactions_result) {
    die("Error fetching transactions: " . pg_last_error($db_connection));
}

// Calculate total payments made
$totals_query = "SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_payments
                FROM transactions
                WHERE related_resident_id = $1";
$totals_result = pg_query_params($db_connection, $totals_query, array($selected_resident_id));
$totals = pg_fetch_assoc($totals_result);

// Calculate outstanding balance
$admission_query = "SELECT admission_date FROM residents WHERE resident_id = $1";
$admission_result = pg_query_params($db_connection, $admission_query, array($selected_resident_id));
$admission_row = pg_fetch_assoc($admission_result);
$admission_date = $admission_row ? $admission_row['admission_date'] : null;
$monthly_fee = 10000;
$months = 0;
if ($admission_date) {
    $admission = new DateTime($admission_date);
    $now = new DateTime();
    $interval = $admission->diff($now);
    $months = ($interval->y * 12) + $interval->m + 1; // +1 to count current month
}
$total_due = $months * $monthly_fee;
$total_paid = $totals['total_payments'] ?? 0;
$outstanding = $total_due - $total_paid;

// Filter logic
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filtered_transactions_query = "SELECT t.*, u.username as created_by_username FROM transactions t JOIN users u ON t.created_by = u.user_id WHERE t.related_resident_id = $1 AND EXTRACT(MONTH FROM t.date) = $2 AND EXTRACT(YEAR FROM t.date) = $3 ORDER BY t.date DESC";
$filtered_transactions_result = pg_query_params($db_connection, $filtered_transactions_query, array($selected_resident_id, $filter_month, $filter_year));
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
        <h1 class="h3 mb-0 text-gray-800">Financial Transactions</h1>
        <?php if ($selected_resident_id): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPaymentModal">
            <i class="fas fa-plus"></i> Make Payment
        </button>
        <?php endif; ?>
    </div>

    <?php if (pg_num_rows($residents_result) === 0): ?>
        <div class="alert alert-info">
            You do not have any active residents associated with your account. Please contact administration if you believe this is an error.
        </div>
    <?php else: ?>

    <!-- Resident Selector -->
    <?php if (pg_num_rows($residents_result) > 1): ?>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" id="residentSelectorForm" class="row align-items-center">
                <div class="col-auto">
                    <label for="resident_id" class="col-form-label"><b>Viewing transactions for:</b></label>
                </div>
                <div class="col-auto">
                    <select name="resident_id" id="resident_id" class="form-select" onchange="document.getElementById('residentSelectorForm').submit();">
                        <?php 
                        pg_result_seek($residents_result, 0); // Rewind
                        while ($res = pg_fetch_assoc($residents_result)): ?>
                            <option value="<?php echo $res['resident_id']; ?>" <?php if ($res['resident_id'] == $selected_resident_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>


    <!-- Summary Card -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Outstanding Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                KSh <?php echo number_format(max($outstanding, 0), 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Payments Made</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KSh <?php echo number_format($total_paid, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="get" class="form-inline mb-2">
                <label for="month" class="me-2">Month:</label>
                <select name="month" id="month" class="form-select me-2" style="width:auto;display:inline-block;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>" <?php if ($filter_month == sprintf('%02d', $m)) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year" class="me-2">Year:</label>
                <select name="year" id="year" class="form-select me-2" style="width:auto;display:inline-block;">
                    <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if ($filter_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
            <h6 class="m-0 font-weight-bold text-primary">Transaction History</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($filtered_transactions_result) > 0): ?>
                            <?php while ($transaction = pg_fetch_assoc($filtered_transactions_result)): 
                                $type_class = $transaction['type'] === 'income' ? 'success' : 'danger';
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $type_class; ?>"><?php echo ucfirst($transaction['type']); ?></span></td>
                                    <td class="text-<?php echo $type_class; ?>">KSh <?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($transaction['category']))); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['created_by_username']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- New Payment Modal -->
<div class="modal fade" id="newPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_transaction.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" value="income">
                    <input type="hidden" name="related_resident_id" value="<?php echo $selected_resident_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">KSh</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <small class="form-text text-muted">Enter amount without commas (e.g., 10000)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="monthly_fee">Monthly Fee</option>
                            <option value="medical_expenses">Medical Expenses</option>
                            <option value="personal_care">Personal Care</option>
                            <option value="activities">Activities</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 