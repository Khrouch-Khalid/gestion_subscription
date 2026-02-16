<?php
// agent/agent_dashboard.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Build date filter conditions
$dateFilter = '';
$dateParams = [];
if (isset($_GET['download_pdf']) || isset($_GET['download_csv'])) {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    if ($start_date && $end_date) {
        // Validate dates
        if (strtotime($start_date) && strtotime($end_date)) {
            $dateFilter = " AND DATE(created_at) BETWEEN ? AND ?";
            $dateParams = [$start_date, $end_date];
        }
    }
}

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    // Verify database connection
    if (!$conn) {
        die('Database connection error. Please try again.');
    }
    
    // Fetch all data BEFORE output buffering
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    $dateFilter = '';
    $dateParams = [];
    if ($start_date && $end_date) {
        if (strtotime($start_date) && strtotime($end_date)) {
            $dateFilter = " AND DATE(created_at) BETWEEN ? AND ?";
            $dateParams = [$start_date, $end_date];
        }
    }
    
    // Get total clients with date filter
    $sql = "SELECT COUNT(*) as total FROM clients WHERE agent_id = ?" . $dateFilter;
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $total_clients = $stmt->fetch()['total'];
    
    // Get total subscriptions and revenue with date filter
    $sql = "
        SELECT COUNT(*) as total, SUM(s.price) as total_revenue
        FROM subscriptions s
        JOIN clients c ON s.client_id = c.client_id
        WHERE c.agent_id = ?" . str_replace('created_at', 's.created_at', $dateFilter);
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sub_data = $stmt->fetch();
    $total_subscriptions = $sub_data['total'];
    $total_revenue = $sub_data['total_revenue'] ?? 0;
    
    // Get clients
    $sql = "SELECT client_id, full_name, email, phone, status, created_at FROM clients WHERE agent_id = ?" . $dateFilter . " ORDER BY created_at DESC";
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pdf_clients = $stmt->fetchAll();
    
    // Get subscriptions
    $sql = "
        SELECT s.subscription_id, c.full_name, s.subscription_type, s.price, s.status, s.created_at, s.end_date
        FROM subscriptions s
        JOIN clients c ON s.client_id = c.client_id
        WHERE c.agent_id = ?" . str_replace('created_at', 's.created_at', $dateFilter) . "
        ORDER BY s.created_at DESC
    ";
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pdf_subscriptions = $stmt->fetchAll();
    
    // Now start output buffering
    ob_start();
    ?>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
            h2 { color: #667eea; margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background-color: #667eea; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .no-data { text-align: center; color: #999; padding: 20px; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 12px; }
        </style>
    </head>
    <body>
        <h1>Agent Data Report</h1>
        <p><strong>Agent:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <?php if (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
            <p><strong>Period:</strong> <?php echo date('d/m/Y', strtotime($_GET['start_date'])); ?> to <?php echo date('d/m/Y', strtotime($_GET['end_date'])); ?></p>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div style="background-color: #f0f4ff; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <p style="margin: 0; color: #999; font-size: 12px;">Total Clients</p>
                <h3 style="margin: 5px 0 0 0; color: #667eea; font-size: 28px;"><?php echo $total_clients; ?></h3>
            </div>
            <div style="background-color: #f0f4ff; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <p style="margin: 0; color: #999; font-size: 12px;">Total Subscriptions</p>
                <h3 style="margin: 5px 0 0 0; color: #667eea; font-size: 28px;"><?php echo $total_subscriptions; ?></h3>
            </div>
            <div style="background-color: #f0f4ff; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <p style="margin: 0; color: #999; font-size: 12px;">Total Revenue</p>
                <h3 style="margin: 5px 0 0 0; color: #667eea; font-size: 28px;"><?php echo formatMoney($total_revenue); ?></h3>
            </div>
        </div>
        
        <h2>Clients</h2>
        <?php if (!empty($pdf_clients)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdf_clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($client['status'])); ?></td>
                            <td><?php echo formatDate($client['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No clients found</p>
        <?php endif; ?>
        
        <h2>Subscriptions</h2>
        <?php if (!empty($pdf_subscriptions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdf_subscriptions as $sub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                            <td><?php echo formatMoney($sub['price']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($sub['status'])); ?></td>
                            <td><?php echo formatDate($sub['created_at']); ?></td>
                            <td><?php echo formatDate($sub['end_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No subscriptions found</p>
        <?php endif; ?>
        
        <div class="footer">
            <p>This document was generated automatically by Subscription Manager</p>
            <p><em>To save as PDF: Press Ctrl+P (or Cmd+P on Mac) and select "Save as PDF"</em></p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Output as HTML that can be printed to PDF by browser
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="agent_report_' . date('Y-m-d_H-i-s') . '.html"');
    echo $html;
    exit();
}

// Handle CSV download
if (isset($_GET['download_csv'])) {
    // Create CSV file
    $filename = 'agent_data_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // === CLIENTS SECTION ===
    fputcsv($output, ['CLIENTS DATA']);
    fputcsv($output, ['Client ID', 'Full Name', 'Email', 'Phone', 'Status', 'Created At']);
    
    $sql = "SELECT client_id, full_name, email, phone, status, created_at FROM clients WHERE agent_id = ?" . $dateFilter . " ORDER BY created_at DESC";
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fputcsv($output, []); // Empty line
    
    // === SUBSCRIPTIONS SECTION ===
    fputcsv($output, ['SUBSCRIPTIONS DATA']);
    fputcsv($output, ['Subscription ID', 'Client Name', 'Subscription Type', 'Price', 'Status', 'Start Date', 'End Date']);
    
    $sql = "
        SELECT s.subscription_id, c.full_name, s.subscription_type, s.price, s.status, s.created_at, s.end_date
        FROM subscriptions s
        JOIN clients c ON s.client_id = c.client_id
        WHERE c.agent_id = ?" . str_replace('created_at', 's.created_at', $dateFilter) . "
        ORDER BY s.created_at DESC
    ";
    $params = [$agent_id];
    $params = array_merge($params, $dateParams);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fputcsv($output, []); // Empty line
    
    fclose($output);
    exit();
}

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

// Get recent subscriptions (pull only subscription status and alias it to avoid any client status mix‑up)
$stmt = $conn->prepare("SELECT s.subscription_id, s.subscription_name, s.subscription_type, 
                              s.price, s.status AS subscription_status, s.created_at, c.full_name, c.client_id
                       FROM subscriptions s
                       JOIN clients c ON s.client_id = c.client_id
                       WHERE c.agent_id = ?
                       ORDER BY s.created_at DESC
                       LIMIT 5");
$stmt->execute([$agent_id]);
$recentSubscriptions = $stmt->fetchAll();

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
            display: inline-block;
        }

        .badge-inactive {
            background-color: #f8d7da;
            color: #721c24;
            display: inline-block;
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
                <!-- Download Button -->
                <div style="margin-bottom: 30px;">
                    <button onclick="openDownloadModal()" style="display: inline-block; padding: 12px 25px; background-color: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; transition: all 0.3s ease;">
                        📥 Download Data
                    </button>
                </div>

                <!-- Download Modal -->
                <div id="downloadModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div style="background-color: #fff; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h2 style="margin-top: 0; color: #333;">Download Data Report</h2>
                        <p style="color: #666;">Select the date range for your data export:</p>
                        
                        <form id="downloadForm">
                            <div style="margin-bottom: 20px;">
                                <label for="startDate" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Start Date:</label>
                                <input type="date" id="startDate" name="startDate" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label for="endDate" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">End Date:</label>
                                <input type="date" id="endDate" name="endDate" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" style="flex: 1; padding: 12px; background-color: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                                    Download PDF
                                </button>
                                <button type="button" onclick="closeDownloadModal()" style="flex: 1; padding: 12px; background-color: #999; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

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

                        <!-- Recent Subscriptions Section -->
                        <div class="data-section">
                            <div class="section-title">
                                📋 Recent Subscriptions
                                <a href="manage_subscriptions.php">View All</a>
                            </div>
                            <?php if (empty($recentSubscriptions)): ?>
                                <p style="color: #999; text-align: center; padding: 40px 0;">No subscriptions yet.</p>
                            <?php else: ?>
                                <table class="table-simple">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentSubscriptions as $sub): ?>
                                        <?php
                                            // normalize label: active stays active, everything else becomes "Inactive"
                                            $status = $sub['subscription_status'] ?? $sub['status'];
                                            $label = $status === 'active' ? 'Active' : 'Inactive';
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="view_client.php?id=<?php echo $sub['client_id']; ?>" style="color: #ff6b5b; text-decoration: none;">
                                                    <?php echo htmlspecialchars($sub['full_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                            <td><?php echo formatCurrency($sub['price']); ?></td>
                                            <td>
                                                <span class="badge-sm <?php echo $status === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                    <?php echo $label; ?>
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
        
        // Download Modal Functions
        function openDownloadModal() {
            document.getElementById('downloadModal').style.display = 'block';
        }
        
        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('downloadModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Handle form submission
        document.getElementById('downloadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                // Validate that end date is not before start date
                if (new Date(endDate) < new Date(startDate)) {
                    alert('End date must be after or equal to start date');
                    return;
                }
                window.location.href = 'agent_dashboard.php?download_pdf=1&start_date=' + startDate + '&end_date=' + endDate;
                closeDownloadModal();
            }
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
