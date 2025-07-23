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

// Get all residents for filter and outstanding balance
$residents_query = "SELECT resident_id, first_name, last_name, admission_date FROM residents ORDER BY first_name, last_name";
$residents_result = pg_query($db_connection, $residents_query);
$residents = [];
while ($row = pg_fetch_assoc($residents_result)) {
    $residents[$row['resident_id']] = $row;
}
// Get total paid per resident
$paid_query = "SELECT related_resident_id, SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_paid FROM transactions GROUP BY related_resident_id";
$paid_result = pg_query($db_connection, $paid_query);
$total_paid_map = [];
while ($row = pg_fetch_assoc($paid_result)) {
    $total_paid_map[$row['related_resident_id']] = $row['total_paid'];
}
$monthly_fee = 10000;
// Filter logic
$filter_resident = isset($_GET['resident_id']) ? $_GET['resident_id'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_sql = "";
$params = [];
if ($filter_resident) {
    $filter_sql .= " AND r.resident_id = $1";
    $params[] = $filter_resident;
}
$param_offset = count($params);
$filter_sql .= " AND EXTRACT(MONTH FROM t.date) = $" . ($param_offset+1);
$params[] = $filter_month;
$filter_sql .= " AND EXTRACT(YEAR FROM t.date) = $" . ($param_offset+2);
$params[] = $filter_year;
$filtered_transactions_query = "SELECT t.*, u.username as created_by_username, r.first_name as resident_first_name, r.last_name as resident_last_name, fm.username as family_member_username FROM transactions t JOIN users u ON t.created_by = u.user_id LEFT JOIN residents r ON t.related_resident_id = r.resident_id LEFT JOIN users fm ON r.family_member_id = fm.user_id WHERE 1=1 $filter_sql ORDER BY t.date DESC";
$filtered_transactions_result = pg_query_params($db_connection, $filtered_transactions_query, $params);

// Calculate total outstanding balance for all residents
$total_outstanding = 0;
foreach ($residents as $rid => $res) {
    $admission_date = $res['admission_date'];
    $months = 0;
    if ($admission_date) {
        $admission = new DateTime($admission_date);
        $now = new DateTime();
        $interval = $admission->diff($now);
        $months = ($interval->y * 12) + $interval->m + 1;
    }
    $total_due = $months * $monthly_fee;
    $paid = isset($total_paid_map[$rid]) ? $total_paid_map[$rid] : 0;
    $outstanding = $total_due - $paid;
    $total_outstanding += max($outstanding, 0);
}
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KSh <?php echo number_format($totals['total_income'] ?? 0, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KSh <?php echo number_format(($totals['total_income'] ?? 0) - ($totals['total_expenses'] ?? 0), 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Outstanding Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KSh <?php echo number_format($total_outstanding, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Outstanding Balances Table -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <strong>Outstanding Balances by Resident</strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Resident</th>
                        <th>Outstanding Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($residents as $rid => $res):
                        $admission_date = $res['admission_date'];
                        $months = 0;
                        if ($admission_date) {
                            $admission = new DateTime($admission_date);
                            $now = new DateTime();
                            $interval = $admission->diff($now);
                            $months = ($interval->y * 12) + $interval->m + 1;
                        }
                        $total_due = $months * $monthly_fee;
                        $paid = isset($total_paid_map[$rid]) ? $total_paid_map[$rid] : 0;
                        $outstanding = $total_due - $paid;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></td>
                        <td>KSh <?php echo number_format(max($outstanding, 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="get" class="form-inline mb-2">
                <label for="resident_id" class="me-2">Resident:</label>
                <select name="resident_id" id="resident_id" class="form-select me-2" style="width:auto;display:inline-block;">
                    <option value="">All</option>
                    <?php foreach ($residents as $id => $res): ?>
                        <option value="<?php echo $id; ?>" <?php if ($filter_resident == $id) echo 'selected'; ?>><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
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
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Related Resident</th>
                            <th>Outstanding Balance</th>
                            <th>Family Member</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (pg_num_rows($filtered_transactions_result) > 0): ?>
                            <?php while ($transaction = pg_fetch_assoc($filtered_transactions_result)): 
                                $rid = $transaction['related_resident_id'];
                                $admission_date = isset($residents[$rid]['admission_date']) ? $residents[$rid]['admission_date'] : null;
                                $months = 0;
                                if ($admission_date) {
                                    $admission = new DateTime($admission_date);
                                    $now = new DateTime();
                                    $interval = $admission->diff($now);
                                    $months = ($interval->y * 12) + $interval->m + 1;
                                }
                                $total_due = $months * $monthly_fee;
                                $paid = isset($total_paid_map[$rid]) ? $total_paid_map[$rid] : 0;
                                $outstanding = $total_due - $paid;
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['date'])); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', htmlspecialchars($transaction['category']))); ?></td>
                                    <td class="text-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">KSh <?php echo number_format($transaction['amount'], 2); ?></td>
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
                                    <td>KSh <?php echo number_format(max($outstanding, 0), 2); ?></td>
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