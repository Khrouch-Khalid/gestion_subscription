<?php
// admin/view_all_clients.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$messageType = '';

// Get search filter
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$agent_filter = $_GET['agent'] ?? '';

// Get all agents for filter dropdown
$agentsStmt = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'agent' ORDER BY full_name");
$agents = $agentsStmt->fetchAll();

// Get all clients with their agent info
$query = "
    SELECT c.*, u.full_name as agent_name, u.email as agent_email,
           COUNT(s.subscription_id) as subscription_count,
           SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions
    FROM clients c
    JOIN users u ON c.agent_id = u.user_id
    LEFT JOIN subscriptions s ON c.client_id = s.client_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.city LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

if (!empty($agent_filter)) {
    $query .= " AND c.agent_id = ?";
    $params[] = (int)$agent_filter;
}

$query .= " GROUP BY c.client_id ORDER BY c.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM clients
";
$statsStmt = $conn->query($statsQuery);
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Clients - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .overview-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .overview-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .overview-subtitle {
            font-size: 13px;
            color: #999;
            margin-bottom: 25px;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
        }

        .overview-card {
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #eee;
            background-color: #f9f9f9;
            text-align: center;
        }

        .overview-card-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .overview-card-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .filter-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .filter-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-box form {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 300px;
            flex-wrap: wrap;
        }

        .filter-box input,
        .filter-box select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
        }

        .filter-box input {
            flex: 1;
            min-width: 200px;
        }

        .filter-box select {
            min-width: 150px;
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
            background-color: #667eea;
            color: white;
        }

        .filter-box button:hover {
            background-color: #5568d3;
        }

        .filter-box a {
            background-color: #6c757d;
            color: white;
        }

        .filter-box a:hover {
            background-color: #5a6268;
        }

        .data-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            margin: 0 0 20px 0;
        }

        .table-simple {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table-simple thead {
            background-color: #f5f5f5;
        }

        .table-simple th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        .table-simple td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        .table-simple tbody tr:hover {
            background-color: #fafafa;
        }

        .badge-sm {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
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
                <a href="admin_dashboard.php" style="<?php echo $current_page === 'admin_dashboard.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📊 Dashboard</a>
                
                <!-- Agents Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'agents-menu')">
                        👥 Agents
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="agents-menu">
                        <a href="manage_agents.php">Manage Agents</a>
                        <a href="create_agent.php">Create New Agent</a>
                        <a href="view_all_clients.php" class="active">View All Clients</a>
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
                <h1>All Clients</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">Overview</div>
                    <div class="overview-subtitle">All clients across your system.</div>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-card-label">Total Clients</div>
                            <div class="overview-card-value"><?php echo $stats['total']; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">🟢 Active</div>
                            <div class="overview-card-value"><?php echo $stats['active'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">🔴 Inactive</div>
                            <div class="overview-card-value"><?php echo $stats['inactive'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-box">
                        <form style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%;">
                            <input type="text" name="search" placeholder="Search by name, email, phone, or city..." 
                                   value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 250px;">
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <select name="agent">
                                <option value="">All Agents</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['user_id']; ?>" 
                                            <?php echo $agent_filter == $agent['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($agent['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">🔍 Search</button>
                        </form>
                        <?php if (!empty($search) || !empty($status_filter) || !empty($agent_filter)): ?>
                            <a href="view_all_clients.php">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Clients Table -->
                <div class="data-section">
                    <div class="section-title">All Clients (<?php echo count($clients); ?>)</div>
                    <?php if (empty($clients)): ?>
                        <div class="empty-state">
                            <h3>No clients found</h3>
                            <p>No clients match your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <table class="table-simple">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th>Agent</th>
                                    <th>Subscriptions</th>
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
                                            <a href="view_agent_clients.php?agent_id=<?php echo $client['agent_id']; ?>" 
                                               style="color: #667eea; text-decoration: none;">
                                                <?php echo htmlspecialchars($client['agent_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge-sm badge-info">
                                                <?php echo $client['subscription_count']; ?> (<?php echo $client['active_subscriptions'] ?? 0; ?> active)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($client['status'] === 'active'): ?>
                                                <span class="badge-sm badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-sm badge-inactive">Inactive</span>
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
                    <?php endif; ?>
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
