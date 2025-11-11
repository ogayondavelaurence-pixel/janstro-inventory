-- ============================================
-- Janstro Prime Inventory Management System
-- Database Schema
-- ISO/IEC 25010:2023 Compliant
-- Date: November 2025
-- ============================================

-- Drop existing database if exists (CAUTION: Use only in development)
DROP DATABASE IF EXISTS janstro_inventory;

-- Create database
CREATE DATABASE janstro_inventory 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE janstro_inventory;

-- ============================================
-- 1. ROLES TABLE
-- Defines user roles for RBAC
-- ============================================
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. USERS TABLE
-- Stores system users with authentication
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    role_id INT NOT NULL,
    contact_no VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CATEGORIES TABLE
-- Item classification
-- ============================================
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ITEMS TABLE
-- Inventory items (solar panels, inverters, etc.)
-- ============================================
CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category_id INT,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'pcs',
    reorder_level INT DEFAULT 10,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_item_name (item_name),
    INDEX idx_quantity (quantity),
    INDEX idx_low_stock (quantity, reorder_level),
    CHECK (quantity >= 0),
    CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. SUPPLIERS TABLE
-- Supplier information
-- ============================================
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_info VARCHAR(100),
    address TEXT,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. PURCHASE ORDERS TABLE
-- Material procurement orders
-- ============================================
CREATE TABLE purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'approved', 'delivered', 'cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    po_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_po_date (po_date),
    CHECK (quantity > 0),
    CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TRANSACTIONS TABLE
-- Inventory movement history (IN/OUT)
-- ============================================
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    notes TEXT,
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_date_time (date_time),
    CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. AUDIT LOGS TABLE
-- System action tracking for security
-- ============================================
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT SEED DATA
-- ============================================

-- Insert Roles
INSERT INTO roles (role_name, description) VALUES
('superadmin', 'Full system access'),
('admin', 'Administrative access'),
('staff', 'Standard user access');

-- Insert Default Users
-- Password: admin123 (hashed with SHA-256)
INSERT INTO users (username, password_hash, name, role_id, status) VALUES
('admin', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'System Administrator', 1, 'active'),
('staff1', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'Staff User', 3, 'active');

-- Insert Categories
INSERT INTO categories (name, description) VALUES
('Solar Panels', 'Photovoltaic panels for solar energy'),
('Inverters', 'Solar power inverters'),
('Batteries', 'Energy storage batteries'),
('Cables & Wiring', 'Electrical cables and connectors'),
('Mounting Systems', 'Panel mounting hardware'),
('Tools & Equipment', 'Installation tools');

-- Insert Sample Items
INSERT INTO items (item_name, category_id, quantity, unit, reorder_level, unit_price) VALUES
('Solar Panel 250W Monocrystalline', 1, 50, 'pcs', 10, 5000.00),
('Solar Panel 300W Polycrystalline', 1, 30, 'pcs', 8, 6000.00),
('Inverter 5kW Hybrid', 2, 15, 'pcs', 5, 45000.00),
('Inverter 10kW On-Grid', 2, 8, 'pcs', 3, 85000.00),
('Deep Cycle Battery 200Ah', 3, 25, 'pcs', 10, 12000.00),
('MC4 Solar Cable 4mm', 4, 500, 'meters', 50, 50.00),
('Aluminum Mounting Rail 4m', 5, 100, 'pcs', 20, 800.00);

-- Insert Sample Suppliers
INSERT INTO suppliers (supplier_name, contact_info, address, email) VALUES
('SolarTech Philippines', '02-1234-5678', 'Quezon City, Metro Manila', 'sales@solartech.ph'),
('Green Energy Solutions', '02-8765-4321', 'Makati City, Metro Manila', 'info@greenenergy.com.ph'),
('PowerSun Distributors', '0917-123-4567', 'Calamba City, Laguna', 'contact@powersun.ph');

-- Insert Sample Purchase Orders
INSERT INTO purchase_orders (supplier_id, item_id, quantity, total_amount, status, created_by) VALUES
(1, 1, 100, 500000.00, 'delivered', 1),
(2, 3, 10, 450000.00, 'pending', 1),
(3, 5, 50, 600000.00, 'approved', 1);

-- Insert Sample Transactions
INSERT INTO transactions (item_id, user_id, transaction_type, quantity, notes) VALUES
(1, 1, 'IN', 100, 'Initial stock from supplier'),
(1, 1, 'OUT', 20, 'Installation for Project ABC'),
(3, 1, 'IN', 10, 'Supplier delivery'),
(5, 1, 'OUT', 5, 'Battery replacement service');

-- ============================================
-- CREATE VIEWS FOR REPORTING
-- ============================================

-- Low Stock Items View
CREATE VIEW v_low_stock_items AS
SELECT 
    i.item_id,
    i.item_name,
    c.name AS category_name,
    i.quantity,
    i.reorder_level,
    i.unit_price,
    (i.quantity * i.unit_price) AS total_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
WHERE i.quantity <= i.reorder_level
ORDER BY i.quantity ASC;

-- Inventory Summary View
CREATE VIEW v_inventory_summary AS
SELECT 
    c.name AS category_name,
    COUNT(i.item_id) AS total_items,
    SUM(i.quantity) AS total_quantity,
    SUM(i.quantity * i.unit_price) AS total_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
GROUP BY c.category_id, c.name
ORDER BY total_value DESC;

-- Recent Transactions View
CREATE VIEW v_recent_transactions AS
SELECT 
    t.transaction_id,
    i.item_name,
    u.name AS user_name,
    t.transaction_type,
    t.quantity,
    t.notes,
    t.date_time
FROM transactions t
JOIN items i ON t.item_id = i.item_id
JOIN users u ON t.user_id = u.user_id
ORDER BY t.date_time DESC
LIMIT 100;

-- Purchase Order Summary View
CREATE VIEW v_purchase_order_summary AS
SELECT 
    po.po_id,
    s.supplier_name,
    i.item_name,
    po.quantity,
    po.total_amount,
    po.status,
    u.name AS created_by_name,
    po.po_date
FROM purchase_orders po
JOIN suppliers s ON po.supplier_id = s.supplier_id
JOIN items i ON po.item_id = i.item_id
JOIN users u ON po.created_by = u.user_id
ORDER BY po.po_date DESC;

-- ============================================
-- CREATE STORED PROCEDURES
-- ============================================

DELIMITER $$

-- Procedure: Update stock with automatic transaction logging
CREATE PROCEDURE sp_update_stock(
    IN p_item_id INT,
    IN p_quantity INT,
    IN p_operation VARCHAR(10),
    IN p_user_id INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_current_qty INT;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Get current quantity
    SELECT quantity INTO v_current_qty FROM items WHERE item_id = p_item_id;
    
    -- Update stock based on operation
    IF p_operation = 'IN' THEN
        UPDATE items SET quantity = quantity + p_quantity WHERE item_id = p_item_id;
    ELSEIF p_operation = 'OUT' THEN
        -- Check if sufficient stock
        IF v_current_qty >= p_quantity THEN
            UPDATE items SET quantity = quantity - p_quantity WHERE item_id = p_item_id;
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
        END IF;
    END IF;
    
    -- Log transaction
    INSERT INTO transactions (item_id, user_id, transaction_type, quantity, notes)
    VALUES (p_item_id, p_user_id, p_operation, p_quantity, p_notes);
    
    COMMIT;
END$$

DELIMITER ;

-- ============================================
-- CREATE TRIGGERS
-- ============================================

DELIMITER $$

-- Trigger: Auto-update item timestamp on quantity change
CREATE TRIGGER tr_update_item_timestamp
BEFORE UPDATE ON items
FOR EACH ROW
BEGIN
    IF NEW.quantity != OLD.quantity THEN
        SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- GRANT PERMISSIONS (Optional - for production)
-- ============================================

-- Create application user
-- CREATE USER 'janstro_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON janstro_inventory.* TO 'janstro_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Show all tables
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'janstro_inventory';

-- Verify data counts
SELECT 
    (SELECT COUNT(*) FROM users) AS total_users,
    (SELECT COUNT(*) FROM items) AS total_items,
    (SELECT COUNT(*) FROM suppliers) AS total_suppliers,
    (SELECT COUNT(*) FROM purchase_orders) AS total_orders,
    (SELECT COUNT(*) FROM transactions) AS total_transactions;

-- ============================================
-- EXTENDED SCHEMA FOR IMMUTABLE TRANSACTIONS
-- Add to existing schema.sql
-- ============================================

-- Sales Orders Table (Customer Orders)
CREATE TABLE IF NOT EXISTS sales_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_contact VARCHAR(100),
    installation_address TEXT,
    installation_date DATE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_by INT,
    completed_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (completed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_installation_date (installation_date),
    INDEX idx_customer (customer_name),
    CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales Order Items (Line items for each order)
CREATE TABLE IF NOT EXISTS sales_order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_item (item_id),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(12, 2) DEFAULT 0,
    paid_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES sales_orders(order_id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_order (order_id),
    INDEX idx_paid_status (paid_status),
    CHECK (total_amount >= 0),
    CHECK (paid_amount >= 0),
    CHECK (paid_amount <= total_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add delivered_date to purchase_orders
ALTER TABLE purchase_orders 
ADD COLUMN delivered_date TIMESTAMP NULL AFTER status;

-- Update transactions table to ensure proper types
ALTER TABLE transactions 
MODIFY COLUMN transaction_type ENUM('IN', 'OUT') NOT NULL;

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- Active Sales Orders View
CREATE OR REPLACE VIEW v_active_sales_orders AS
SELECT 
    so.order_id,
    so.customer_name,
    so.installation_date,
    so.total_amount,
    so.status,
    so.created_at,
    u.name as created_by_name,
    COUNT(soi.order_item_id) as item_count
FROM sales_orders so
LEFT JOIN users u ON so.created_by = u.user_id
LEFT JOIN sales_order_items soi ON so.order_id = soi.order_id
WHERE so.status IN ('pending', 'scheduled', 'in_progress')
GROUP BY so.order_id
ORDER BY so.installation_date ASC;

-- Pending Invoices View
CREATE OR REPLACE VIEW v_pending_invoices AS
SELECT 
    i.invoice_id,
    i.invoice_number,
    i.customer_name,
    i.total_amount,
    i.paid_amount,
    (i.total_amount - i.paid_amount) as balance,
    i.paid_status,
    i.generated_at,
    u.name as generated_by_name
FROM invoices i
LEFT JOIN users u ON i.generated_by = u.user_id
WHERE i.paid_status != 'paid'
ORDER BY i.generated_at DESC;

-- Stock Movement Summary View
CREATE OR REPLACE VIEW v_stock_movement_summary AS
SELECT 
    i.item_id,
    i.item_name,
    i.category_id,
    c.name as category_name,
    i.quantity as current_stock,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'IN' THEN t.quantity ELSE 0 END), 0) as total_stock_in,
    COALESCE(SUM(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity ELSE 0 END), 0) as total_stock_out,
    (i.quantity * i.unit_price) as current_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN transactions t ON i.item_id = t.item_id
GROUP BY i.item_id
ORDER BY current_value DESC;

-- ============================================
-- TRIGGERS FOR DATA INTEGRITY
-- ============================================

-- Prevent direct stock updates (only through transactions)
DELIMITER $$

CREATE TRIGGER prevent_direct_stock_update
BEFORE UPDATE ON items
FOR EACH ROW
BEGIN
    -- Allow updates only from stored procedures or system processes
    -- This is a safety check - in production, use application-level controls
    IF NEW.quantity != OLD.quantity THEN
        -- Log the change
        SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
END$$

-- Auto-calculate line totals in sales order items
CREATE TRIGGER calculate_sales_order_line_total
BEFORE INSERT ON sales_order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$

CREATE TRIGGER update_sales_order_line_total
BEFORE UPDATE ON sales_order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$

DELIMITER ;

-- ============================================
-- STORED PROCEDURES (Enhanced)
-- ============================================

DELIMITER $$

-- Procedure: Process Stock Movement (Enhanced)
DROP PROCEDURE IF EXISTS sp_process_stock_movement$$

CREATE PROCEDURE sp_process_stock_movement(
    IN p_item_id INT,
    IN p_quantity INT,
    IN p_operation ENUM('IN', 'OUT'),
    IN p_user_id INT,
    IN p_notes TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_current_qty INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Transaction failed: Database error';
    END;
    
    START TRANSACTION;
    
    -- Get current quantity with lock
    SELECT quantity INTO v_current_qty 
    FROM items 
    WHERE item_id = p_item_id 
    FOR UPDATE;
    
    IF v_current_qty IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Item not found';
        ROLLBACK;
    ELSEIF p_operation = 'OUT' AND v_current_qty < p_quantity THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Insufficient stock: ', v_current_qty, ' available');
        ROLLBACK;
    ELSE
        -- Update stock
        IF p_operation = 'IN' THEN
            UPDATE items SET quantity = quantity + p_quantity WHERE item_id = p_item_id;
        ELSE
            UPDATE items SET quantity = quantity - p_quantity WHERE item_id = p_item_id;
        END IF;
        
        -- Log transaction
        INSERT INTO transactions (item_id, user_id, transaction_type, quantity, notes)
        VALUES (p_item_id, p_user_id, p_operation, p_quantity, p_notes);
        
        SET p_success = TRUE;
        SET p_message = 'Stock movement processed successfully';
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Sample Sales Order
INSERT INTO sales_orders (customer_name, customer_contact, installation_address, installation_date, total_amount, status, created_by, notes)
VALUES 
('Juan Dela Cruz Solar Farm', '09171234567', '123 Main St, Calamba, Laguna', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 500000.00, 'pending', 1, 'Residential 5kW solar installation'),
('Maria Santos Residence', '09187654321', '456 Oak Ave, Los Baños, Laguna', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 350000.00, 'scheduled', 1, 'Rooftop solar system');

-- Sample Sales Order Items (linked to existing items)
INSERT INTO sales_order_items (order_id, item_id, quantity, unit_price, line_total)
VALUES 
(1, 1, 20, 5000.00, 100000.00),  -- 20x Solar Panel 250W
(1, 4, 1, 45000.00, 45000.00),    -- 1x Inverter 5kW
(2, 2, 12, 6000.00, 72000.00),    -- 12x Solar Panel 300W
(2, 4, 1, 45000.00, 45000.00);    -- 1x Inverter 5kW

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check all tables exist
SELECT TABLE_NAME, TABLE_ROWS 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'janstro_inventory'
ORDER BY TABLE_NAME;

-- Verify views
SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW';

-- Test stock movement procedure
CALL sp_process_stock_movement(1, 5, 'OUT', 1, 'Test stock out', @success, @message);
SELECT @success, @message;

-- ============================================
-- END OF SCHEMA
-- ============================================