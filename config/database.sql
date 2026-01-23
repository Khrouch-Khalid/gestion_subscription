-- ========================================
-- SUBSCRIPTION MANAGEMENT SYSTEM DATABASE
-- 3-Tier: Admin -> Agent -> Clients
-- ========================================

-- ========================================
-- 1. USERS TABLE (Admin & Agents ONLY)
-- ========================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'agent') NOT NULL DEFAULT 'agent',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 2. CLIENTS TABLE (Managed by Agents)
-- ========================================
CREATE TABLE clients (
    client_id INT PRIMARY KEY AUTO_INCREMENT,
    agent_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (agent_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_agent (agent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- 3. SUBSCRIPTIONS TABLE
-- ========================================
CREATE TABLE subscriptions (
    subscription_id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    subscription_name VARCHAR(100) NOT NULL,
    subscription_type ENUM('Monthly', 'Quarterly', 'Yearly') NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    auto_renew BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- USEFUL VIEWS
-- ========================================


-- ========================================
-- USEFUL QUERIES
-- ========================================

-- Get agent's clients
-- SELECT * FROM clients WHERE agent_id = ?;

-- Get agent's total revenue
-- SELECT SUM(s.price) as total_revenue
-- FROM clients c
-- JOIN subscriptions s ON c.client_id = s.client_id
-- WHERE c.agent_id = ? AND s.status = 'active';

-- Get expiring subscriptions for an agent
-- SELECT c.full_name, s.subscription_name, s.end_date
-- FROM clients c
-- JOIN subscriptions s ON c.client_id = s.client_id
-- WHERE c.agent_id = ?
-- AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
-- AND s.status = 'active';

-- Get all clients (admin view)
-- SELECT c.*, u.full_name as agent_name
-- FROM clients c
-- JOIN users u ON c.agent_id = u.user_id;
