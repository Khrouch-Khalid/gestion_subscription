<?php
// admin/admin_dashboard.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);

// Get selected agent from GET or session
$selected_agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
$filter_mode = $selected_agent_id ? 'agent' : 'all';

// Get all agents for dropdown
$stmt = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'agent' ORDER BY full_name");
$allAgents = $stmt->fetchAll();

// Get Statistics based on selection
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Agent-specific stats
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE agent_id = ?");
    $stmt->execute([$selected_agent_id]);
    $totalClients = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)");
    $stmt->execute([$selected_agent_id]);
    $activeSubscriptions = $stmt->fetch()['count'];

    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE 
                WHEN subscription_type = 'Monthly' THEN price
                WHEN subscription_type = 'Quarterly' THEN price / 3
                WHEN subscription_type = 'Yearly' THEN price / 12
            END) as revenue
        FROM subscriptions 
        WHERE status = 'active'
        AND client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)
    ");
    $stmt->execute([$selected_agent_id]);
    $totalRevenue = $stmt->fetch()['revenue'] ?? 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subscriptions 
        WHERE status = 'expired'
        AND client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)
    ");
    $stmt->execute([$selected_agent_id]);
    $expiredSubscriptions = $stmt->fetch()['count'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subscriptions 
        WHERE status = 'active' 
        AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)
    ");
    $stmt->execute([$selected_agent_id]);
    $expiringSoon = $stmt->fetch()['count'];
    
    $totalAgents = 1;
} else {
    // Show all stats
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'");
    $totalAgents = $stmt->fetch()['count'];

    $stmt = $conn->query("SELECT COUNT(*) as count FROM clients");
    $totalClients = $stmt->fetch()['count'];

    $stmt = $conn->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
    $activeSubscriptions = $stmt->fetch()['count'];

    $stmt = $conn->query("
        SELECT 
            SUM(CASE 
                WHEN subscription_type = 'Monthly' THEN price
                WHEN subscription_type = 'Quarterly' THEN price / 3
                WHEN subscription_type = 'Yearly' THEN price / 12
            END) as revenue
        FROM subscriptions 
        WHERE status = 'active'
    ");
    $totalRevenue = $stmt->fetch()['revenue'] ?? 0;

    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM subscriptions 
        WHERE status = 'expired'
    ");
    $expiredSubscriptions = $stmt->fetch()['count'];

    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM subscriptions 
        WHERE status = 'active' 
        AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $expiringSoon = $stmt->fetch()['count'];
}

// Recent Agents (last 5)
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Show only the selected agent
    $stmt = $conn->prepare("
        SELECT user_id, full_name, email, created_at, status
        FROM users 
        WHERE role = 'agent' AND user_id = ?
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$selected_agent_id]);
    $recentAgents = $stmt->fetchAll();
} else {
    // Show all agents
    $stmt = $conn->query("
        SELECT user_id, full_name, email, created_at, status
        FROM users 
        WHERE role = 'agent' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentAgents = $stmt->fetchAll();
}

// Recent Clients (last 5)
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Show only clients of the selected agent
    $stmt = $conn->prepare("
        SELECT c.*, u.full_name as agent_name
        FROM clients c
        JOIN users u ON c.agent_id = u.user_id
        WHERE c.agent_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$selected_agent_id]);
    $recentClients = $stmt->fetchAll();
} else {
    // Show all clients
    $stmt = $conn->query("
        SELECT c.*, u.full_name as agent_name
        FROM clients c
        JOIN users u ON c.agent_id = u.user_id
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $recentClients = $stmt->fetchAll();
}

// Top Agents by Client Count
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Show only the selected agent
    $stmt = $conn->prepare("
        SELECT u.full_name, u.email, COUNT(c.client_id) as client_count
        FROM users u
        LEFT JOIN clients c ON u.user_id = c.agent_id
        WHERE u.role = 'agent' AND u.user_id = ?
        GROUP BY u.user_id
        ORDER BY client_count DESC
        LIMIT 5
    ");
    $stmt->execute([$selected_agent_id]);
    $topAgents = $stmt->fetchAll();
} else {
    // Show all agents
    $stmt = $conn->query("
        SELECT u.full_name, u.email, COUNT(c.client_id) as client_count
        FROM users u
        LEFT JOIN clients c ON u.user_id = c.agent_id
        WHERE u.role = 'agent'
        GROUP BY u.user_id
        ORDER BY client_count DESC
        LIMIT 5
    ");
    $topAgents = $stmt->fetchAll();
}

// Subscription Status Breakdown
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Show only subscriptions of the selected agent's clients
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count
        FROM subscriptions
        WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)
        GROUP BY status
    ");
    $stmt->execute([$selected_agent_id]);
    $subscriptionStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    // Show all subscriptions
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count
        FROM subscriptions
        GROUP BY status
    ");
    $subscriptionStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Upcoming Renewals (next 7 days)
if ($filter_mode === 'agent' && $selected_agent_id) {
    // Show only renewals for the selected agent's clients
    $stmt = $conn->prepare("
        SELECT s.*, c.full_name as client_name, u.full_name as agent_name
        FROM subscriptions s
        JOIN clients c ON s.client_id = c.client_id
        JOIN users u ON c.agent_id = u.user_id
        WHERE u.user_id = ?
        AND s.status = 'active'
        AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY s.end_date ASC
        LIMIT 10
    ");
    $stmt->execute([$selected_agent_id]);
    $upcomingRenewals = $stmt->fetchAll();
} else {
    // Show all upcoming renewals
    $stmt = $conn->query("
        SELECT s.*, c.full_name as client_name, u.full_name as agent_name
        FROM subscriptions s
        JOIN clients c ON s.client_id = c.client_id
        JOIN users u ON c.agent_id = u.user_id
        WHERE s.status = 'active'
        AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY s.end_date ASC
        LIMIT 10
    ");
    $upcomingRenewals = $stmt->fetchAll();
}

$pageTitle = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
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
                
                <a href="reports.php" style="<?php echo $current_page === 'reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>
                <a href="settings.php">⚙️ Settings</a>
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1><?php echo $pageTitle; ?></h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <?php 
                $flash = getFlashMessage();
                if ($flash): 
                ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Agent Selector -->
                <div style="margin-bottom: 30px; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);">
                    <form method="GET" action="admin_dashboard.php" style="display: flex; gap: 15px; align-items: center;">
                        <label for="agent_selector" style="font-weight: 600; color: #333;">Filter by Agent:</label>
                        <select id="agent_selector" name="agent_id" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 250px;">
                            <option value="">-- All Agents --</option>
                            <?php foreach ($allAgents as $agent): ?>
                                <option value="<?php echo $agent['user_id']; ?>" <?php echo $selected_agent_id == $agent['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($agent['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="padding: 10px 25px; background-color: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                            Filter
                        </button>
                        <?php if ($selected_agent_id): ?>
                            <a href="admin_dashboard.php" style="padding: 10px 25px; background-color: #ff6b5b; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none; transition: all 0.3s ease;">
                                Clear Filter
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">Overview</div>
                    <div class="overview-subtitle"><?php echo $selected_agent_id ? 'Agent-specific data' : 'A quick snapshot of the latest activity and key metrics.'; ?></div>
                    <div class="overview-grid">
                        <?php if (!$selected_agent_id): ?>
                            <div class="overview-card">
                                <div class="overview-card-label">👥 Total Agents</div>
                                <div class="overview-card-value"><?php echo $totalAgents; ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="overview-card">
                            <div class="overview-card-label">👤 Total Clients</div>
                            <div class="overview-card-value"><?php echo $totalClients; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">📋 Subscriptions</div>
                            <div class="overview-card-value"><?php echo $activeSubscriptions; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">💰 Revenue</div>
                            <div class="overview-card-value"><?php echo formatMoney($totalRevenue); ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">❌ Expired</div>
                            <div class="overview-card-value"><?php echo $expiredSubscriptions; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">⏰ Expiring Soon</div>
                            <div class="overview-card-value"><?php echo $expiringSoon; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Left Column -->
                    <div>
                        <?php if (!$selected_agent_id): ?>
                            <!-- Recent Agents Section (Only show when all agents selected) -->
                            <div class="data-section">
                                <div class="section-title">
                                    Recent Agents
                                    <a href="manage_agents.php">View All</a>
                                </div>
                                <?php if (empty($recentAgents)): ?>
                                    <p style="color: #999; text-align: center; padding: 40px 0;">No agents found</p>
                                <?php else: ?>
                                    <table class="table-simple">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentAgents as $agent): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                                    <td>
                                                        <span class="badge-sm <?php echo $agent['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                            <?php echo ucfirst($agent['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Recent Subscriptions (Only show when agent selected) -->
                            <div class="data-section">
                                <div class="section-title">
                                    Recent Subscriptions
                                </div>
                                <?php if (empty($recentClients)): ?>
                                    <p style="color: #999; text-align: center; padding: 40px 0;">No subscriptions found</p>
                                <?php else: ?>
                                    <table class="table-simple">
                                        <thead>
                                            <tr>
                                                <th>Client Name</th>
                                                <th>Subscription Type</th>
                                                <th>Status</th>
                                                <th>End Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Fetch recent subscriptions for selected agent
                                            $stmt = $conn->prepare("
                                                SELECT s.*, c.full_name as client_name
                                                FROM subscriptions s
                                                JOIN clients c ON s.client_id = c.client_id
                                                WHERE c.agent_id = ?
                                                ORDER BY s.created_at DESC
                                                LIMIT 5
                                            ");
                                            $stmt->execute([$selected_agent_id]);
                                            $recentSubscriptions = $stmt->fetchAll();
                                            foreach ($recentSubscriptions as $sub): 
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                                    <td>
                                                        <span class="badge-sm <?php echo $sub['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                            <?php echo ucfirst($sub['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($sub['end_date']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Recent Clients Section -->
                        <div class="data-section" style="margin-top: 30px;">
                            <div class="section-title">
                                Recent Clients
                                <a href="view_all_clients.php">View All</a>
                            </div>
                            <?php if (empty($recentClients)): ?>
                                <p style="color: #999; text-align: center; padding: 40px 0;">No clients found</p>
                            <?php else: ?>
                                <table class="table-simple">
                                    <thead>
                                        <tr>
                                            <th>Client Name</th>
                                            <th>Phone</th>
                                            <th>Agent</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentClients as $client): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($client['agent_name']); ?></td>
                                                <td>
                                                    <span class="badge-sm <?php echo $client['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                        <?php echo ucfirst($client['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <div class="stats-column">
                            <?php if (!$selected_agent_id): ?>
                                <!-- Top Agents (Only show when all agents selected) -->
                                <div class="data-section">
                                    <div class="section-title">Top Agents</div>
                                    <?php if (empty($topAgents)): ?>
                                        <p style="color: #999; text-align: center; padding: 40px 0;">No data available</p>
                                    <?php else: ?>
                                        <table class="table-simple">
                                            <thead>
                                                <tr>
                                                    <th>Agent Name</th>
                                                    <th>Clients</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topAgents as $agent): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                                        <td><strong><?php echo $agent['client_count']; ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Subscription Status -->
                            <div class="data-section">
                                <div class="section-title">Subscriptions Status</div>
                                <div class="stats-column" style="gap: 10px;">
                                    <div class="stat-box">
                                        <div class="stat-box-label">🟢 Active</div>
                                        <div class="stat-box-value"><?php echo $subscriptionStats['active'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">🔴 Expired</div>
                                        <div class="stat-box-value"><?php echo $subscriptionStats['expired'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-label">⛔ Cancelled</div>
                                        <div class="stat-box-value"><?php echo $subscriptionStats['cancelled'] ?? 0; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Renewals Section -->
                <div class="data-section">
                    <div class="section-title">Upcoming Renewals (Next 7 Days)</div>
                    <?php if (empty($upcomingRenewals)): ?>
                        <p style="color: #999; text-align: center; padding: 40px 0;">No upcoming renewals in the next 7 days</p>
                    <?php else: ?>
                        <table class="table-simple">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Subscription</th>
                                    <th>Agent</th>
                                    <th>End Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingRenewals as $renewal): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($renewal['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($renewal['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($renewal['agent_name']); ?></td>
                                        <td><?php echo formatDate($renewal['end_date']); ?></td>
                                        <td><?php echo formatMoney($renewal['price']); ?></td>
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
            
            // Highlight active menu item
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar-submenu a').forEach(link => {
                const href = link.getAttribute('href').split('/').pop();
                if (href === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>