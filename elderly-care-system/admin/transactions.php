<?php
require_once '../includes/config.php';
require_once 'includes/header.php';

// Get all transactions with related information
$transactions_query = "SELECT t.*, 
                        u.username as created_by_username,
                        r.first_name as resident_first_name,
                        r.last_name as resident_last_name,
                        fm.username as family_member_username
                    FROM transactions t
                    JOIN users u ON t.created_by = u.user_id
                    LEFT JOIN residents r ON t.related_resident_id = r.resident_id
                    LEFT JOIN users fm ON r.family_member_id = fm.user_id
                    ORDER BY t.date DESC";
$transactions_result = pg_query($db_connection, $transactions_query);

if (!$transactions_result) {
    die("Error fetching transactions: " . pg_last_error($db_connection));
}

// Calculate total income and expenses
$totals_query = "SELECT 
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses
                FROM transactions";
$totals_result = pg_query($db_connection, $totals_query);
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
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Income</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['total_income'] ?? 0, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Total Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['total_expenses'] ?? 0, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-minus-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Net Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format(($totals['total_income'] ?? 0) - ($totals['total_expenses'] ?? 0), 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
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
                            <th>Related Resident</th>
                            <th>Family Member</th>
                            <th>Created By</th>
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
                                    <td>
                                        <?php 
                                        if ($transaction['resident_first_name']) {
                                            echo htmlspecialchars($transaction['resident_first_name'] . ' ' . $transaction['resident_last_name']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($transaction['family_member_username']) {
                                            echo htmlspecialchars($transaction['family_member_username']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['created_by_username']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 