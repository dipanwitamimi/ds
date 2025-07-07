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
    <title>Add New Staff</title>
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
admin/staff/add.php</div>
    </div>

    <!-- Add Staff Content -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add New Staff</h4>
                <a href="../staff.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="card-body">
                <form id="addStaffForm" method="POST" action="process_staff.php" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label required">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Mobile Number</label>
                                <input type="tel" class="form-control" name="mobile" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Employment Information</h5>

                            <div class="mb-3">
                                <label class="form-label required">Staff ID</label>
                                <input type="text" class="form-control" name="staff_id" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Branch</label>
                                <select class="form-select" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php
                                    require_once '../../config/database.php';
                                    try {
                                        $db = new Database();
                                        $conn = $db->getConnection();
                                        $stmt = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name");
                                        while ($branch = $stmt->fetch()) {
                                            echo "<option value='" . $branch['id'] . "'>" . htmlspecialchars($branch['branch_name']) . "</option>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<option value=''>Error loading branches</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="manager">Manager</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Joining Date</label>
                                <input type="date" class="form-control" name="joining_date" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department">
                                    <option value="">Select Department</option>
                                    <option value="sales">Sales</option>
                                    <option value="operations">Operations</option>
                                    <option value="accounts">Accounts</option>
                                    <option value="admin">Administration</option>
                                </select>
                            </div>
                        </div>

                        <!-- Login Credentials -->
                        <div class="col-md-6 mt-3">
                            <h5 class="mb-3">Login Credentials</h5>

                            <div class="mb-3">
                                <label class="form-label required">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="col-md-6 mt-3">
                            <h5 class="mb-3">Additional Information</h5>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" name="profile_photo" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Staff
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Clear Form
                            </button>
                        </div>
                    </div>
					<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                Debug Information
            </div>
            <div class="card-body">
                <pre id="debugInfo"></pre>
            </div>
        </div>
    </div>
</div>

                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/jquery.min.js"></script>
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/jquery.validate.min.js"></script>
	<script src="../../assets/js/jquery.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.validate.min.js"></script>
<script src="../../assets/js/select2.min.js"></script>
<script src="../../assets/js/moment.min.js"></script>
<script src="../../assets/js/bootstrap-datepicker.min.js"></script>
<script src="../../assets/js/sweetalert2.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/pdfmake.min.js"></script>
<script src="../../assets/js/vfs_fonts.js"></script>
<script src="../../assets/js/chart.min.js"></script>
<script src="js/staff.js"></script>
<script src="js/staff-view.js"></script>
<script>
// Add this to your existing JavaScript
$(document).ready(function() {
    $('#addStaffForm').on('submit', function(e) {
        let formData = new FormData(this);
        let debugInfo = '';
        
        for (let pair of formData.entries()) {
            debugInfo += pair[0] + ': ' + pair[1] + '\n';
        }
        
        $('#debugInfo').text(debugInfo);
    });
});
</script>
    <script>
        $(document).ready(function() {
            $("#addStaffForm").validate({
                rules: {
                    full_name: {
                        required: true,
                        minlength: 3
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    mobile: {
                        required: true,
                        minlength: 10
                    },
                    staff_id: {
                        required: true,
                        minlength: 4
                    },
                    username: {
                        required: true,
                        minlength: 5
                    },
                    password: {
                        required: true,
                        minlength: 6
                    },
                    confirm_password: {
                        required: true,
                        equalTo: "[name='password']"
                    }
                },
                messages: {
                    confirm_password: {
                        equalTo: "Passwords do not match!"
                    }
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.mb-3').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>