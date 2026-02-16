<?php
// agent/edit_client.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';
$client = null;

// Get client details and verify it belongs to agent
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ? AND agent_id = ?");
$stmt->execute([$client_id, $agent_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: manage_clients.php');
    exit;
}

// Get client statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscriptions WHERE client_id = ?");
$stmt->execute([$client_id]);
$subscriptionCount = $stmt->fetch()['count'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validation
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email already exists for other clients of this agent
        $stmt = $conn->prepare("SELECT client_id FROM clients WHERE email = ? AND agent_id = ? AND client_id != ?");
        $stmt->execute([$email, $agent_id, $client_id]);
        if ($stmt->fetch()) {
            $errors[] = 'A client with this email already exists in your account';
        }
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Invalid status selected';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE clients 
                SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, notes = ?, status = ?, updated_at = NOW()
                WHERE client_id = ? AND agent_id = ?
            ");
            
            $stmt->execute([
                $fullName,
                $email,
                $phone,
                $address,
                $city,
                $notes,
                $status,
                $client_id,
                $agent_id
            ]);
            
            // Redirect to dashboard after successful update
            header('Location: agent_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error updating client: ' . $e->getMessage();
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
    <title>Edit Client - Subscription Manager</title>
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
        .form-group input[type="tel"],
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
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
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
            .form-row {
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
                <h1>Edit Client</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="form-container">
                    <!-- Information Box -->
                    <div class="form-info">
                        <h5>📝 Edit Client Information</h5>
                        <p>Update the client details below. All fields marked with * are required.</p>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 20px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Client Statistics -->
                    <div class="stats-section">
                        <div class="stat-card">
                            <div class="stat-label">Active Subscriptions</div>
                            <div class="stat-value"><?php echo $subscriptionCount; ?></div>
                        </div>
                        <div class="stat-card" style="border-left-color: #28a745;">
                            <div class="stat-label">Member Since</div>
                            <div class="stat-value" style="font-size: 14px;"><?php echo formatDate($client['created_at']); ?></div>
                        </div>
                        <div class="stat-card" style="border-left-color: #17a2b8;">
                            <div class="stat-label">Status</div>
                            <div class="stat-value" style="font-size: 14px;"><?php echo ucfirst($client['status']); ?></div>
                        </div>
                    </div>

                    <!-- Edit Client Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">Client Details</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <!-- Full Name -->
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($client['full_name']); ?>" 
                                           required placeholder="Enter client full name">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($client['email']); ?>" 
                                           required placeholder="Enter email address">
                                </div>

                                <!-- Phone and City Row -->
                                <div class="form-row">
                                    <!-- Phone -->
                                    <div class="form-group">
                                        <label for="phone">Phone Number *</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($client['phone']); ?>" 
                                               required placeholder="Enter phone number">
                                    </div>

                                    <!-- City -->
                                    <div class="form-group">
                                        <label for="city">City (Optional)</label>
                                        <input type="text" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>" 
                                               placeholder="Enter city">
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="form-group">
                                    <label for="address">Address (Optional)</label>
                                    <input type="text" id="address" name="address" 
                                           value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>" 
                                           placeholder="Enter street address">
                                </div>

                                <!-- Notes -->
                                <div class="form-group">
                                    <label for="notes">Notes (Optional)</label>
                                    <textarea id="notes" name="notes" placeholder="Add any additional notes about the client"><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                                </div>

                                <!-- Status -->
                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select id="status" name="status" required>
                                        <option value="active" <?php echo $client['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $client['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="submit">💾 Save Changes</button>
                                    <a href="manage_clients.php">❌ Cancel</a>
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
