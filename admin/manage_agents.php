<?php
// admin/manage_agents.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$messageType = '';

// Handle delete agent
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $agent_id = (int)$_GET['id'];
    
    try {
        // First check if agent has clients
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE agent_id = ?");
        $checkStmt->execute([$agent_id]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            $message = "Cannot delete agent with existing clients. Please reassign or delete their clients first.";
            $messageType = "danger";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'agent'");
            $deleteStmt->execute([$agent_id]);
            $message = "Agent deleted successfully!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Error deleting agent: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $agent_id = (int)$_GET['id'];
    
    try {
        // Get current status
        $statusStmt = $conn->prepare("SELECT status FROM users WHERE user_id = ? AND role = 'agent'");
        $statusStmt->execute([$agent_id]);
        $agent = $statusStmt->fetch();
        
        if ($agent) {
            $newStatus = $agent['status'] === 'active' ? 'inactive' : 'active';
            $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $updateStmt->execute([$newStatus, $agent_id]);
            $message = "Agent status updated to " . ucfirst($newStatus) . "!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Error updating agent status: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get search filter
$search = sanitizeInput($_GET['search'] ?? '');

// Get all agents with their client count
if (!empty($search)) {
    $query = "
        SELECT u.*, COUNT(c.client_id) as client_count
        FROM users u
        LEFT JOIN clients c ON u.user_id = c.agent_id
        WHERE u.role = 'agent' 
        AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $query = "
        SELECT u.*, COUNT(c.client_id) as client_count
        FROM users u
        LEFT JOIN clients c ON u.user_id = c.agent_id
        WHERE u.role = 'agent'
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ";
    $stmt = $conn->query($query);
}

$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Agents - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-buttons a,
        .action-buttons button {
            margin: 0;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-box button {
            padding: 10px 20px;
        }
        
        .table td:last-child {
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
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
                <a href="settings.php">⚙️ Settings</a>
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Manage Agents</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Search Box -->
                <div class="search-box">
                    <form style="display: flex; gap: 10px; width: 100%;">
                        <input type="text" name="search" placeholder="Search agents by name, email, or username..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="manage_agents.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Card Container -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0;">Agents (<?php echo count($agents); ?>)</h2>
                        <a href="create_agent.php" class="btn btn-success">➕ Create New Agent</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agents)): ?>
                            <div class="empty-state">
                                <h3>No agents found</h3>
                                <p>Start by creating a new agent</p>
                                <a href="create_agent.php" class="btn btn-primary" style="margin-top: 15px;">Create Agent</a>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Clients</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agents as $agent): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($agent['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($agent['username']); ?></td>
                                                <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                                <td><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo $agent['client_count']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($agent['status'] === 'active'): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($agent['created_at']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit_agent.php?id=<?php echo $agent['user_id']; ?>" 
                                                           class="btn btn-primary btn-sm">✏️ Edit</a>
                                                        
                                                        <?php if ($agent['client_count'] == 0): ?>
                                                            <a href="manage_agents.php?delete=1&id=<?php echo $agent['user_id']; ?>" 
                                                               class="btn btn-danger btn-sm" 
                                                               onclick="return confirm('Are you sure you want to delete this agent?');">
                                                                🗑️ Delete
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm" disabled title="Cannot delete agent with clients">
                                                                🗑️ Delete
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
