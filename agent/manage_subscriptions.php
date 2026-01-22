<?php
// agent/manage_subscriptions.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get filter and search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query for subscriptions with client info
$query = "SELECT s.*, c.full_name as client_name FROM subscriptions s 
          JOIN clients c ON s.client_id = c.client_id 
          WHERE c.agent_id = ?";
$params = [$agent_id];

// Add search filters
if (!empty($search)) {
    $query .= " AND (s.subscription_name LIKE ? OR c.full_name LIKE ? OR s.subscription_type LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add status filter
if (!empty($status)) {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
    FROM subscriptions
    WHERE client_id IN (SELECT client_id FROM clients WHERE agent_id = ?)";

$stmt = $conn->prepare($stats_query);
$stmt->execute([$agent_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions - Subscription Manager</title>
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
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 15px;
            align-items: center;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        
        .btn-filter {
            padding: 10px 24px;
            background-color: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            background-color: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-reset {
            padding: 10px 24px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-reset:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
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
        
        .btn-add-subscription {
            padding: 12px 25px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }

        .btn-add-subscription:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
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
            background-color: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-weight: 600;
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            table {
                font-size: 12px;
            }
            
            table th,
            table td {
                padding: 8px;
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
                <a href="agent_dashboard.php" style="<?php echo $current_page === 'agent_dashboard.php' ? 'background-color: #667eea; color: white;' : ''; ?>">📊 Dashboard</a>
                
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
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'subscriptions-menu')">
                        📋 Subscriptions
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="subscriptions-menu">
                        <a href="manage_subscriptions.php">Manage Subscriptions</a>
                        <a href="add_subscription.php">Add Subscription</a>
                    </div>
                </div>
                
                <!-- Reports Menu Group -->
                <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #667eea; color: white;' : ''; ?>">📈 Reports</a>
                
                <!-- Settings -->
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #667eea; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Manage Subscriptions</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">📊 Subscription Overview</div>
                    <div class="overview-subtitle">Your subscription statistics</div>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-card-label">📋 Total Subscriptions</div>
                            <div class="overview-card-value"><?php echo $stats['total'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">✓ Active</div>
                            <div class="overview-card-value"><?php echo $stats['active'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">✗ Inactive</div>
                            <div class="overview-card-value"><?php echo $stats['inactive'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">⏰ Expired</div>
                            <div class="overview-card-value"><?php echo $stats['expired'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Add New Subscription Button -->
                <a href="add_subscription.php" class="btn-add-subscription">➕ Add New Subscription</a>

                <!-- Filter Section -->
                <form method="GET" class="filter-section">
                    <div class="filter-row">
                        <div class="form-group">
                            <label for="search">Search (Name, Client, Type)</label>
                            <input type="text" id="search" name="search" placeholder="Search subscriptions..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter">🔍 Search</button>
                            <a href="manage_subscriptions.php" class="btn-reset">Reset</a>
                        </div>
                    </div>
                </form>

                <!-- Subscriptions Table -->
                <div class="data-section">
                    <div class="section-title">📋 Subscriptions List</div>
                    <?php if (empty($subscriptions)): ?>
                        <div class="no-data">
                            <p>No subscriptions found. <a href="add_subscription.php" style="color: #667eea; font-weight: 600;">Create one now</a></p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Subscription</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Auto-Renew</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sub['client_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sub['subscription_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['subscription_type']); ?></td>
                                    <td><?php echo formatCurrency($sub['price']); ?></td>
                                    <td><?php echo formatDate($sub['start_date']); ?></td>
                                    <td><?php echo formatDate($sub['end_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($sub['status']); ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $sub['auto_renew'] ? '✓ Yes' : '✗ No'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_subscription.php?id=<?php echo $sub['subscription_id']; ?>" class="btn-action btn-edit">Edit</a>
                                            <a href="delete_subscription.php?id=<?php echo $sub['subscription_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
            const menuIds = ['clients-menu', 'subscriptions-menu'];
            menuIds.forEach(menuId => {
                const saved = localStorage.getItem('menu-' + menuId);
                if (saved === 'true') {
                    const menu = document.getElementById(menuId);
                    const button = menu.previousElementSibling;
                    menu.classList.add('active');
                    button.classList.add('expanded');
                } else if (menuId === 'subscriptions-menu') {
                    // Keep Subscriptions menu open on this page
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
