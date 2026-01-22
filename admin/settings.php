<?php
// admin/settings.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // General Settings
    if ($action === 'save_general') {
        $siteName = sanitizeInput($_POST['site_name'] ?? '');
        $adminEmail = sanitizeInput($_POST['admin_email'] ?? '');
        $currency = sanitizeInput($_POST['currency'] ?? 'DH');
        
        if (empty($siteName)) {
            $message = 'Site name is required';
            $messageType = 'danger';
        } elseif (empty($adminEmail) || !validateEmail($adminEmail)) {
            $message = 'Valid admin email is required';
            $messageType = 'danger';
        } else {
            try {
                // Update or insert settings
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                
                $stmt->execute(['site_name', $siteName]);
                $stmt->execute(['admin_email', $adminEmail]);
                $stmt->execute(['currency', $currency]);
                
                $message = 'General settings updated successfully!';
                $messageType = 'success';
                $activeTab = 'general';
            } catch (PDOException $e) {
                $message = 'Error saving settings: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    // Change Password
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            $message = 'Current password is required';
            $messageType = 'danger';
        } elseif (empty($newPassword)) {
            $message = 'New password is required';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters long';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match';
            $messageType = 'danger';
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    $message = 'Current password is incorrect';
                    $messageType = 'danger';
                } else {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $updateStmt->execute([$hashedPassword, $_SESSION['user_id']]);
                    
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                    $activeTab = 'password';
                }
            } catch (PDOException $e) {
                $message = 'Error changing password: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all settings
try {
    $settingsStmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

// Get system statistics
try {
    $statsStmt = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'agent') as agents,
            (SELECT COUNT(*) FROM clients) as clients,
            (SELECT COUNT(*) FROM subscriptions) as subscriptions,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subs
    ");
    $systemStats = $statsStmt->fetch();
} catch (PDOException $e) {
    $systemStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .tabs-header {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .tab-button {
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: -2px;
        }
        
        .tab-button:hover {
            color: #667eea;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 12px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .form-actions button,
        .form-actions a {
            padding: 12px 30px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .form-actions button[type="submit"] {
            background-color: #007bff;
            color: white;
        }
        
        .form-actions button[type="submit"]:hover {
            background-color: #0056b3;
        }
        
        .form-actions a {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions a:hover {
            background-color: #5a6268;
        }
        
        .settings-info {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .settings-info h5 {
            margin: 0 0 10px 0;
            color: #0c5460;
        }
        
        .settings-info p {
            margin: 5px 0;
            color: #0c5460;
            font-size: 14px;
        }
        
        .stats-display {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            border-top: 3px solid #667eea;
        }
        
        .stat-box-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .stat-box-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Panel</h2>
                <p><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php">📊 Dashboard</a>
                
                <!-- Agents Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'agents-menu')">
                        👥 Agents
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu" id="agents-menu">
                        <a href="manage_agents.php">Manage Agents</a>
                        <a href="create_agent.php">Create New Agent</a>
                        <a href="view_all_clients.php">View All Clients</a>
                    </div>
                </div>
                
                <a href="reports.php">📈 Reports</a>
                
                <!-- Settings Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'settings-menu')">
                        ⚙️ Settings
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu" id="settings-menu">
                        <a href="settings.php" <?php echo $activeTab === 'general' || (empty($activeTab) || $activeTab === '') ? 'style="background-color: #fff0ed; color: #ff6b5b; border-left-color: #ff6b5b;"' : ''; ?>>General Settings</a>
                        <a href="settings.php?tab=password" <?php echo $activeTab === 'password' ? 'style="background-color: #fff0ed; color: #ff6b5b; border-left-color: #ff6b5b;"' : ''; ?>>Change Password</a>
                    </div>
                </div>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Settings</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="settings-container">
                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 20px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs Header -->
                    <div class="tabs-header">
                        <button class="tab-button <?php echo $activeTab === 'general' ? 'active' : ''; ?>" 
                                onclick="switchTab(event, 'general-tab')">
                            ⚙️ General Settings
                        </button>
                        <button class="tab-button <?php echo $activeTab === 'password' ? 'active' : ''; ?>" 
                                onclick="switchTab(event, 'password-tab')">
                            🔐 Change Password
                        </button>
                    </div>

                    <!-- General Settings Tab -->
                    <div id="general-tab" class="tab-content <?php echo $activeTab === 'general' ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header">
                                <h2 style="margin: 0;">General Settings</h2>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="save_general">
                                    
                                    <div class="form-group">
                                        <label for="site_name">Site Name</label>
                                        <input type="text" id="site_name" name="site_name" 
                                               value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Subscription Manager'); ?>" 
                                               required placeholder="Enter site name">
                                    </div>

                                    <div class="form-group">
                                        <label for="admin_email">Admin Email Address</label>
                                        <input type="email" id="admin_email" name="admin_email" 
                                               value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" 
                                               required placeholder="Enter admin email">
                                    </div>

                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select id="currency" name="currency">
                                            <option value="DH" <?php echo ($settings['currency'] ?? 'DH') === 'DH' ? 'selected' : ''; ?>>DH (Moroccan Dirham)</option>
                                            <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                            <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                            <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP (British Pound)</option>
                                        </select>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit">💾 Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings Tab -->
                    <div id="password-tab" class="tab-content <?php echo $activeTab === 'password' ? 'active' : ''; ?>">
                        <div class="card">
                            <div class="card-header">
                                <h2 style="margin: 0;">Change Password</h2>
                            </div>
                            <div class="card-body">
                                <div class="settings-info">
                                    <h5>🔐 Update Your Password</h5>
                                    <p>Enter your current password and then choose a new password for your admin account.</p>
                                </div>

                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" 
                                               required placeholder="Enter your current password">
                                    </div>

                                    <div class="form-group">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password" 
                                               required placeholder="Enter new password">
                                        <small>Password must be at least 6 characters long</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               required placeholder="Confirm new password">
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit">💾 Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Switch tabs
        function switchTab(event, tabId) {
            event.preventDefault();
            
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Deactivate all buttons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
            
            // Update URL
            const tabName = tabId.replace('-tab', '');
            if (tabName !== 'general') {
                window.history.pushState(null, null, '?tab=' + tabName);
            }
        }

        // Toggle sidebar menu
        function toggleMenu(event, menuId) {
            event.preventDefault();
            const button = event.target.closest('.sidebar-menu-toggle');
            const menu = document.getElementById(menuId);
            
            button.classList.toggle('expanded');
            menu.classList.toggle('active');
            
            // Save state to localStorage
            localStorage.setItem('menu-' + menuId, menu.classList.contains('active'));
        }
        
        // Restore menu state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const menuIds = ['agents-menu', 'reports-menu', 'settings-menu'];
            menuIds.forEach(menuId => {
                const saved = localStorage.getItem('menu-' + menuId);
                if (saved === 'true') {
                    const menu = document.getElementById(menuId);
                    const button = menu.previousElementSibling;
                    menu.classList.add('active');
                    button.classList.add('expanded');
                }
            });
        });
    </script>
</body>
</html>
