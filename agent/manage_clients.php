<?php
// agent/manage_clients.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$query = "SELECT * FROM clients WHERE agent_id = ?";
$params = [$agent_id];

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY created_at DESC";

// Get clients
$stmt = $conn->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM clients WHERE agent_id = ?
");
$stmt->execute([$agent_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - Subscription Manager</title>
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
        
        .filter-item input,
        .filter-item select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            min-width: 150px;
            transition: border-color 0.3s ease;
        }

        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            padding: 10px 20px;
            background-color: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: #5568d3;
            transform: translateY(-2px);
        }
        
        .reset-btn {
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .reset-btn:hover {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-button {
            display: inline-block;
            padding: 12px 25px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .add-button:hover {
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
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
            transform: translateY(-1px);
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
            .filter-group {
                flex-direction: column;
            }
            
            .filter-item input,
            .filter-item select {
                min-width: 100%;
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
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'clients-menu')">
                        👥 Clients
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="clients-menu">
                        <a href="manage_clients.php" class="active">Manage Clients</a>
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
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #667eea; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Manage Clients</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Overview Section -->
                <div class="overview-section">
                    <div class="overview-title">📊 Client Overview</div>
                    <div class="overview-subtitle">Your client statistics</div>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <div class="overview-card-label">👥 Total Clients</div>
                            <div class="overview-card-value"><?php echo $stats['total'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">✓ Active Clients</div>
                            <div class="overview-card-value"><?php echo $stats['active'] ?? 0; ?></div>
                        </div>
                        <div class="overview-card">
                            <div class="overview-card-label">✗ Inactive Clients</div>
                            <div class="overview-card-value"><?php echo $stats['inactive'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Add New Client Button -->
                <a href="add_client.php" class="add-button">➕ Add New Client</a>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="">
                        <div class="filter-group">
                            <div class="filter-item">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Name, Email, or Phone">
                            </div>
                            <div class="filter-item">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">-- All Status --</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <button type="submit" class="filter-btn">🔍 Filter</button>
                            <a href="manage_clients.php" class="reset-btn">↻ Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Clients Table -->
                <div class="data-section">
                    <div class="section-title">👥 Clients List</div>
                    <?php if (empty($clients)): ?>
                        <div class="no-data">
                            <p>No clients found. <a href="add_client.php" style="color: #667eea; font-weight: 600;">Create one now</a></p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
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
                                    <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($client['city'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($client['status']); ?>">
                                            <?php echo ucfirst($client['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($client['created_at']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_client.php?id=<?php echo $client['client_id']; ?>" class="action-btn btn-view">View</a>
                                            <a href="edit_client.php?id=<?php echo $client['client_id']; ?>" class="action-btn btn-edit">Edit</a>
                                            <a href="delete_client.php?id=<?php echo $client['client_id']; ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
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
    </script>
</body>
</html>
