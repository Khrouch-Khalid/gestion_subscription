<?php
// admin/edit_agent.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$errors = [];
$success = false;
$agent = null;

// Get agent ID from URL
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agent_id) {
    header('Location: manage_agents.php');
    exit();
}

// Fetch agent information
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'agent'");
    $stmt->execute([$agent_id]);
    $agent = $stmt->fetch();
    
    if (!$agent) {
        header('Location: manage_agents.php');
        exit();
    }
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists (excluding current agent)
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $checkStmt->execute([$email, $agent_id]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Email already exists";
        }
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status";
    }
    
    // Password validation (only if provided)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } elseif ($password !== $password_confirm) {
            $errors[] = "Passwords do not match";
        }
    }

    // If no errors, update the agent
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                // Update with password change
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, status = ?, password = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$full_name, $email, $phone, $status, $hashedPassword, $agent_id]);
            } else {
                // Update without password change
                $updateStmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, status = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $updateStmt->execute([$full_name, $email, $phone, $status, $agent_id]);
            }
            
            $success = true;
            
            // Refresh agent data
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'agent'");
            $stmt->execute([$agent_id]);
            $agent = $stmt->fetch();
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get client count
$clientStmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE agent_id = ?");
$clientStmt->execute([$agent_id]);
$clientCount = $clientStmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Agent - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
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
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .info-box {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .info-box p {
            margin: 0;
            color: #0c5460;
            font-size: 14px;
        }
        
        .password-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .password-section h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .form-actions button,
        .form-actions a {
            flex: 1;
            padding: 12px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
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
        
        .success-message {
            background-color: #e8f5e9;
            border-left: 4px solid #27ae60;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message h4 {
            margin: 0 0 5px 0;
            color: #27ae60;
        }
        
        .success-message p {
            margin: 0;
            color: #27ae60;
            font-size: 14px;
        }
        
        .agent-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
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
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'agents-menu')">
                        👥 Agents
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="agents-menu">
                        <a href="manage_agents.php" class="active">Manage Agents</a>
                        <a href="create_agent.php">Create New Agent</a>
                        <a href="view_all_clients.php">View All Clients</a>
                    </div>
                </div>
                
                <a href="reports.php" style="<?php echo $current_page === 'reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>
                
                <!-- Settings Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'settings-menu')">
                        ⚙️ Settings
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu" id="settings-menu">
                        <a href="settings.php">General Settings</a>
                        <a href="settings.php?tab=system">System Settings</a>
                    </div>
                </div>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Edit Agent</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="form-container">
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="success-message">
                            <h4>✅ Agent Updated Successfully!</h4>
                            <p>All changes have been saved.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Agent Stats -->
                    <div class="agent-stats">
                        <div class="stat">
                            <div class="stat-label">Status</div>
                            <div class="stat-value">
                                <?php echo $agent['status'] === 'active' ? '✅ Active' : '❌ Inactive'; ?>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Clients</div>
                            <div class="stat-value"><?php echo $clientCount; ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Member Since</div>
                            <div class="stat-value" style="font-size: 14px; margin-top: 5px;">
                                <?php echo formatDate($agent['created_at']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">✏️ Edit Agent Information</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <!-- Full Name -->
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($agent['full_name'] ?? ''); ?>" 
                                           required placeholder="Enter full name">
                                </div>

                                <!-- Username (Read-only) -->
                                <div class="form-group">
                                    <label for="username">Username (Cannot be changed)</label>
                                    <input type="text" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($agent['username'] ?? ''); ?>" 
                                           disabled placeholder="Username" style="background-color: #f0f0f0;">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($agent['email'] ?? ''); ?>" 
                                           required placeholder="Enter email address">
                                </div>

                                <!-- Phone -->
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($agent['phone'] ?? ''); ?>" 
                                           placeholder="Enter phone number">
                                </div>

                                <!-- Status -->
                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select id="status" name="status" required>
                                        <option value="active" <?php echo $agent['status'] === 'active' ? 'selected' : ''; ?>>
                                            ✅ Active
                                        </option>
                                        <option value="inactive" <?php echo $agent['status'] === 'inactive' ? 'selected' : ''; ?>>
                                            ❌ Inactive
                                        </option>
                                    </select>
                                </div>

                                <!-- Password Change Section -->
                                <div class="password-section">
                                    <h4>🔐 Change Password (Optional)</h4>
                                    
                                    <div class="form-group">
                                        <label for="password">New Password</label>
                                        <input type="password" id="password" name="password" 
                                               placeholder="Leave blank to keep current password (min 6 characters)">
                                    </div>

                                    <div class="form-group">
                                        <label for="password_confirm">Confirm Password</label>
                                        <input type="password" id="password_confirm" name="password_confirm" 
                                               placeholder="Confirm new password">
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">✅ Save Changes</button>
                                    <a href="manage_agents.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>

                            <!-- Danger Zone -->
                            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                                <h4 style="color: #dc3545; margin-bottom: 15px;">⚠️ Danger Zone</h4>
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                    <?php if ($clientCount > 0): ?>
                                        This agent cannot be deleted because they have <strong><?php echo $clientCount; ?></strong> active client(s).
                                    <?php else: ?>
                                        Permanently delete this agent account. This action cannot be undone.
                                    <?php endif; ?>
                                </p>
                                <a href="delete_agent.php?id=<?php echo $agent['user_id']; ?>" 
                                   class="btn btn-danger" 
                                   <?php echo $clientCount > 0 ? 'style="opacity: 0.5; cursor: not-allowed; pointer-events: none;"' : ''; ?>>
                                    🗑️ Delete Agent
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
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
