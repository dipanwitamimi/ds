<?php
// Common header info component
function displayHeaderInfo() {
    $currentDateTime = date('Y-m-d H:i:s');
    $currentUser = 'sgpriyom';
    ?>
    <div class="header-info">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 
                    <span id="current-datetime"><?php echo $currentDateTime; ?></span>
                </div>
                <div class="col-md-4 text-end">
                    Current User's Login: 
                    <span id="current-user"><?php echo $currentUser; ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>