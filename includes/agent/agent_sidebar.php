<?php
// includes/agent/agent_sidebar.php
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Agent Panel</h2>
        <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Agent'); ?></p>
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
                <a href="manage_clients.php" style="<?php echo $current_page === 'manage_clients.php' ? 'background-color: rgba(255, 107, 91, 0.2); color: #ff6b5b;' : ''; ?>">Manage Clients</a>
                <a href="add_client.php" style="<?php echo $current_page === 'add_client.php' ? 'background-color: rgba(255, 107, 91, 0.2); color: #ff6b5b;' : ''; ?>">Add New Client</a>
            </div>
        </div>
        
        <!-- Subscriptions Menu Group -->
        <div class="sidebar-menu-group">
            <button class="sidebar-menu-toggle" onclick="toggleMenu(event, 'subscriptions-menu')">
                📋 Subscriptions
                <span class="toggle-icon">▼</span>
            </button>
            <div class="sidebar-submenu" id="subscriptions-menu">
                <a href="manage_subscriptions.php" style="<?php echo $current_page === 'manage_subscriptions.php' ? 'background-color: rgba(255, 107, 91, 0.2); color: #ff6b5b;' : ''; ?>">Manage Subscriptions</a>
                <a href="add_subscription.php" style="<?php echo $current_page === 'add_subscription.php' ? 'background-color: rgba(255, 107, 91, 0.2); color: #ff6b5b;' : ''; ?>">Add Subscription</a>
            </div>
        </div>
        
        <!-- Reports -->
        <a href="my_reports.php" style="<?php echo $current_page === 'my_reports.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?>">📈 Reports</a>

        <!-- Settings -->
        <a href="agent_settings.php" style="<?php echo $current_page === 'agent_settings.php' ? 'background-color: #ff6b5b; color: white;' : ''; ?> margin-top: 20px;">⚙️ Settings</a>
        
        <a href="../auth/logout.php" style="margin-top: 20px; border-top: 1px solid #34495e; padding-top: 15px;">🚪 Logout</a>
    </nav>
</aside>
