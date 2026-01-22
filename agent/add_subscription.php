<?php
// agent/add_subscription.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$messageType = '';

// Get agent's clients for dropdown
$stmt = $conn->prepare("SELECT client_id, full_name FROM clients WHERE agent_id = ? AND status = 'active' ORDER BY full_name ASC");
$stmt->execute([$agent_id]);
$clients = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $subscriptionName = sanitizeInput($_POST['subscription_name'] ?? '');
    $subscriptionType = sanitizeInput($_POST['subscription_type'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $startDate = sanitizeInput($_POST['start_date'] ?? '');
    $endDate = sanitizeInput($_POST['end_date'] ?? '');
    $autoRenew = isset($_POST['auto_renew']) ? 1 : 0;
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validation
    $errors = [];
    
    if ($clientId <= 0) {
        $errors[] = 'Please select a client';
    } else {
        // Verify client belongs to this agent
        $stmt = $conn->prepare("SELECT client_id FROM clients WHERE client_id = ? AND agent_id = ?");
        $stmt->execute([$clientId, $agent_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'Invalid client selected';
        }
    }
    
    if (empty($subscriptionName)) {
        $errors[] = 'Subscription name is required';
    }
    
    if (empty($subscriptionType)) {
        $errors[] = 'Subscription type is required';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if (empty($startDate)) {
        $errors[] = 'Start date is required';
    } else {
        if (!strtotime($startDate)) {
            $errors[] = 'Invalid start date format';
        }
    }
    
    if (empty($endDate)) {
        $errors[] = 'End date is required';
    } else {
        if (!strtotime($endDate)) {
            $errors[] = 'Invalid end date format';
        } elseif (strtotime($endDate) <= strtotime($startDate)) {
            $errors[] = 'End date must be after start date';
        }
    }
    
    if (!in_array($status, ['active', 'inactive', 'expired'])) {
        $errors[] = 'Invalid status selected';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO subscriptions (client_id, subscription_name, subscription_type, price, start_date, end_date, auto_renew, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $clientId,
                $subscriptionName,
                $subscriptionType,
                $price,
                $startDate,
                $endDate,
                $autoRenew,
                $status
            ]);
            
            $message = 'Subscription added successfully!';
            $messageType = 'success';
            
            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            $message = 'Error adding subscription: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subscription - Subscription Manager</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b5b;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 12px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
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
            background-color: #ff6b5b;
            color: white;
        }
        
        .form-actions button[type="submit"]:hover {
            background-color: #5568d3;
        }
        
        .form-actions a {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions a:hover {
            background-color: #5a6268;
        }
        
        .form-info {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .form-info h5 {
            margin: 0 0 10px 0;
            color: #0c5460;
        }
        
        .form-info p {
            margin: 5px 0;
            color: #0c5460;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .form-row,
            .form-row-three {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
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
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'subscriptions-menu')">
                        📋 Subscriptions
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="subscriptions-menu">
                        <a href="manage_subscriptions.php">Manage Subscriptions</a>
                        <a href="add_subscription.php" class="active" style="background-color: #ff6b5b; color: white;">Add Subscription</a>

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
                <h1>Add New Subscription</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="form-container">
                    <!-- Information Box -->
                    <div class="form-info">
                        <h5>📋 Add New Subscription</h5>
                        <p>Create a new subscription for one of your clients. Fill in all required fields below.</p>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 20px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Subscription Form -->
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="">
                                <!-- Client Selection -->
                                <div class="form-group">
                                    <label for="client_id">Select Client *</label>
                                    <select id="client_id" name="client_id" required>
                                        <option value="">-- Select a client --</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['client_id']; ?>" 
                                                    <?php echo ($_POST['client_id'] ?? '') == $client['client_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($clients)): ?>
                                        <small style="color: #dc3545;">No active clients available. <a href="add_client.php" style="color: #dc3545; text-decoration: underline;">Create one first</a></small>
                                    <?php endif; ?>
                                </div>

                                <!-- Subscription Name -->
                                <div class="form-group">
                                    <label for="subscription_name">Subscription Name *</label>
                                    <input type="text" id="subscription_name" name="subscription_name" 
                                           value="<?php echo htmlspecialchars($_POST['subscription_name'] ?? ''); ?>" 
                                           required placeholder="e.g., Premium Plan">
                                </div>

                                <!-- Subscription Type and Price Row -->
                                <div class="form-row">
                                    <!-- Subscription Type -->
                                    <div class="form-group">
                                        <label for="subscription_type">Subscription Type *</label>
                                        <input type="text" id="subscription_type" name="subscription_type" 
                                               value="<?php echo htmlspecialchars($_POST['subscription_type'] ?? ''); ?>" 
                                               required placeholder="e.g., Monthly, Annual">
                                    </div>

                                    <!-- Price -->
                                    <div class="form-group">
                                        <label for="price">Price *</label>
                                        <input type="number" id="price" name="price" 
                                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                                               required step="0.01" min="0.01" placeholder="0.00">
                                    </div>
                                </div>

                                <!-- Start Date and End Date Row -->
                                <div class="form-row">
                                    <!-- Start Date -->
                                    <div class="form-group">
                                        <label for="start_date">Start Date *</label>
                                        <input type="date" id="start_date" name="start_date" 
                                               value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" 
                                               required>
                                    </div>

                                    <!-- End Date -->
                                    <div class="form-group">
                                        <label for="end_date">End Date *</label>
                                        <input type="date" id="end_date" name="end_date" 
                                               value="<?php echo htmlspecialchars($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 year'))); ?>" 
                                               required>
                                    </div>
                                </div>

                                <!-- Status -->
                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select id="status" name="status" required>
                                        <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="expired" <?php echo ($_POST['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>

                                <!-- Auto Renew Checkbox -->
                                <div class="checkbox-group">
                                    <input type="checkbox" id="auto_renew" name="auto_renew" 
                                           <?php echo isset($_POST['auto_renew']) ? 'checked' : ''; ?>>
                                    <label for="auto_renew">Auto-renew this subscription</label>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="submit">✅ Add Subscription</button>
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

        // Set default end date to 1 year from start date
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            startDate.setFullYear(startDate.getFullYear() + 1);
            const endDateInput = document.getElementById('end_date');
            if (endDateInput.value === '' || new Date(endDateInput.value) <= new Date(this.value)) {
                endDateInput.value = startDate.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
