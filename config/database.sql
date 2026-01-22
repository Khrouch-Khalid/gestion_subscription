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
-- INSERT DEFAULT ADMIN
-- ========================================
INSERT INTO users (username, email, password, full_name, role, status) 
VALUES (
    'admin', 
    'admin@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'System Administrator', 
    'admin', 
    'active'
);

-- ========================================
-- INSERT DEMO AGENT
-- ========================================
INSERT INTO users (username, email, password, full_name, phone, role, status) 
VALUES (
    'agent1', 
    'agent1@example.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'khalid Agent', 
    '+212 6 12 34 56 78',
    'agent', 
    'active'
);

-- ========================================
-- INSERT DEMO CLIENTS (for agent1)
-- ========================================
INSERT INTO clients (agent_id, full_name, email, phone, city, status) VALUES
(2, 'Ahmed Mansouri', 'ahmed@example.com', '+212 6 11 22 33 44', 'Casablanca', 'active'),
(2, 'Fatima Zahra', 'fatima@example.com', '+212 6 22 33 44 55', 'Rabat', 'active'),
(2, 'Youssef Benali', 'youssef@example.com', '+212 6 33 44 55 66', 'Marrakech', 'active');

-- ========================================
-- INSERT DEMO SUBSCRIPTIONS
-- ========================================
INSERT INTO subscriptions (client_id, subscription_name, subscription_type, price, start_date, end_date, status) VALUES
(1, 'Netflix Premium', 'Monthly', 99.00, '2026-01-01', '2026-02-01', 'active'),
(1, 'Internet Fiber', 'Monthly', 299.00, '2026-01-01', '2026-02-01', 'active'),
(2, 'Spotify Family', 'Yearly', 990.00, '2026-01-01', '2027-01-01', 'active'),
(3, 'Office 365', 'Monthly', 149.00, '2025-12-01', '2026-01-01', 'active');

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

-- ========================================
-- DEMO LOGIN CREDENTIALS
-- ========================================
-- Admin:
--   Username: admin
--   Password: password
--
-- Agent:
--   Username: agent1
--   Password: password
-- ========================================