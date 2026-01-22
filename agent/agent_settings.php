<?php
// agent/agent_settings.php
require_once __DIR__ . '/../config/config.php';
requireAgent();

$conn = getDBConnection();
$agent_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$errors = [];
$success = false;
$message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }

    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }

    if (empty($confirm_password)) {
        $errors[] = "Password confirmation is required";
    }

    if (!empty($new_password) && !empty($confirm_password) && $new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // If no validation errors, check current password and update
    if (empty($errors)) {
        try {
            // Get current password hash
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? AND role = 'agent'");
            $stmt->execute([$agent_id]);
            $agent = $stmt->fetch();

            if (!$agent) {
                $errors[] = "Agent not found";
            } elseif (!password_verify($current_password, $agent['password'])) {
                $errors[] = "Current password is incorrect";
            } else {
                // Update password
                $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updateStmt->execute([$hashedPassword, $agent_id]);
                $success = true;
                $message = "Password changed successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get agent info
$stmt = $conn->prepare("SELECT user_id, full_name, email, username, phone, created_at FROM users WHERE user_id = ? AND role = 'agent'");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Agent Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        .settings-container {
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
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
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

        .card-divider {
            border-bottom: 1px solid #ddd;
            padding-bottom: 30px;
            margin-bottom: 30px;
        }

        .card-divider:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/agent/agent_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1>Settings</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../auth/logout.php" class="btn-logout">Logout</a>
                </div>
            </header>

            <div class="content-area">
                <div class="settings-container">
                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="alert alert-success" style="margin-bottom: 20px;">
                            <strong>✅ Success!</strong> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" style="margin-bottom: 20px;">
                            <strong>⚠️ Please fix the following errors:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Information Card -->
                    <div class="card" style="margin-bottom: 30px;">
                        <div class="card-header">
                            <h2 style="margin: 0;">Profile Information</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($agent['full_name']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($agent['username']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($agent['email']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" value="<?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Member Since</label>
                                <input type="text" value="<?php echo formatDate($agent['created_at']); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2 style="margin: 0;">Change Password</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="current_password">Current Password *</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           placeholder="Enter your current password" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password *</label>
                                    <input type="password" id="new_password" name="new_password" 
                                           placeholder="Enter new password (min 6 characters)" required>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm new password" required>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn btn-success">✅ Change Password</button>
                                    <a href="agent_dashboard.php" class="btn btn-secondary">Cancel</a>
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
                }
            });
        });
    </script>
</body>
</html>
