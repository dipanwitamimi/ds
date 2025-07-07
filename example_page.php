<?php
$pageTitle = 'Example Page';
ob_start();
?>

<!-- Your page content here -->
<h1>Example Page</h1>
<p>Content goes here...</p>

<?php
$content = ob_get_clean();
include 'includes/layout.php';
?>