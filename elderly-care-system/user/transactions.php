<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get resident information for the logged-in user
$resident_query = "SELECT resident_id FROM residents WHERE family_member_id = $1";
$resident_result = pg_query_params($db_connection, $resident_query, array($_SESSION['user_id']));
$resident = pg_fetch_assoc($resident_result);

if (!$resident) {
    die("No resident found for this family member.");
}

// Get all transactions for the resident
$transactions_query = "SELECT t.*, u.username as created_by_username
                    FROM transactions t
                    JOIN users u ON t.created_by = u.user_id
                    WHERE t.related_resident_id = $1
                    ORDER BY t.date DESC";
$transactions_result = pg_query_params($db_connection, $transactions_query, array($resident['resident_id']));

if (!$transactions_result) {
    die("Error fetching transactions: " . pg_last_error($db_connection));
}

// Calculate total payments made
$totals_query = "SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_payments
                FROM transactions
                WHERE related_resident_id = $1";
$totals_result = pg_query_params($db_connection, $totals_query, array($resident['resident_id']));
$totals = pg_fetch_assoc($totals_result);
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
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPaymentModal">
            <i class="fas fa-plus"></i> Make Payment
        </button>
    </div>

    <!-- Summary Card -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Payments Made</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['total_payments'] ?? 0, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
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
                        <?php if (pg_num_rows($transactions_result) > 0): ?>
                            <?php while ($transaction = pg_fetch_assoc($transactions_result)): 
                                $type_class = $transaction['type'] === 'income' ? 'success' : 'danger';
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $type_class; ?>"><?php echo ucfirst($transaction['type']); ?></span></td>
                                    <td class="text-<?php echo $type_class; ?>">$<?php echo number_format($transaction['amount'], 2); ?></td>
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
                    <input type="hidden" name="related_resident_id" value="<?php echo $resident['resident_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
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