<?php
// admin/create_agent.php
require_once __DIR__ . '/../config/config.php';
requireAdmin();

$conn = getDBConnection();
$current_page = basename($_SERVER['PHP_SELF']);
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    } else {
        // Check if username exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Username already exists";
        }
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = "Email already exists";
        }
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    } elseif ($password !== $password_confirm) {
        $errors[] = "Passwords do not match";
    }

    // If no errors, create the agent
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, phone, role, status)
                VALUES (?, ?, ?, ?, ?, 'agent', 'active')
            ");
            
            $stmt->execute([$username, $email, $hashedPassword, $full_name, $phone]);
            $success = true;
            
            // Store values for display before clearing
            $success_username = $username;
            $success_password = $password;
            
            // Clear form
            $full_name = '';
            $username = '';
            $email = '';
            $phone = '';
            $password = '';
            $password_confirm = '';
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Agent - Subscription Manager</title>
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
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        

        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .form-actions button,
        .form-actions a {
            flex: 1;
            padding: 12px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .form-actions button[type="submit"] {
            background-color: #28a745;
            color: white;
        }
        
        .form-actions button[type="submit"]:hover {
            background-color: #218838;
        }
        
        .form-actions a {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions a:hover {
            background-color: #5a6268;
        }
        
        .success-message {
            background-color: #e8f5e9;
            border-left: 4px solid #27ae60;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message h4 {
            margin: 0 0 10px 0;
            color: #27ae60;
        }
        
        .success-message p {
            margin: 5px 0;
            color: #27ae60;
            font-size: 14px;
        }
        
        .success-message code {
            background-color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
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
                    <button class="sidebar-menu-toggle active" onclick="toggleMenu(event, 'agents-menu')">
                        👥 Agents
                        <span class="toggle-icon">▼</span>
                    </button>
                    <div class="sidebar-submenu active" id="agents-menu">
                        <a href="manage_agents.php">Manage Agents</a>
                        <a href="create_agent.php" class="active">Create New Agent</a>
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
                <h1>Create New Agent</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="form-container">
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="success-message">
                            <h4>✅ Agent Created Successfully!</h4>
                            <p><strong>Username:</strong> <code><?php echo htmlspecialchars($success_username); ?></code></p>
                            <p><strong>Initial Password:</strong> <code><?php echo htmlspecialchars($success_password); ?></code></p>
                            <p style="color: #f39c12; margin-top: 15px;"><strong>⚠️ Important:</strong> Make sure to save this password. The agent can change it after logging in.</p>
                            <div style="margin-top: 20px;">
                                <a href="manage_agents.php" class="btn btn-primary">View Agents</a>
                                <a href="create_agent.php" class="btn btn-success">Create Another Agent</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">Agent Information</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <!-- Full Name -->
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($full_name ?? ''); ?>" 
                                           required placeholder="Enter full name">
                                </div>

                                <!-- Username -->
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                           required placeholder="Enter unique username (min 3 characters)">
                                </div>

                                <!-- Email -->
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                           required placeholder="Enter email address">
                                </div>

                                <!-- Phone -->
                                <div class="form-group">
                                    <label for="phone">Phone Number (Optional)</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                                           placeholder="Enter phone number">
                                </div>

                                <!-- Password Section -->
                                    <div class="form-group">
                                        <label for="password">Password *</label>
                                        <input type="password" id="password" name="password" 
                                               value="<?php echo htmlspecialchars($password ?? ''); ?>" 
                                               placeholder="Enter password (min 6 characters)">
                                    </div>

                                    <div class="form-group">
                                        <label for="password_confirm">Confirm Password *</label>
                                        <input type="password" id="password_confirm" name="password_confirm" 
                                               value="<?php echo htmlspecialchars($password_confirm ?? ''); ?>" 
                                               placeholder="Confirm password">
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success">✅ Create Agent</button>
                                    <a href="manage_agents.php" class="btn btn-secondary">Cancel</a>
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
            const menuIds = ['agents-menu', 'reports-menu', 'settings-menu'];
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
