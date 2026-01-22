<?php
// admin/view_agent_clients.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$agent = null;
$clients = [];
$errors = [];

// Get agent ID from URL
$agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;

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

// Get search filter
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Get agent's clients
try {
    if (!empty($search)) {
        if (!empty($status_filter)) {
            $query = "
                SELECT * FROM clients 
                WHERE agent_id = ? 
                AND status = ?
                AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)
                ORDER BY created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $searchTerm = "%$search%";
            $stmt->execute([$agent_id, $status_filter, $searchTerm, $searchTerm, $searchTerm]);
        } else {
            $query = "
                SELECT * FROM clients 
                WHERE agent_id = ? 
                AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)
                ORDER BY created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $searchTerm = "%$search%";
            $stmt->execute([$agent_id, $searchTerm, $searchTerm, $searchTerm]);
        }
    } else {
        if (!empty($status_filter)) {
            $query = "
                SELECT * FROM clients 
                WHERE agent_id = ? AND status = ?
                ORDER BY created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute([$agent_id, $status_filter]);
        } else {
            $query = "
                SELECT * FROM clients 
                WHERE agent_id = ?
                ORDER BY created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute([$agent_id]);
        }
    }
    
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error fetching clients: " . $e->getMessage();
}

// Get client statistics
try {
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM clients WHERE agent_id = ?
    ");
    $statsStmt->execute([$agent_id]);
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $errors[] = "Error fetching statistics: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Clients - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .agent-header-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .agent-header-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .agent-header-info p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-top: 3px solid #667eea;
        }
        
        .stat-card.active {
            border-top-color: #28a745;
        }
        
        .stat-card.inactive {
            border-top-color: #dc3545;
        }
        
        .stat-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .filter-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-box form {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 300px;
        }
        
        .filter-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-box select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
        }
        
        .filter-box button,
        .filter-box a {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-box button {
            background-color: #007bff;
            color: white;
        }
        
        .filter-box button:hover {
            background-color: #0056b3;
        }
        
        .filter-box a {
            background-color: #6c757d;
            color: white;
        }
        
        .filter-box a:hover {
            background-color: #5a6268;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-buttons a {
            margin: 0;
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
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
                <h1>Agent Clients</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="admin_dashboard.php">Dashboard</a> /
                    <a href="manage_agents.php">Agents</a> /
                    <span><?php echo htmlspecialchars($agent['full_name']); ?>'s Clients</span>
                </div>

                <!-- Agent Information -->
                <div class="agent-header-info">
                    <h3><?php echo htmlspecialchars($agent['full_name']); ?></h3>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($agent['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($agent['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></p>
                </div>

                <!-- Statistics -->
                <?php if (!empty($stats)): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Clients</div>
                            <div class="stat-value"><?php echo $stats['total']; ?></div>
                        </div>
                        <div class="stat-card active">
                            <div class="stat-label">Active</div>
                            <div class="stat-value"><?php echo $stats['active'] ?? 0; ?></div>
                        </div>
                        <div class="stat-card inactive">
                            <div class="stat-label">Inactive</div>
                            <div class="stat-value"><?php echo $stats['inactive'] ?? 0; ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Box -->
                <div class="filter-box">
                    <form style="display: flex; gap: 10px; flex: 1;">
                        <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button type="submit">🔍 Search</button>
                        <input type="hidden" name="agent_id" value="<?php echo $agent_id; ?>">
                    </form>
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="view_agent_clients.php?agent_id=<?php echo $agent_id; ?>">Clear Filters</a>
                    <?php endif; ?>
                </div>

                <!-- Clients Table -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0;">Clients (<?php echo count($clients); ?>)</h2>
                        <a href="manage_agents.php" class="btn btn-secondary btn-sm">← Back to Agents</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($clients)): ?>
                            <div class="empty-state">
                                <h3>No clients found</h3>
                                <p>This agent hasn't added any clients yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>City</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $client): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($client['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($client['city'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($client['status'] === 'active'): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($client['created_at']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="../agent/view_client.php?id=<?php echo $client['client_id']; ?>" 
                                                           class="btn btn-primary btn-sm">👁️ View</a>
                                                        <a href="../agent/edit_client.php?id=<?php echo $client['client_id']; ?>" 
                                                           class="btn btn-info btn-sm">✏️ Edit</a>
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
