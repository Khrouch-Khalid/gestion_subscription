<?php
// index.php
require_once __DIR__ . '/config/config.php';

// If not logged in, redirect to login
if (!isLoggedIn()) {
    header("Location: ./auth/login.php");
    exit();
}

// Redirect based on role
if (isAdmin()) {
    header("Location: ./admin/admin_dashboard.php");
} elseif (isAgent()) {
    header("Location: ./agent/agent_dashboard.php");
} else {
    // Invalid role, logout
    header("Location: ./auth/logout.php");
}
exit();
?>