<?php
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Distribution Management System');
    define('SITE_URL', '/');
}

// Include header
include 'header.php';
?>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php include 'navigation.php'; ?>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-4">
    <?php echo $content ?? ''; ?>
</div>

<!-- Footer -->
<footer class="bg-dark text-white text-center py-2 mt-4">
    All rights Reserved 2025 and Project developed by MS INFOSYSTEMS
</footer>

<script src="<?php echo SITE_URL; ?>assets/js/jquery.min.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITE_URL; ?>assets/js/datetime.js"></script>
</body>
</html>