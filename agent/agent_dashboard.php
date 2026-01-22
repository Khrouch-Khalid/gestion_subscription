<?php
// agent/agent_dashboard.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get agent's statistics
// Total Clients
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE agent_id = ?");
$stmt->execute([$agent_id]);
$totalClients = $stmt->fetch()['count'];

// Active Subscriptions
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscriptions s 
                       JOIN clients c ON s.client_id = c.client_id 
                       WHERE c.agent_id = ? AND s.status = 'active'");
$stmt->execute([$agent_id]);
$activeSubscriptions = $stmt->fetch()['count'];

// Total Revenue (active subscriptions)
$stmt = $conn->prepare("SELECT COALESCE(SUM(s.price), 0) as total FROM subscriptions s 
                       JOIN clients c ON s.client_id = c.client_id 
                       WHERE c.agent_id = ? AND s.status = 'active'");
$stmt->execute([$agent_id]);
$totalRevenue = $stmt->fetch()['total'];

// Expiring Subscriptions (next 30 days)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscriptions s 
                       JOIN clients c ON s.client_id = c.client_id 
                       WHERE c.agent_id = ? 
                       AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                       AND s.status = 'active'");
$stmt->execute([$agent_id]);
$expiringSubscriptions = $stmt->fetch()['count'];

// Get recent clients
$stmt = $conn->prepare("SELECT client_id, full_name, email, phone, created_at, status 
                       FROM clients 
                       WHERE agent_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5");
$stmt->execute([$agent_id]);
$recentClients = $stmt->fetchAll();

// Get expiring subscriptions for display
$stmt = $conn->prepare("SELECT c.full_name, s.subscription_name, s.end_date, 
                              s.price, s.subscription_id, c.client_id
                       FROM clients c
                       JOIN subscriptions s ON c.client_id = s.client_id
                       WHERE c.agent_id = ?
                       AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                       AND s.status = 'active'
                       ORDER BY s.end_date ASC
                       LIMIT 10");
$stmt->execute([$agent_id]);
$expiringList = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Subscription Manager</title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title a {
            font-size: 12px;
            color: #ff6b5b;
            text-decoration: none;
            font-weight: 500;
        }

        .section-title a:hover {
            text-decoration: underline;
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
            background-color: #f9f9f9;
        }

        .badge-sm {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-active {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background-color: #ff6b5b;
            color: white;
            padding: 15px 20px;
            border-radius: 6px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .action-btn:hover {
            background-color: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .action-btn.btn-success {
            background-color: #28a745;
        }

        .action-btn.btn-success:hover {
            background-color: #218838;
        }

        .action-btn.btn-info {
            background-color: #17a2b8;
        }

        .action-btn.btn-info:hover {
            background-color: #138496;
        }

        .action-btn.btn-primary {
            background-color: #007bff;
        }

        .action-btn.btn-primary:hover {
            background-color: #0056b3;
        }

        .client-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .client-item:last-child {
            border-bottom: none;
        }

        .client-name {
            margin: 0 0 8px 0;
            font-weight: 600;
            font-size: 14px;
        }

        .client-name a {
            color: #ff6b5b;
            text-decoration: none;
        }

        .client-name a:hover {
            text-decoration: underline;
        }

        .client-info {
            color: #666;
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Agent Panel</h2>
                <p><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="agent_dashboard.php" style="<?php echo $current_page === 'agent_dashboard.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📊 Dashboard</a>
                
                <!-- Clients Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'clients-menu')">
                        👥 Clients
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu" id="clients-menu">
                        <a href="manage_clients.php">Manage Clients</a>
                        <a href="add_client.php">Add New Client</a>
                    </div>
                </div>
                
                <!-- Subscriptions Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'subscriptions-menu')">
                        📋 Subscriptions
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu" id="subscriptions-menu">
                        <a href="manage_subscriptions.php">Manage Subscriptions</a>
                        <a href="add_subscription.php">Add Subscription</a>
                    </div>
                </div>
                
                <!-- Reports Menu Group -->
                <a href="my_reports.php">📈 Reports</a>
                
                <!-- Settings -->
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Agent Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">📊 Dashboard Overview</div>
                    <div class="overview-subtitle">Your performance at a glance</div>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-card-label">👥 Total Clients</div>
                            <div class="overview-card-value"><?php echo $totalClients; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">📋 Active Subscriptions</div>
                            <div class="overview-card-value"><?php echo $activeSubscriptions; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">💰 Total Revenue</div>
                            <div class="overview-card-value"><?php echo formatCurrency($totalRevenue); ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">⏰ Expiring Soon</div>
                            <div class="overview-card-value"><?php echo $expiringSubscriptions; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Left Column -->
                    <div>
                        <!-- Expiring Subscriptions Section -->
                        <div class="data-section">
                            <div class="section-title">
                                ⏰ Expiring Subscriptions (Next 30 Days)
                            </div>
                            <?php if (empty($expiringList)): ?>
                                <p style="color: #999; text-align: center; padding: 40px 0;">No subscriptions expiring in the next 30 days.</p>
                            <?php else: ?>
                                <table class="table-simple">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Subscription</th>
                                            <th>Price</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expiringList as $sub): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sub['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                            <td><?php echo formatCurrency($sub['price']); ?></td>
                                            <td>
                                                <span class="badge-sm" style="background-color: #fff3cd; color: #856404;">
                                                    <?php echo date('d/m/Y', strtotime($sub['end_date'])); ?>
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
                        <!-- Recent Clients Section -->
                        <div class="data-section">
                            <div class="section-title">
                                👥 Recent Clients
                                <a href="manage_clients.php">View All</a>
                            </div>
                            <?php if (empty($recentClients)): ?>
                                <p style="color: #999; text-align: center; padding: 40px 0;">No clients yet.</p>
                            <?php else: ?>
                                <?php foreach ($recentClients as $client): ?>
                                <div class="client-item">
                                    <div class="client-name">
                                        <a href="view_client.php?id=<?php echo $client['client_id']; ?>">
                                            <?php echo htmlspecialchars($client['full_name']); ?>
                                        </a>
                                    </div>
                                    <span class="client-info"><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></span>
                                    <span class="client-info"><?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?></span>
                                    <span class="badge-sm <?php echo $client['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo ucfirst($client['status']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="action-grid">
                    <a href="manage_clients.php" class="action-btn">👥 Manage Clients</a>
                    <a href="add_client.php" class="action-btn btn-success">➕ Add New Client</a>
                    <a href="manage_subscriptions.php" class="action-btn btn-primary">📋 Manage Subscriptions</a>
                    <a href="my_reports.php" class="action-btn btn-info">📈 View Reports</a>
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
            const menuIds = ['clients-menu', 'subscriptions-menu'];
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
