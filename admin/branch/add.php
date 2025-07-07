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
    <title>Add New Branch</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        body {
            background: #f8f9fa;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .form-label {
            font-weight: 500;
        }
        .required:after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <?php echo $currentDateTime; ?>
Current User's Login: <?php echo $currentUser; ?>
admin/branch/add.php</div>
    </div>

    <!-- Add Branch Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add New Branch</h4>
                <a href="../branch.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form id="addBranchForm" method="POST" action="process_branch.php">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label required">Branch Code</label>
                                <input type="text" class="form-control" name="branch_code" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Branch Name</label>
                                <input type="text" class="form-control" name="branch_name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Branch Type</label>
                                <select class="form-select" name="branch_type">
                                    <option value="main">Main Branch</option>
                                    <option value="sub">Sub Branch</option>
                                    <option value="satellite">Satellite Office</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Opening Date</label>
                                <input type="date" class="form-control" name="opening_date" required>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Contact Information</h5>

                            <div class="mb-3">
                                <label class="form-label required">Address Line 1</label>
                                <input type="text" class="form-control" name="address1" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address2">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">City</label>
                                    <input type="text" class="form-control" name="city" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Postal Code</label>
                                    <input type="text" class="form-control" name="postal_code" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Contact Number</label>
                                <input type="tel" class="form-control" name="contact_number" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="col-12 mt-3">
                            <h5 class="mb-3">Additional Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Manager Name</label>
                                        <input type="text" class="form-control" name="manager_name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Working Hours</label>
                                        <input type="text" class="form-control" name="working_hours" placeholder="e.g., 9:00 AM - 5:00 PM">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Branch
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>