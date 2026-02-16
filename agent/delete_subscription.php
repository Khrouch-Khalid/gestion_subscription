<?php
// agent/delete_subscription.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$subscription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';
$subscription = null;

// Get subscription details and verify it belongs to agent's client
$stmt = $conn->prepare("
    SELECT s.*, c.full_name as client_name
    FROM subscriptions s
    JOIN clients c ON s.client_id = c.client_id
    WHERE s.subscription_id = ? AND c.agent_id = ?
");
$stmt->execute([$subscription_id, $agent_id]);
$subscription = $stmt->fetch();

if (!$subscription) {
    header('Location: manage_subscriptions.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = isset($_POST['confirm_delete']) ? 1 : 0;
    
    if (!$confirm) {
        $message = 'Please confirm deletion by checking the box';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM subscriptions WHERE subscription_id = ?");
            $stmt->execute([$subscription_id]);
            
            header('Location: manage_subscriptions.php?message=deleted');
            exit;
        } catch (PDOException $e) {
            $message = 'Error deleting subscription: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Subscription - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        
        .warning-box h3 {
            margin: 0 0 10px 0;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-box p {
            margin: 5px 0;
            color: #856404;
        }
        
        .subscription-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
            min-width: 150px;
        }
        
        .detail-value {
            color: #666;
        }
        
        .confirmation-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
        }
        
        .form-actions button,
        .form-actions a {
            flex: 1;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .form-actions button[type="submit"] {
            background-color: #dc3545;
            color: white;
        }
        
        .form-actions button[type="submit"]:hover {
            background-color: #c82333;
        }
        
        .form-actions button[type="submit"]:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .form-actions a {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions a:hover {
            background-color: #5a6268;
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
                <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>
                
                <!-- Settings -->
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Delete Subscription</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="delete-container">
                    <!-- Warning Box -->
                    <div class="warning-box">
                        <h3>⚠️ Delete Subscription</h3>
                        <p>You are about to permanently delete a subscription. This action cannot be undone.</p>
                    </div>

                    <!-- Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 20px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Subscription Details -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">Subscription Details</h2>
                        </div>
                        <div class="card-body">
                            <div class="subscription-details">
                                <div class="detail-row">
                                    <span class="detail-label">Subscription Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($subscription['subscription_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Client:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($subscription['client_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Type:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($subscription['subscription_type']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Price:</span>
                                    <span class="detail-value"><?php echo formatCurrency($subscription['price']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Start Date:</span>
                                    <span class="detail-value"><?php echo formatDate($subscription['start_date']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">End Date:</span>
                                    <span class="detail-value"><?php echo formatDate($subscription['end_date']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="badge badge-<?php echo strtolower($subscription['status']); ?>">
                                        <?php echo ucfirst($subscription['status']); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Auto-Renew:</span>
                                    <span class="detail-value"><?php echo $subscription['auto_renew'] ? 'Yes' : 'No'; ?></span>
                                </div>
                            </div>

                            <!-- Confirmation Section -->
                            <form method="POST" action="">
                                <div class="confirmation-section">
                                    <h4 style="margin-top: 0;">Confirm Deletion</h4>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="confirm_delete" name="confirm_delete" 
                                               onchange="document.querySelector('button[type=submit]').disabled = !this.checked">
                                        <label for="confirm_delete">
                                            I understand this subscription will be permanently deleted and cannot be recovered
                                        </label>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="submit" disabled>🗑️ Delete Subscription</button>
                                    <a href="manage_subscriptions.php">❌ Cancel</a>
                                </div>
                            </form>
                        </div>
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
