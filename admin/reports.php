<?php
// admin/reports.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$reportType = isset($_GET['type']) ? $_GET['type'] : 'overview';
$dateFrom = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Get report data based on type
$reportData = [];

if ($reportType === 'overview') {
    // Overview Report
    try {
        $stmt = $conn->query("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'agent') as total_agents,
                (SELECT COUNT(*) FROM clients) as total_clients,
                (SELECT COUNT(*) FROM subscriptions) as total_subscriptions,
                (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
                (SELECT SUM(price) FROM subscriptions WHERE status = 'active') as monthly_revenue
        ");
        $reportData['overview'] = $stmt->fetch();
        
        // Recent activity
        $stmt = $conn->query("
            SELECT 'New Client' as activity, full_name as name, created_at as date FROM clients 
            UNION ALL 
            SELECT 'New Subscription' as activity, subscription_name as name, created_at as date FROM subscriptions 
            ORDER BY date DESC LIMIT 10
        ");
        $reportData['activity'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $reportData = [];
    }
}

if ($reportType === 'subscriptions') {
    // Subscriptions Report
    try {
        $stmt = $conn->prepare("
            SELECT 
                s.subscription_id,
                s.subscription_name,
                s.subscription_type,
                s.price,
                s.start_date,
                s.end_date,
                s.status,
                s.auto_renew,
                c.full_name as client_name,
                u.full_name as agent_name
            FROM subscriptions s
            JOIN clients c ON s.client_id = c.client_id
            LEFT JOIN users u ON c.agent_id = u.user_id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $reportData['subscriptions'] = $stmt->fetchAll();
        
        // Summary stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN status = 'active' THEN price ELSE 0 END) as total_revenue
            FROM subscriptions
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $reportData['summary'] = $stmt->fetch();
    } catch (PDOException $e) {
        $reportData = [];
    }
}

if ($reportType === 'agents') {
    // Agents Report
    try {
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.created_at,
                COUNT(c.client_id) as client_count,
                COUNT(s.subscription_id) as subscription_count,
                SUM(s.price) as total_revenue
            FROM users u
            LEFT JOIN clients c ON u.user_id = c.agent_id
            LEFT JOIN subscriptions s ON c.client_id = s.client_id AND s.status = 'active'
            WHERE u.role = 'agent'
            GROUP BY u.user_id
            ORDER BY client_count DESC
        ");
        $stmt->execute();
        $reportData['agents'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $reportData = [];
    }
}

if ($reportType === 'revenue') {
    // Revenue Report
    try {
        $stmt = $conn->prepare("
            SELECT 
                DATE_FORMAT(s.created_at, '%Y-%m') as month,
                COUNT(*) as subscription_count,
                SUM(s.price) as total_revenue,
                AVG(s.price) as avg_price
            FROM subscriptions s
            WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status = 'active'
            GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $reportData['revenue'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $reportData = [];
    }
}

if ($reportType === 'expiring') {
    // Expiring Subscriptions Report
    try {
        $stmt = $conn->query("
            SELECT 
                s.subscription_id,
                s.subscription_name,
                s.end_date,
                s.price,
                c.full_name as client_name,
                u.full_name as agent_name,
                DATEDIFF(s.end_date, CURDATE()) as days_until_expiry
            FROM subscriptions s
            JOIN clients c ON s.client_id = c.client_id
            LEFT JOIN users u ON c.agent_id = u.user_id
            WHERE s.status = 'active' AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY s.end_date ASC
        ");
        $reportData['expiring'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $reportData = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .report-type-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .report-type-btn {
            padding: 10px 15px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 13px;
        }
        
        .report-type-btn:hover {
            border-color: #ff6b5b;
            color: #ff6b5b;
        }
        
        .report-type-btn.active {
            background-color: #ff6b5b;
            color: white;
            border-color: #ff6b5b;
        }
        
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        
        .filter-item label {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
            color: #333;
        }
        
        .filter-item input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .filter-btn {
            padding: 8px 20px;
            background-color: #ff6b5b;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: #ff6b5b;
        }
        
        .report-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .report-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f9f9f9;
            color: #333;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #eee;
        }
        
        .stat-card-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .stat-card-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        table thead {
            background-color: #f5f5f5;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        
        table tbody tr:hover {
            background-color: #fafafa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: 500;
            font-size: 11px;
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
        
        .export-btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 500;
        }
        
        .export-btn:hover {
            background-color: #218838;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .reports-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-type-selector {
                width: 100%;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-item input {
                width: 100%;
            }
            
            .filter-btn {
                width: 100%;
            }
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
                <a href="settings.php" style="<?php echo $current_page === 'settings.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">⚙️ Settings</a>
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Reports</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Report Type Selector -->
                <div class="reports-header">
                    <div class="report-type-selector">
                        <a href="reports.php" class="report-type-btn <?php echo $reportType === 'overview' ? 'active' : ''; ?>">📊 Overview</a>
                        <a href="reports.php?type=subscriptions" class="report-type-btn <?php echo $reportType === 'subscriptions' ? 'active' : ''; ?>">📋 Subscriptions</a>
                        <a href="reports.php?type=agents" class="report-type-btn <?php echo $reportType === 'agents' ? 'active' : ''; ?>">👥 Agents</a>
                        <a href="reports.php?type=revenue" class="report-type-btn <?php echo $reportType === 'revenue' ? 'active' : ''; ?>">💰 Revenue</a>
                        <a href="reports.php?type=expiring" class="report-type-btn <?php echo $reportType === 'expiring' ? 'active' : ''; ?>">⏰ Expiring Soon</a>
                    </div>
                </div>

                <!-- Date Filter -->
                <?php if ($reportType !== 'overview' && $reportType !== 'expiring'): ?>
                <div class="filter-section">
                    <form method="GET" action="">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                        <div class="filter-group">
                            <div class="filter-item">
                                <label>From Date</label>
                                <input type="date" name="from" value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="filter-item">
                                <label>To Date</label>
                                <input type="date" name="to" value="<?php echo $dateTo; ?>">
                            </div>
                            <button type="submit" class="filter-btn">🔍 Filter</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Report Content -->
                <div class="report-container">
                    <!-- Overview Report -->
                    <?php if ($reportType === 'overview'): ?>
                        <div class="report-title">System Overview</div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card-label">Total Agents</div>
                                <div class="stat-card-value"><?php echo $reportData['overview']['total_agents'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Total Clients</div>
                                <div class="stat-card-value"><?php echo $reportData['overview']['total_clients'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Total Subscriptions</div>
                                <div class="stat-card-value"><?php echo $reportData['overview']['total_subscriptions'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Active Subscriptions</div>
                                <div class="stat-card-value"><?php echo $reportData['overview']['active_subscriptions'] ?? 0; ?></div>
                            </div>
                        </div>

                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">Monthly Revenue</h3>
                        <div class="stat-card" style="text-align: left;">
                            <div style="font-size: 32px; font-weight: 700;">
                                <?php echo formatCurrency($reportData['overview']['monthly_revenue'] ?? 0); ?>
                            </div>
                        </div>

                        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">Recent Activity</h3>
                        <?php if (!empty($reportData['activity'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['activity'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['activity']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo formatDate($item['date']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No activity found</div>
                        <?php endif; ?>

                    <!-- Subscriptions Report -->
                    <?php elseif ($reportType === 'subscriptions'): ?>
                        <div class="report-title">Subscriptions Report</div>
                        
                        <?php if (!empty($reportData['summary'])): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card-label">Total Subscriptions</div>
                                <div class="stat-card-value"><?php echo $reportData['summary']['total'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Active</div>
                                <div class="stat-card-value"><?php echo $reportData['summary']['active'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Inactive</div>
                                <div class="stat-card-value"><?php echo $reportData['summary']['inactive'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-label">Total Revenue</div>
                                <div class="stat-card-value" style="font-size: 20px;"><?php echo formatCurrency($reportData['summary']['total_revenue'] ?? 0); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($reportData['subscriptions'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subscription Name</th>
                                        <th>Client</th>
                                        <th>Agent</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Auto Renew</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['subscriptions'] as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['agent_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                        <td><?php echo formatCurrency($sub['price']); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower($sub['status']); ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                                        <td><?php echo $sub['auto_renew'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No subscriptions found for the selected date range</div>
                        <?php endif; ?>

                    <!-- Agents Report -->
                    <?php elseif ($reportType === 'agents'): ?>
                        <div class="report-title">Agents Performance Report</div>
                        
                        <?php if (!empty($reportData['agents'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Agent Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Clients</th>
                                        <th>Subscriptions</th>
                                        <th>Total Revenue</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['agents'] as $agent): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                        <td><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $agent['client_count']; ?></td>
                                        <td><?php echo $agent['subscription_count']; ?></td>
                                        <td><?php echo formatCurrency($agent['total_revenue'] ?? 0); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower($agent['status']); ?>"><?php echo ucfirst($agent['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No agents found</div>
                        <?php endif; ?>

                    <!-- Revenue Report -->
                    <?php elseif ($reportType === 'revenue'): ?>
                        <div class="report-title">Revenue Report</div>
                        
                        <?php if (!empty($reportData['revenue'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Subscriptions</th>
                                        <th>Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['revenue'] as $revenue): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($revenue['month']); ?></td>
                                        <td><?php echo $revenue['subscription_count']; ?></td>
                                        <td><?php echo formatCurrency($revenue['total_revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No revenue data found for the selected date range</div>
                        <?php endif; ?>

                    <!-- Expiring Soon Report -->
                    <?php elseif ($reportType === 'expiring'): ?>
                        <div class="report-title">Subscriptions Expiring Soon (Next 30 Days)</div>
                        
                        <?php if (!empty($reportData['expiring'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subscription Name</th>
                                        <th>Client</th>
                                        <th>Agent</th>
                                        <th>Price</th>
                                        <th>Expiry Date</th>
                                        <th>Days Until Expiry</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['expiring'] as $exp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exp['subscription_name']); ?></td>
                                        <td><?php echo htmlspecialchars($exp['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($exp['agent_name']); ?></td>
                                        <td><?php echo formatCurrency($exp['price']); ?></td>
                                        <td><?php echo formatDate($exp['end_date']); ?></td>
                                        <td>
                                            <?php 
                                            $days = $exp['days_until_expiry'];
                                            if ($days <= 7) {
                                                echo '<span class="badge" style="background-color: #f8d7da; color: #721c24;">' . $days . ' days</span>';
                                            } elseif ($days <= 14) {
                                                echo '<span class="badge" style="background-color: #fff3cd; color: #856404;">' . $days . ' days</span>';
                                            } else {
                                                echo '<span class="badge" style="background-color: #d1ecf1; color: #0c5460;">' . $days . ' days</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">No subscriptions expiring in the next 30 days</div>
                        <?php endif; ?>
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
                } else if (menuId === 'reports-menu') {
                    // Keep Reports menu open on reports page
                    const menu = document.getElementById(menuId);
                    const button = menu.previousElementSibling;
                    menu.classList.add('active');
                    button.classList.add('expanded');
                }
            });
        });

        // Highlight active menu link (including top-level)
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar-nav a').forEach(link => {
                const href = link.getAttribute('href').split('/').pop();
                if (href === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
