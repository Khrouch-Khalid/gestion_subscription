<?php
// agent/delete_client.php
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

// Get subscription count for this client
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE client_id = ?");
$stmt->execute([$client_id]);
$subscription_count = $stmt->fetch()['count'];

$error = '';
$success = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = isset($_POST['confirm']) ? (bool)$_POST['confirm'] : false;
    
    if (!$confirm) {
        $error = 'You must confirm the deletion by checking the checkbox.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Delete all subscriptions for this client
            $stmt = $conn->prepare("DELETE FROM subscriptions WHERE client_id = ?");
            $stmt->execute([$client_id]);
            
            // Delete the client
            $stmt = $conn->prepare("DELETE FROM clients WHERE client_id = ? AND agent_id = ?");
            $stmt->execute([$client_id, $agent_id]);
            
            $conn->commit();
            
            header('Location: manage_clients.php?success=Client deleted successfully');
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error deleting client: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Client - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .warning-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .warning-title {
            font-size: 18px;
            font-weight: 700;
            color: #856404;
            margin-bottom: 10px;
        }
        
        .warning-text {
            color: #856404;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        .client-details {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
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
        }
        
        .subscription-warning {
            background-color: #f8d7da;
            border: 2px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }
        
        .subscription-warning h4 {
            color: #721c24;
            margin: 0 0 10px 0;
        }
        
        .subscription-warning p {
            color: #721c24;
            margin: 0;
        }
        
        .confirmation-checkbox {
            display: flex;
            align-items: flex-start;
            background-color: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .confirmation-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            margin-top: 2px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .checkbox-label {
            flex: 1;
            cursor: pointer;
        }
        
        .checkbox-label strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }
        
        .checkbox-label span {
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .btn-delete:disabled {
            background-color: #ccc;
            color: #999;
            cursor: not-allowed;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            margin: 0;
            font-size: 28px;
        }
        
        .page-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .confirmation-checkbox {
                flex-direction: column;
            }
            
            .confirmation-checkbox input[type="checkbox"] {
                margin-right: 10px;
                margin-bottom: 10px;
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
                <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #667eea; color: white;' : ''; ?>">📈 Reports</a>
                
                <!-- Settings -->
                <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #667eea; color: white;' : ''; ?>">⚙️ Settings</a>
                
                <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Delete Client</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>⚠️ Delete Client</h2>
                    <p>This action cannot be undone. Please review the information carefully before proceeding.</p>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Warning Box -->
                <div class="warning-box">
                    <div class="warning-icon">⚠️</div>
                    <div class="warning-title">This is a permanent action</div>
                    <div class="warning-text">
                        Deleting this client will also permanently delete all associated subscriptions. This action cannot be reversed. 
                        Make sure you have backed up any important information before proceeding.
                    </div>
                </div>

                <!-- Client Details -->
                <div class="client-details">
                    <h3 style="margin-top: 0; color: #333;">Client Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($client['full_name']); ?></span>
                    </div>
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
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="badge" style="background-color: <?php echo $client['status'] === 'active' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $client['status'] === 'active' ? '#155724' : '#721c24'; ?>; padding: 4px 8px; border-radius: 4px;">
                                <?php echo ucfirst($client['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Subscription Warning -->
                <?php if ($subscription_count > 0): ?>
                    <div class="subscription-warning">
                        <h4>⚠️ Associated Subscriptions</h4>
                        <p>
                            This client has <strong><?php echo $subscription_count; ?></strong> subscription(s) that will also be permanently deleted.
                            These subscriptions will be removed from the system and cannot be recovered.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Confirmation Form -->
                <form method="POST" class="confirmation-form">
                    <div class="confirmation-checkbox">
                        <input type="checkbox" id="confirm" name="confirm" value="1" onchange="updateDeleteButton()">
                        <label for="confirm" class="checkbox-label">
                            <strong>I understand this action is permanent</strong>
                            <span>I have read the warning and I want to proceed with deleting this client and all associated data.</span>
                        </label>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-delete" id="deleteBtn" disabled>
                            🗑️ Permanently Delete Client
                        </button>
                        <a href="manage_clients.php" class="btn btn-cancel">Cancel</a>
                    </div>
                </form>
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
        
        // Update delete button state
        function updateDeleteButton() {
            const confirm = document.getElementById('confirm').checked;
            const deleteBtn = document.getElementById('deleteBtn');
            
            if (confirm) {
                deleteBtn.disabled = false;
            } else {
                deleteBtn.disabled = true;
            }
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
