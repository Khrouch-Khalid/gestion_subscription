<?php
// auth/login.php
require_once __DIR__ . '/../config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to dashboard
            header("Location: ../index.php");
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Subscription Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
                'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
                sans-serif;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 520px;
        }

        .login-box {
            background: white;
            border-radius: 12px;
            padding: 50px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-header img {
            height: 50px;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .login-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .login-header p {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }

        .login-signup {
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
            color: #666;
        }

        .login-signup a {
            color: #0088cc;
            text-decoration: none;
            font-weight: 500;
        }

        .login-signup a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d0d0d0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #0088cc;
            box-shadow: 0 0 0 3px rgba(0, 136, 204, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #0088cc;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
            padding: 0;
        }

        .password-toggle:hover {
            text-decoration: underline;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0088cc;
        }

        .checkbox-group label {
            margin: 0;
            font-size: 14px;
            color: #1a1a1a;
            font-weight: 400;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: #0a8fa1;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-login:hover {
            background-color: #077a8a;
        }

        .btn-login:active {
            background-color: #066275;
        }

        .footer-links {
            text-align: center;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #e8e8e8;
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }

        .footer-links a {
            color: #0088cc;
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Log In</h1>
            </div>

            <div class="login-signup">
                Need a Subscription account? <a href="#">Create an account</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">Show</button>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me logged in</label>
                </div>
                
                <button type="submit" class="btn-login">Log In</button>
            </form>

            <div class="footer-links">
                ©2026 All Rights Reserved. Subscription Management® is a<br>
                registered trademark. <a href="#">Cookie Preferences</a>, <a href="#">Privacy</a>, and <a href="#">Terms</a>.
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Show';
            }
        }
    </script>
</body>
</html>