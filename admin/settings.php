<?php
session_start();
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['admin_user']['username'] ?? 'sgpriyom';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        .header-info {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
        }
        .settings-nav .nav-link.active {
            background-color: #f8f9fa;
            border-left: 3px solid #0d6efd;
        }
        .settings-nav .nav-link {
            color: #333;
            padding: 0.8rem 1rem;
            border-left: 3px solid transparent;
        }
    </style>
</head>
<body>
    <!-- Header Info -->
    <div class="header-info">
        <div class="container">
Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): <span id="current-datetime"><?php echo $currentDateTime; ?></span>
Current User's Login: <span id="current-user"><?php echo htmlspecialchars($currentUser); ?></span>
        </div>
    </div>

    <!-- Settings Content -->
    <div class="container mt-4">
        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="settings-nav nav flex-column nav-pills" id="settingsTabs" role="tablist">
                            <a class="nav-link active" data-bs-toggle="pill" href="#general" role="tab">
                                <i class="bi bi-gear"></i> General Settings
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#security" role="tab">
                                <i class="bi bi-shield-lock"></i> Security Settings
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#notifications" role="tab">
                                <i class="bi bi-bell"></i> Notifications
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#api" role="tab">
                                <i class="bi bi-code-square"></i> API Configuration
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#backup" role="tab">
                                <i class="bi bi-database"></i> Backup & Restore
                            </a>
                            <a class="nav-link" data-bs-toggle="pill" href="#logs" role="tab">
                                <i class="bi bi-journal-text"></i> System Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" class="form-control" value="MyCompany Ltd.">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Default Currency</label>
                                        <select class="form-select">
                                            <option selected>INR - Indian Rupee</option>
                                            <option>USD - US Dollar</option>
                                            <option>EUR - Euro</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Time Zone</label>
                                        <select class="form-select">
                                            <option selected>Asia/Kolkata (GMT +5:30)</option>
                                            <option>UTC</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Date Format</label>
                                        <select class="form-select">
                                            <option selected>DD-MM-YYYY</option>
                                            <option>MM-DD-YYYY</option>
                                            <option>YYYY-MM-DD</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Password Policy</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Require minimum 8 characters</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Require special characters</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Require numbers</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" value="30">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Two-Factor Authentication</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Enable 2FA for all users</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Security Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Notification Settings</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Email Notifications</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">System Alerts</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Transaction Updates</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox">
                                            <label class="form-check-label">Marketing Updates</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">SMS Notifications</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Enable SMS Alerts</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Save Notification Preferences</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- API Settings -->
                    <div class="tab-pane fade" id="api" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">API Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">API Key</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="xxxxxx-xxxxxx-xxxxxx-xxxxxx" readonly>
                                            <button class="btn btn-outline-secondary" type="button">Regenerate</button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Webhook URL</label>
                                        <input type="url" class="form-control" value="https://api.example.com/webhook">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">API Rate Limit</label>
                                        <input type="number" class="form-control" value="1000">
                                        <small class="text-muted">Requests per hour</small>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update API Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Settings -->
                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Backup & Restore</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6>Automatic Backups</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" checked>
                                        <label class="form-check-label">Enable automatic backups</label>
                                    </div>
                                    <select class="form-select mt-2">
                                        <option>Daily</option>
                                        <option selected>Weekly</option>
                                        <option>Monthly</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <h6>Manual Backup</h6>
                                    <button class="btn btn-primary">
                                        <i class="bi bi-download"></i> Create Backup Now
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <h6>Restore from Backup</h6>
                                    <div class="input-group">
                                        <input type="file" class="form-control">
                                        <button class="btn btn-outline-secondary" type="button">Restore</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Logs -->
                    <div class="tab-pane fade" id="logs" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Logs</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <select class="form-select">
                                        <option>All Logs</option>
                                        <option>Error Logs</option>
                                        <option>Access Logs</option>
                                        <option>Transaction Logs</option>
                                    </select>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Level</th>
                                                <th>Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>2025-03-11 16:49:35</td>
                                                <td><span class="badge bg-info">INFO</span></td>
                                                <td>System settings updated</td>
                                            </tr>
                                            <tr>
                                                <td>2025-03-11 16:48:22</td>
                                                <td><span class="badge bg-warning">WARN</span></td>
                                                <td>Failed login attempt</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="text-end mt-3">
                                    <button class="btn btn-secondary">
                                        <i class="bi bi-download"></i> Download Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
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

    // Auto-activate tab from hash on page load
    document.addEventListener("DOMContentLoaded", function() {
      var hash = window.location.hash;
      if (hash) {
        var tabTrigger = document.querySelector('a[href="' + hash + '"]');
        if (tabTrigger) {
          var tab = new bootstrap.Tab(tabTrigger);
          tab.show();
        }
      }
    });
    </script>
</body>
</html>