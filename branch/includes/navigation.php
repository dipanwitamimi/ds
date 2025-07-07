<?php
if(!defined('SITE_NAME')) {
    exit('Direct script access denied.');
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard.php"><i class="bi bi-house"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../staff/"><i class="bi bi-people"></i> Staff</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../bank/"><i class="bi bi-bank"></i> Bank Accounts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../lapu/"><i class="bi bi-currency-exchange"></i> LAPU</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../sim/"><i class="bi bi-sim"></i> SIM Cards</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../apb/"><i class="bi bi-box"></i> APB</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../dth/"><i class="bi bi-broadcast"></i> DTH</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../cash/"><i class="bi bi-cash-stack"></i> Cash Deposit</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../reports/"><i class="bi bi-file-text"></i> Reports</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>