<?php
session_start();
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Reports</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
Current User's Login: <span id="current-user"><?php echo $currentUser; ?></span>
transaction.php</div>
    </div>

    <!-- Transaction Report Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Transaction Report</h4>
                <div>
                    <button class="btn btn-success me-2" onclick="exportTableToExcel('transactionTable', 'transaction_report.xlsx')">
                        <i class="bi bi-file-excel"></i> Export Excel
                    </button>
                    <button class="btn btn-info" onclick="printElement('reportContent')">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                </div>
            </div>
            <div class="card-body" id="reportContent">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Transaction Type</label>
                        <select class="form-select">
                            <option>All Types</option>
                            <option>Credit</option>
                            <option>Debit</option>
                            <option>Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Amount Range</label>
                        <select class="form-select">
                            <option>All Amounts</option>
                            <option>0-1000</option>
                            <option>1001-10000</option>
                            <option>>10000</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select">
                            <option>All Status</option>
                            <option>Success</option>
                            <option>Pending</option>
                            <option>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary d-block w-100">Search</button>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h6>Total Volume</h6>
                            <h3>₹1,23,45,678</h3>
                            <small class="text-success">↑ 8.5% vs last period</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h6>Transaction Count</h6>
                            <h3>5,678</h3>
                            <small class="text-success">↑ 12.3%</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h6>Success Rate</h6>
                            <h3>99.2%</h3>
                            <small class="text-success">↑ 0.5%</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h6>Average Amount</h6>
                            <h3>₹21,745</h3>
                            <small class="text-muted">Per transaction</small>
                        </div>
                    </div>
                </div>

                <!-- Transaction Chart -->
                <div class="chart-container mb-4">
                    <canvas id="transactionChart"></canvas>
                </div>

                <!-- Transactions Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="transactionTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>TXN123456789</td>
                                <td>2025-03-11 17:00:44</td>
                                <td>John Doe</td>
                                <td>Credit</td>
                                <td>Salary Credit</td>
                                <td>₹45,000.00</td>
                                <td><span class="badge bg-success">Success</span></td>
                                <td>
                                    <button class="btn btn-sm btn-info">View</button>
                                    <button class="btn btn-sm btn-secondary">Receipt</button>
                                </td>
                            </tr>
                            <tr>
                                <td>TXN123456790</td>
                                <td>2025-03-11 16:55:22</td>
                                <td>Jane Smith</td>
                                <td>Debit</td>
                                <td>ATM Withdrawal</td>
                                <td>₹10,000.00</td>
                                <td><span class="badge bg-success">Success</span></td>
                                <td>
                                    <button class="btn btn-sm btn-info">View</button>
                                    <button class="btn btn-sm btn-secondary">Receipt</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav class="mt-3">
                    <ul class="pagination justify-content-end">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">Previous</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">3</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>