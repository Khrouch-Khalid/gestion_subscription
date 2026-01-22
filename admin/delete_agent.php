<?php
// admin/delete_agent.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$agent = null;
$error = '';
$success = false;

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
    
    // Get client count
    $clientStmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE agent_id = ?");
    $clientStmt->execute([$agent_id]);
    $clientCount = $clientStmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle confirmation deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($clientCount > 0) {
        $error = "Cannot delete agent with existing clients. Please reassign or delete their clients first.";
    } else {
        try {
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'agent'");
            $deleteStmt->execute([$agent_id]);
            $success = true;
            header('Location: manage_agents.php?message=Agent+deleted+successfully');
            exit();
        } catch (PDOException $e) {
            $error = "Error deleting agent: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Agent - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .agent-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .agent-info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .agent-info-row:last-child {
            border-bottom: none;
        }
        
        .agent-info-label {
            font-weight: 600;
            color: #333;
            min-width: 150px;
        }
        
        .agent-info-value {
            color: #666;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .warning-box h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        .warning-box p {
            margin: 5px 0;
            color: #856404;
            font-size: 14px;
        }
        
        .warning-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .warning-box li {
            margin: 5px 0;
        }
        
        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-box h4 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        
        .error-box p {
            margin: 0;
            color: #721c24;
        }
        
        .client-warning {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .client-warning h4 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        
        .client-warning p {
            margin: 5px 0;
            color: #721c24;
            font-size: 14px;
        }
        
        .checkbox-confirmation {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .checkbox-confirmation input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-confirmation label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
            color: #333;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
        }
        
        .form-actions a,
        .form-actions button {
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
            background-color: #dc3545;
            color: white;
        }
        
        .form-actions button[type="submit"]:hover:not(:disabled) {
            background-color: #c82333;
        }
        
        .form-actions button[type="submit"]:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .form-actions a {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions a:hover {
            background-color: #5a6268;
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
                <h1>Delete Agent</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="delete-container">
                    <!-- Error Messages -->
                    <?php if (!empty($error)): ?>
                        <div class="error-box">
                            <h4>⚠️ Error</h4>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">🗑️ Delete Agent - Confirmation Required</h2>
                        </div>
                        <div class="card-body">
                            <!-- Agent Information -->
                            <div class="agent-info">
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Full Name:</span>
                                    <span class="agent-info-value"><strong><?php echo htmlspecialchars($agent['full_name']); ?></strong></span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Username:</span>
                                    <span class="agent-info-value"><?php echo htmlspecialchars($agent['username']); ?></span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Email:</span>
                                    <span class="agent-info-value"><?php echo htmlspecialchars($agent['email']); ?></span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Phone:</span>
                                    <span class="agent-info-value"><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Status:</span>
                                    <span class="agent-info-value">
                                        <?php if ($agent['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Created:</span>
                                    <span class="agent-info-value"><?php echo formatDate($agent['created_at']); ?></span>
                                </div>
                                <div class="agent-info-row">
                                    <span class="agent-info-label">Clients:</span>
                                    <span class="agent-info-value">
                                        <span class="badge badge-info"><?php echo $clientCount; ?></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Client Warning -->
                            <?php if ($clientCount > 0): ?>
                                <div class="client-warning">
                                    <h4>❌ Cannot Delete - Active Clients</h4>
                                    <p>This agent has <strong><?php echo $clientCount; ?></strong> active client(s).</p>
                                    <p style="margin-top: 10px;">To delete this agent, you must first:</p>
                                    <ul style="margin-top: 10px; margin-bottom: 0;">
                                        <li>Reassign their clients to another agent, OR</li>
                                        <li>Delete all their clients</li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <!-- Warning Box -->
                                <div class="warning-box">
                                    <h4>⚠️ Warning: Permanent Action</h4>
                                    <p>You are about to permanently delete this agent account.</p>
                                    <ul>
                                        <li>This action cannot be undone</li>
                                        <li>All agent's profile data will be deleted</li>
                                        <li>The agent will no longer be able to log in</li>
                                        <li>Historical records will be preserved</li>
                                    </ul>
                                </div>

                                <!-- Confirmation Form -->
                                <form method="POST" action="">
                                    <!-- Checkbox Confirmation -->
                                    <div class="checkbox-confirmation">
                                        <input type="checkbox" id="confirm_understand" name="confirm_understand" required>
                                        <label for="confirm_understand">
                                            I understand this action is permanent and cannot be undone
                                        </label>
                                    </div>

                                    <!-- Hidden Input -->
                                    <input type="hidden" name="confirm_delete" value="1">

                                    <!-- Form Actions -->
                                    <div class="form-actions">
                                        <button type="submit" id="delete-btn" disabled class="btn btn-danger">
                                            🗑️ Delete Agent Permanently
                                        </button>
                                        <a href="manage_agents.php" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <?php if ($clientCount > 0): ?>
                                <!-- Cancel Button Only -->
                                <div class="form-actions" style="margin-top: 20px;">
                                    <a href="manage_agents.php" class="btn btn-secondary">Back to Agents</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Enable delete button only when checkbox is checked
        const confirmCheckbox = document.getElementById('confirm_understand');
        const deleteBtn = document.getElementById('delete-btn');

        if (confirmCheckbox) {
            confirmCheckbox.addEventListener('change', function() {
                deleteBtn.disabled = !this.checked;
            });
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
