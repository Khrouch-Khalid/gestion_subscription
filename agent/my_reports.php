<?php
// agent/my_reports.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'overview';

// Get overall statistics
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM clients WHERE agent_id = ?) as total_clients,
        (SELECT COUNT(*) FROM subscriptions WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)) as total_subscriptions,
        (SELECT COUNT(*) FROM subscriptions WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?) AND status = 'active') as active_subscriptions,
        (SELECT COUNT(*) FROM subscriptions WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?) AND status = 'expired') as expired_subscriptions,
        (SELECT SUM(price) FROM subscriptions WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?) AND status = 'active') as total_revenue
");
$stmt->execute([$agent_id, $agent_id, $agent_id, $agent_id, $agent_id]);
$overall_stats = $stmt->fetch();

// Get expiring subscriptions (next 30 days)
$stmt = $conn->prepare("
    SELECT s.*, c.full_name as client_name 
    FROM subscriptions s
    JOIN clients c ON s.client_id = c.client_id
    WHERE c.agent_id = ? 
    AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND s.status != 'expired'
    ORDER BY s.end_date ASC
");
$stmt->execute([$agent_id]);
$expiring_subscriptions = $stmt->fetchAll();

// Get revenue by month (last 12 months)
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        DATE_FORMAT(created_at, '%M %Y') as month_label,
        SUM(price) as revenue
    FROM subscriptions
    WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$agent_id]);
$revenue_by_month = $stmt->fetchAll();

// Get clients with subscription count and revenue
$stmt = $conn->prepare("
    SELECT 
        c.*,
        COUNT(s.subscription_id) as subscription_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
        SUM(CASE WHEN s.status = 'active' THEN s.price ELSE 0 END) as total_revenue
    FROM clients c
    LEFT JOIN subscriptions s ON c.client_id = s.client_id
    WHERE c.agent_id = ?
    GROUP BY c.client_id
    ORDER BY c.created_at DESC
");
$stmt->execute([$agent_id]);
$clients_report = $stmt->fetchAll();

// Get all subscriptions for subscriptions report
$stmt = $conn->prepare("
    SELECT s.*, c.full_name as client_name
    FROM subscriptions s
    JOIN clients c ON s.client_id = c.client_id
    WHERE c.agent_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$agent_id]);
$subscriptions_report = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - Subscription Manager</title>
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

        .data-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
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

        .badge-expired {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .report-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .report-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
            text-decoration: none;
        }
        
        .report-btn:hover {
            border-color: #ff6b5b;
            color: #ff6b5b;
        }
        
        .report-btn.active {
            background-color: #ff6b5b;
            color: white;
            border-color: #ff6b5b;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        
        .revenue-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .revenue-row:last-child {
            border-bottom: none;
        }
        
        .month-label {
            font-weight: 600;
            color: #333;
            width: 100px;
        }
        
        .revenue-bar {
            flex: 1;
            height: 30px;
            background: #ff6b5b;
            border-radius: 4px;
            margin: 0 15px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            color: white;
            font-weight: 600;
            font-size: 12px;
        }
        
        .revenue-amount {
            font-weight: 600;
            color: #333;
            text-align: right;
            width: 100px;
        }
        
        .expiring-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 10px;
        }
        
        .expiring-soon {
            background-color: #ffc107;
            color: #333;
        }
        
        .expiring-critical {
            background-color: #dc3545;
            color: white;
        }

        @media (max-width: 768px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
            
            .report-selector {
                flex-direction: column;
            }
            
            .report-btn {
                width: 100%;
            }
            
            .revenue-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .month-label, .revenue-amount {
                width: 100%;
            }
            
            .revenue-bar {
                width: 100%;
                margin: 10px 0;
            }
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
                <a href="agent_dashboard.php">📊 Dashboard</a>
                
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
                        <a href="add_subscription.php">Add New Subscription</a>
                    </div>
                </div>
                
                <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>
                <a href="agent_settings.php">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>My Reports</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Report Selector -->
                <div class="report-selector">
                    <a href="my_reports.php?type=overview" class="report-btn <?php echo $report_type === 'overview' ? 'active' : ''; ?>">Overview</a>
                    <a href="my_reports.php?type=clients" class="report-btn <?php echo $report_type === 'clients' ? 'active' : ''; ?>">Clients</a>
                    <a href="my_reports.php?type=subscriptions" class="report-btn <?php echo $report_type === 'subscriptions' ? 'active' : ''; ?>">Subscriptions</a>
                    <a href="my_reports.php?type=revenue" class="report-btn <?php echo $report_type === 'revenue' ? 'active' : ''; ?>">Revenue</a>
                    <a href="my_reports.php?type=expiring" class="report-btn <?php echo $report_type === 'expiring' ? 'active' : ''; ?>">Expiring Soon</a>
                </div>

                <?php if ($report_type === 'overview'): ?>
                    <!-- Overview Report -->
                    <div class="overview-section">
                        <div class="overview-title">📊 Reports Overview</div>
                        <div class="overview-subtitle">Your reports summary at a glance</div>
                        <div class="overview-grid">
                            <div class="overview-card">
                                <div class="overview-card-label">👥 Total Clients</div>
                                <div class="overview-card-value"><?php echo $overall_stats['total_clients'] ?? 0; ?></div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-card-label">📋 Total Subscriptions</div>
                                <div class="overview-card-value"><?php echo $overall_stats['total_subscriptions'] ?? 0; ?></div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-card-label">✅ Active Subscriptions</div>
                                <div class="overview-card-value"><?php echo $overall_stats['active_subscriptions'] ?? 0; ?></div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-card-label">❌ Expired Subscriptions</div>
                                <div class="overview-card-value"><?php echo $overall_stats['expired_subscriptions'] ?? 0; ?></div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-card-label">💰 Total Revenue (Active)</div>
                                <div class="overview-card-value"><?php echo formatCurrency($overall_stats['total_revenue'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Expiring Soon Summary -->
                    <div class="data-section">
                        <div class="section-title">
                            <span>⚠️ Subscriptions Expiring in Next 30 Days</span>
                        </div>
                        <?php if (empty($expiring_subscriptions)): ?>
                            <div class="no-data">No subscriptions expiring in the next 30 days</div>
                        <?php else: ?>
                            <table class="table-simple">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Subscription</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>End Date</th>
                                        <th>Days Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiring_subscriptions as $sub): ?>
                                        <?php
                                        $end_date = strtotime($sub['end_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $days_left = ceil(($end_date - $today) / (60 * 60 * 24));
                                        $urgency_class = $days_left <= 7 ? 'expiring-critical' : 'expiring-soon';
                                        ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                        <td><?php echo formatCurrency($sub['price']); ?></td>
                                        <td><?php echo formatDate($sub['end_date']); ?></td>
                                        <td>
                                            <span class="expiring-badge <?php echo $urgency_class; ?>">
                                                <?php echo $days_left; ?> days
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php elseif ($report_type === 'clients'): ?>
                    <!-- Clients Report -->
                    <div class="data-section">
                        <div class="section-title">
                            <span>👥 Client Report</span>
                        </div>
                        <?php if (empty($clients_report)): ?>
                            <div class="no-data">No clients found</div>
                        <?php else: ?>
                            <table class="table-simple">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Total Subscriptions</th>
                                        <th>Active</th>
                                        <th>Total Revenue</th>
                                        <th>Status</th>
                                        <th>Member Since</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients_report as $client): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($client['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td><?php echo $client['subscription_count'] ?? 0; ?></td>
                                        <td><?php echo $client['active_subscriptions'] ?? 0; ?></td>
                                        <td><?php echo formatCurrency($client['total_revenue'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge-sm badge-<?php echo strtolower($client['status']); ?>">
                                                <?php echo ucfirst($client['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($client['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php elseif ($report_type === 'subscriptions'): ?>
                    <!-- Subscriptions Report -->
                    <div class="data-section">
                        <div class="section-title">
                            <span>📋 Subscriptions Report</span>
                        </div>
                        <?php if (empty($subscriptions_report)): ?>
                            <div class="no-data">No subscriptions found</div>
                        <?php else: ?>
                            <table class="table-simple">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Subscription Name</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Auto-Renew</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions_report as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                        <td><?php echo formatCurrency($sub['price']); ?></td>
                                        <td><?php echo formatDate($sub['start_date']); ?></td>
                                        <td><?php echo formatDate($sub['end_date']); ?></td>
                                        <td>
                                            <span class="badge-sm badge-<?php echo strtolower($sub['status']); ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $sub['auto_renew'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php elseif ($report_type === 'revenue'): ?>
                    <!-- Revenue Report -->
                    <div class="data-section">
                        <div class="section-title">
                            <span>💰 Revenue Report (Last 12 Months)</span>
                        </div>
                        <?php
                        // Calculate total revenue
                        $total_revenue = 0;
                        foreach ($revenue_by_month as $month) {
                            $total_revenue += $month['revenue'];
                        }
                        // Find max revenue for scaling
                        $max_revenue = 0;
                        foreach ($revenue_by_month as $month) {
                            if ($month['revenue'] > $max_revenue) {
                                $max_revenue = $month['revenue'];
                            }
                        }
                        ?>
                        
                        <?php if (empty($revenue_by_month)): ?>
                            <div class="no-data">No revenue data found</div>
                        <?php else: ?>
                            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #eee;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-size: 14px; color: #666;">Total Revenue (12 Months)</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #ff6b5b;"><?php echo formatCurrency($total_revenue); ?></div>
                                </div>
                            </div>

                            <div style="margin-bottom: 20px;">
                                <?php foreach ($revenue_by_month as $month): ?>
                                    <?php
                                    $percentage = $max_revenue > 0 ? ($month['revenue'] / $max_revenue) * 100 : 0;
                                    ?>
                                    <div class="revenue-row">
                                        <div class="month-label"><?php echo htmlspecialchars($month['month_label']); ?></div>
                                        <div class="revenue-bar" style="width: <?php echo $percentage; ?>%;">
                                            <?php echo formatCurrency($month['revenue']); ?>
                                        </div>
                                        <div class="revenue-amount"><?php echo formatCurrency($month['revenue']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($report_type === 'expiring'): ?>
                    <!-- Expiring Soon Report -->
                    <div class="data-section">
                        <div class="section-title">
                            <span>⚠️ Subscriptions Expiring Soon (Next 30 Days)</span>
                        </div>
                        <?php if (empty($expiring_subscriptions)): ?>
                            <div class="no-data">No subscriptions expiring in the next 30 days</div>
                        <?php else: ?>
                            <table class="table-simple">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Subscription</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days Left</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiring_subscriptions as $sub): ?>
                                        <?php
                                        $end_date = strtotime($sub['end_date']);
                                        $today = strtotime(date('Y-m-d'));
                                        $days_left = ceil(($end_date - $today) / (60 * 60 * 24));
                                        $urgency_class = $days_left <= 7 ? 'expiring-critical' : 'expiring-soon';
                                        ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                        <td><?php echo formatCurrency($sub['price']); ?></td>
                                        <td><?php echo formatDate($sub['start_date']); ?></td>
                                        <td><?php echo formatDate($sub['end_date']); ?></td>
                                        <td>
                                            <span class="expiring-badge <?php echo $urgency_class; ?>">
                                                <?php echo $days_left; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-sm badge-<?php echo strtolower($sub['status']); ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
