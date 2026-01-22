<?php
// auth/logout.php
require_once __DIR__ . '/../config/config.php';

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit();
?>