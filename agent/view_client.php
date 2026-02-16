<?php
// agent/view_client.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get client details and verify it belongs to agent
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ? AND agent_id = ?");
$stmt->execute([$client_id, $agent_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: manage_clients.php');
    exit;
}

// Get client's subscriptions
$stmt = $conn->prepare("
    SELECT * FROM subscriptions 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client_id]);
$subscriptions = $stmt->fetchAll();

// Get subscription statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status = 'active' THEN price ELSE 0 END) as total_revenue
    FROM subscriptions
    WHERE client_id = ?
");
$stmt->execute([$client_id]);
$subStats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Client - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .client-info {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
        }
        
        .detail-value {
            color: #666;
            word-break: break-word;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ff6b5b;
            text-align: center;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
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
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #0056b3;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .btn-add-subscription {
            background-color: #28a745;
            color: white;
        }
        
        .btn-add-subscription:hover {
            background-color: #218838;
        }
        
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #5a6268;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }
        
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .subscription-actions {
            display: flex;
            gap: 8px;
        }
        
        .sub-action-btn {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
        
        .btn-edit-sub {
            background-color: #007bff;
            color: white;
        }
        
        .btn-edit-sub:hover {
            background-color: #0056b3;
        }
        
        .btn-delete-sub {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete-sub:hover {
            background-color: #c82333;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        
        .client-header {
            background: linear-gradient(135deg, #ff6b5b 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .client-header h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .client-status {
            display: inline-block;
            padding: 6px 12px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 20px;
        }
        
        @media (max-width: 768px) {
            .client-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .subscription-actions {
                flex-direction: column;
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
                <a href="agent_dashboard.php" style="<?php echo $current_page === 'agent_dashboard.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📊 Dashboard</a>
                
                <!-- Clients Menu Group -->
                <div class="sidebar-menu-group">
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'clients-menu')">
                        👥 Clients
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="clients-menu">
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
                <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>
                
                <!-- Settings -->
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>View Client</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Client Header -->
                <div class="client-header">
                    <h2><?php echo htmlspecialchars($client['full_name']); ?></h2>
                    <span class="client-status">
                        <span class="badge badge-<?php echo strtolower($client['status']); ?>" 
                              style="background-color: rgba(255,255,255,0.3); color: white;">
                            <?php echo ucfirst($client['status']); ?>
                        </span>
                    </span>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="edit_client.php?id=<?php echo $client['client_id']; ?>" class="action-btn btn-edit">✏️ Edit Client</a>
                    <a href="delete_client.php?id=<?php echo $client['client_id']; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure?')">🗑️ Delete Client</a>
                    <a href="add_subscription.php" class="action-btn btn-add-subscription">➕ Add Subscription</a>
                    <a href="manage_clients.php" class="action-btn btn-back">← Back to Clients</a>
                </div>

                <!-- Client Information -->
                <div class="client-info">
                    <!-- Details -->
                    <div class="card">
                        <div class="card-header">
                            <h3 style="margin: 0;">Client Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($client['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($client['phone']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">City:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($client['city'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($client['address'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Member Since:</span>
                                <span class="detail-value"><?php echo formatDate($client['created_at']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Updated:</span>
                                <span class="detail-value"><?php echo formatDate($client['updated_at']); ?></span>
                            </div>
                            <?php if (!empty($client['notes'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Notes:</span>
                            </div>
                            <div style="padding: 12px 0; background-color: #f8f9fa; padding: 12px; border-radius: 4px;">
                                <p style="margin: 0; color: #666;"><?php echo htmlspecialchars($client['notes']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-label">Total Subscriptions</div>
                                <div class="stat-value"><?php echo $subStats['total'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <div class="stat-label">Active</div>
                                <div class="stat-value"><?php echo $subStats['active'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <div class="stat-label">Inactive</div>
                                <div class="stat-value"><?php echo $subStats['inactive'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <div class="stat-label">Expired</div>
                                <div class="stat-value"><?php echo $subStats['expired'] ?? 0; ?></div>
                            </div>
                            <div class="stat-card" style="border-left-color: #17a2b8; grid-column: span 2;">
                                <div class="stat-label">Total Revenue</div>
                                <div class="stat-value" style="font-size: 18px;"><?php echo formatCurrency($subStats['total_revenue'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client's Subscriptions -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0;">Subscriptions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subscriptions)): ?>
                            <div class="no-data">
                                <p>No subscriptions found. <a href="add_subscription.php" style="color: #ff6b5b;">Add one now</a></p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
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
                                        <td><?php echo $sub['auto_renew'] ? 'Yes' : 'No'; ?></td>
                                        <td>
                                            <div class="subscription-actions">
                                                <a href="edit_subscription.php?id=<?php echo $sub['subscription_id']; ?>" class="sub-action-btn btn-edit-sub">Edit</a>
                                                <a href="delete_subscription.php?id=<?php echo $sub['subscription_id']; ?>" class="sub-action-btn btn-delete-sub" onclick="return confirm('Are you sure?')">Delete</a>
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
                } else if (menuId === 'clients-menu') {
                    // Keep Clients menu open on this page
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
