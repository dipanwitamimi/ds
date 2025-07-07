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
    <title>Edit Branch</title>
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
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
Current User's Login: <span id="current-user"><?php echo $currentUser; ?></span>
The remaining branch management pages (edit.php, delete.php)</div>
    </div>

    <!-- Edit Branch Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Edit Branch</h4>
                <div>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete Branch
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Branch Status Alert -->
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>
                        This branch is currently active and operating normally.
                    </div>
                </div>

                <form id="editBranchForm" method="POST" action="process_edit.php">
                    <input type="hidden" name="branch_id" value="BR001">
                    
                    <!-- Branch Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Branch Code</label>
                                <input type="text" class="form-control" name="branch_code" value="BR001" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Branch Name*</label>
                                <input type="text" class="form-control" name="branch_name" value="Main Branch" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Branch Type</label>
                                <select class="form-select" name="branch_type">
                                    <option value="main" selected>Main Branch</option>
                                    <option value="sub">Sub Branch</option>
                                    <option value="satellite">Satellite Office</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="mb-3">Contact Information</h5>

                            <div class="mb-3">
                                <label class="form-label">Address Line 1*</label>
                                <input type="text" class="form-control" name="address1" value="123 Main Street" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address2" value="Suite 100">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">City*</label>
                                    <input type="text" class="form-control" name="city" value="Mumbai" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Postal Code*</label>
                                    <input type="text" class="form-control" name="postal_code" value="400001" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contact Number*</label>
                                <input type="tel" class="form-control" name="contact_number" value="022-12345678" required>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="mb-3">Additional Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Manager</label>
                                        <input type="text" class="form-control" name="manager" value="John Doe">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Working Hours</label>
                                        <input type="text" class="form-control" name="working_hours" value="9:00 AM - 5:00 PM">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3">Main city branch with full banking services.</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this branch? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete.php" method="POST" style="display: inline;">
                        <input type="hidden" name="branch_id" value="BR001">
                        <button type="submit" class="btn btn-danger">Delete Branch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function updateDateTime() {
        const now = new Date();
        const formatted = now.getUTCFullYear() + '-' + 
                         String(now.getUTCMonth() + 1).padStart(2, '0') + '-' + 
                         String(now.getUTCDate()).padStart(2, '0') + ' ' + 
                         String(now.getUTCHours()).padStart(2, '0') + ':' + 
                         String(now.getUTCMinutes()).padStart(2, '0') + ':' + 
                         String(now.getUTCSeconds()).padStart(2, '0');
        
        document.getElementById('current-datetime').textContent = formatted;
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();
    </script>
</body>
</html>