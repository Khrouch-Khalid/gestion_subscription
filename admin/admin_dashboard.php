<?php
// admin/admin_dashboard.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);

// Get Statistics
// Total Agents
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'");
$totalAgents = $stmt->fetch()['count'];

// Total Clients
$stmt = $conn->query("SELECT COUNT(*) as count FROM clients");
$totalClients = $stmt->fetch()['count'];

// Active Subscriptions
$stmt = $conn->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'");
$activeSubscriptions = $stmt->fetch()['count'];

// Total Monthly Revenue (calculate based on subscription types)
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

// Expiring Soon (next 30 days)
$stmt = $conn->query("
    SELECT COUNT(*) as count 
    FROM subscriptions 
    WHERE status = 'active' 
    AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$expiringSoon = $stmt->fetch()['count'];

// Recent Agents (last 5)
$stmt = $conn->query("
    SELECT user_id, full_name, email, created_at, status
    FROM users 
    WHERE role = 'agent' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentAgents = $stmt->fetchAll();

// Recent Clients (last 5)
$stmt = $conn->query("
    SELECT c.*, u.full_name as agent_name
    FROM clients c
    JOIN users u ON c.agent_id = u.user_id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentClients = $stmt->fetchAll();

// Top Agents by Client Count
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

// Subscription Status Breakdown
$stmt = $conn->query("
    SELECT status, COUNT(*) as count
    FROM subscriptions
    GROUP BY status
");
$subscriptionStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Upcoming Renewals (next 7 days)
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

                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">Overview</div>
                    <div class="overview-subtitle">A quick snapshot of the latest activity and key metrics.</div>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-card-label">👥 Total Agents</div>
                            <div class="overview-card-value"><?php echo $totalAgents; ?></div>
                        </div>
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
                            <div class="overview-card-label">⏰ Expiring</div>
                            <div class="overview-card-value"><?php echo $expiringSoon; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Left Column -->
                    <div>
                        <!-- Recent Agents Section -->
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
                            <!-- Top Agents -->
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