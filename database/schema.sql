-- ============================================
-- Janstro Prime Inventory Management System
-- COMPLETE FIXED DATABASE SCHEMA v2.5
-- Date: 2025-11-18
-- ============================================

DROP DATABASE IF EXISTS janstro_inventory;
CREATE DATABASE janstro_inventory 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE janstro_inventory;

-- ============================================
-- 1. ROLES TABLE
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
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    email VARCHAR(100),
    role_id INT NOT NULL,
    contact_no VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CATEGORIES TABLE
-- ============================================
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ITEMS TABLE (Master Data)
-- ============================================
CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE,
    category_id INT,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'pcs',
    reorder_level INT DEFAULT 10,
    unit_price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    is_bom_item BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_item_name (item_name),
    INDEX idx_sku (sku),
    INDEX idx_quantity (quantity),
    INDEX idx_low_stock (quantity, reorder_level),
    CHECK (quantity >= 0),
    CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. BILL OF MATERIALS (BOM) TABLE - NEW
-- ============================================
CREATE TABLE bill_of_materials (
    bom_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_item_id INT NOT NULL,
    component_item_id INT NOT NULL,
    quantity_required DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'pcs',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (component_item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    INDEX idx_parent (parent_item_id),
    INDEX idx_component (component_item_id),
    CHECK (quantity_required > 0),
    UNIQUE KEY unique_bom (parent_item_id, component_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. SUPPLIERS TABLE
-- ============================================
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    payment_terms VARCHAR(50) DEFAULT 'Cash on Delivery',
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. CUSTOMERS TABLE - NEW
-- ============================================
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    customer_type ENUM('individual', 'company') DEFAULT 'individual',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_name (customer_name),
    INDEX idx_contact (contact_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. PURCHASE ORDERS TABLE
-- ============================================
CREATE TABLE purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'approved', 'delivered', 'cancelled') DEFAULT 'pending',
    expected_delivery_date DATE,
    delivered_date TIMESTAMP NULL,
    created_by INT NOT NULL,
    po_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_supplier (supplier_id),
    INDEX idx_po_date (po_date),
    CHECK (quantity > 0),
    CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. SALES ORDERS TABLE - FIXED
-- ============================================
CREATE TABLE sales_orders (
    sales_order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    customer_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    delivery_address TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    installation_date DATE,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    completed_by INT,
    completed_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (completed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_customer (customer_id),
    INDEX idx_order_date (order_date),
    CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. SALES ORDER ITEMS TABLE - FIXED
-- ============================================
CREATE TABLE sales_order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(sales_order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_order (sales_order_id),
    INDEX idx_item (item_id),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. INVOICES TABLE
-- ============================================
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(12, 2) DEFAULT 0,
    paid_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(sales_order_id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_order (sales_order_id),
    INDEX idx_paid_status (paid_status),
    CHECK (total_amount >= 0),
    CHECK (paid_amount >= 0),
    CHECK (paid_amount <= total_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. TRANSACTIONS TABLE (Material Documents)
-- ============================================
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2),
    total_amount DECIMAL(12, 2),
    reference_type VARCHAR(50),
    reference_number VARCHAR(50),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_date (transaction_date),
    INDEX idx_item (item_id),
    CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. AUDIT LOGS TABLE
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

-- Roles
INSERT INTO roles (role_name, description) VALUES
('superadmin', 'Full system access with all privileges'),
('admin', 'Administrative access to manage operations'),
('staff', 'Standard user access for daily operations');

-- Users (Password: admin123 hashed with bcrypt)
INSERT INTO users (username, password_hash, name, email, role_id, status) VALUES
('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5yvT7m.H6rXwu', 'System Administrator', 'admin@janstro.com', 1, 'active'),
('staff1', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5yvT7m.H6rXwu', 'Staff User', 'staff@janstro.com', 3, 'active');

-- Categories
INSERT INTO categories (name, description) VALUES
('Solar Panels', 'Photovoltaic panels for solar energy generation'),
('Inverters', 'Solar power inverters and converters'),
('Batteries', 'Energy storage batteries and systems'),
('Cables & Wiring', 'Electrical cables, connectors, and wiring'),
('Mounting Systems', 'Panel mounting hardware and structures'),
('Components', 'Small components and accessories');

-- Items (Master Data)
INSERT INTO items (item_name, sku, category_id, quantity, unit, reorder_level, unit_price, description, is_bom_item) VALUES
-- Parent Items (Complete Systems)
('5kW Solar System', 'SYS-5KW-001', 1, 10, 'set', 3, 150000.00, 'Complete 5kW solar installation system', TRUE),
('10kW Solar System', 'SYS-10KW-001', 1, 5, 'set', 2, 280000.00, 'Complete 10kW solar installation system', TRUE),

-- Component Items
('Solar Panel 250W Mono', 'SP-250W-MONO', 1, 50, 'pcs', 10, 5000.00, '250W Monocrystalline solar panel', FALSE),
('Solar Panel 300W Poly', 'SP-300W-POLY', 1, 30, 'pcs', 8, 6000.00, '300W Polycrystalline solar panel', FALSE),
('Inverter 5kW Hybrid', 'INV-5KW-HYB', 2, 15, 'pcs', 5, 45000.00, '5kW Hybrid inverter with battery support', FALSE),
('Inverter 10kW Grid', 'INV-10KW-GRD', 2, 8, 'pcs', 3, 85000.00, '10kW Grid-tied inverter', FALSE),
('Battery 200Ah 12V', 'BAT-200AH-12V', 3, 25, 'pcs', 10, 12000.00, 'Deep cycle 200Ah battery', FALSE),
('MC4 Cable 4mm', 'CBL-MC4-4MM', 4, 500, 'meters', 50, 50.00, 'MC4 solar cable 4mm', FALSE),
('Mounting Rail 4m', 'MNT-RAIL-4M', 5, 100, 'pcs', 20, 800.00, 'Aluminum mounting rail 4 meters', FALSE),
('Junction Box', 'JBX-STD-001', 6, 80, 'pcs', 15, 350.00, 'Weatherproof junction box', FALSE);

-- Bill of Materials for 5kW System
INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required, unit) VALUES
(1, 3, 20, 'pcs'),  -- 20x 250W panels
(1, 5, 1, 'pcs'),   -- 1x 5kW inverter
(1, 7, 4, 'pcs'),   -- 4x batteries
(1, 8, 100, 'meters'), -- 100m cable
(1, 9, 10, 'pcs'),  -- 10x mounting rails
(1, 10, 2, 'pcs');  -- 2x junction boxes

-- Bill of Materials for 10kW System
INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required, unit) VALUES
(2, 4, 34, 'pcs'),  -- 34x 300W panels
(2, 6, 1, 'pcs'),   -- 1x 10kW inverter
(2, 7, 8, 'pcs'),   -- 8x batteries
(2, 8, 200, 'meters'), -- 200m cable
(2, 9, 20, 'pcs'),  -- 20x mounting rails
(2, 10, 4, 'pcs');  -- 4x junction boxes

-- Suppliers
INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, payment_terms, status) VALUES
('SolarTech Philippines', 'John Santos', '02-1234-5678', 'sales@solartech.ph', 'Quezon City, Metro Manila', 'Net 30', 'active'),
('Green Energy Solutions', 'Maria Garcia', '02-8765-4321', 'info@greenenergy.ph', 'Makati City, Metro Manila', 'Net 15', 'active'),
('PowerSun Distributors', 'Pedro Reyes', '0917-123-4567', 'contact@powersun.ph', 'Calamba City, Laguna', 'COD', 'active');

-- Customers
INSERT INTO customers (customer_name, contact_number, email, address, customer_type) VALUES
('Juan Dela Cruz', '09171234567', 'juan.delacruz@email.com', '123 Main St, Calamba, Laguna', 'individual'),
('ABC Corporation', '02-9876-5432', 'admin@abccorp.com', '456 Business Ave, Makati City', 'company');

-- Sample Purchase Orders
INSERT INTO purchase_orders (supplier_id, item_id, quantity, unit_price, total_amount, status, created_by, expected_delivery_date) VALUES
(1, 3, 100, 5000.00, 500000.00, 'delivered', 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 5, 10, 45000.00, 450000.00, 'pending', 1, DATE_ADD(CURDATE(), INTERVAL 14 DAY));

-- Sample Transactions
INSERT INTO transactions (item_id, user_id, transaction_type, quantity, unit_price, total_amount, reference_type, reference_number, notes) VALUES
(3, 1, 'IN', 100, 5000.00, 500000.00, 'Purchase Order', 'PO-001', 'Initial stock from supplier'),
(3, 1, 'OUT', 20, 5000.00, 100000.00, 'Sales Order', 'SO-001', 'Installation for Customer ABC');

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- Low Stock Items View
CREATE OR REPLACE VIEW v_low_stock_items AS
SELECT 
    i.item_id,
    i.item_name,
    i.sku,
    c.name AS category_name,
    i.quantity,
    i.reorder_level,
    i.unit,
    i.unit_price,
    (i.quantity * i.unit_price) AS total_value,
    CASE 
        WHEN i.quantity = 0 THEN 'Out of Stock'
        WHEN i.quantity <= i.reorder_level THEN 'Low Stock'
        ELSE 'In Stock'
    END AS stock_status
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
WHERE i.quantity <= i.reorder_level
ORDER BY i.quantity ASC;

-- Stock Movement Report View
CREATE OR REPLACE VIEW v_stock_movements AS
SELECT 
    t.transaction_id,
    t.transaction_date,
    t.transaction_type,
    i.item_name,
    i.sku,
    i.unit,
    t.quantity,
    t.unit_price,
    t.total_amount,
    t.reference_type,
    t.reference_number,
    u.name AS created_by_name,
    t.notes
FROM transactions t
JOIN items i ON t.item_id = i.item_id
JOIN users u ON t.user_id = u.user_id
ORDER BY t.transaction_date DESC;

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER $$

-- Process Stock Movement (Immutable Transaction)
DROP PROCEDURE IF EXISTS sp_process_stock_movement$$
CREATE PROCEDURE sp_process_stock_movement(
    IN p_item_id INT,
    IN p_quantity INT,
    IN p_operation ENUM('IN', 'OUT'),
    IN p_user_id INT,
    IN p_reference_type VARCHAR(50),
    IN p_reference_number VARCHAR(50),
    IN p_notes TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255),
    OUT p_new_stock INT
)
BEGIN
    DECLARE v_current_qty INT;
    DECLARE v_unit_price DECIMAL(10,2);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Transaction failed: Database error';
    END;
    
    START TRANSACTION;
    
    -- Get current stock with lock
    SELECT quantity, unit_price INTO v_current_qty, v_unit_price
    FROM items 
    WHERE item_id = p_item_id 
    FOR UPDATE;
    
    IF v_current_qty IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Item not found';
        ROLLBACK;
    ELSEIF p_operation = 'OUT' AND v_current_qty < p_quantity THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('Insufficient stock. Available: ', v_current_qty);
        ROLLBACK;
    ELSE
        -- Update stock
        IF p_operation = 'IN' THEN
            UPDATE items SET quantity = quantity + p_quantity WHERE item_id = p_item_id;
            SET p_new_stock = v_current_qty + p_quantity;
        ELSE
            UPDATE items SET quantity = quantity - p_quantity WHERE item_id = p_item_id;
            SET p_new_stock = v_current_qty - p_quantity;
        END IF;
        
        -- Log transaction (Material Document)
        INSERT INTO transactions (
            item_id, user_id, transaction_type, quantity, 
            unit_price, total_amount, reference_type, reference_number, notes
        ) VALUES (
            p_item_id, p_user_id, p_operation, p_quantity,
            v_unit_price, v_unit_price * p_quantity, 
            p_reference_type, p_reference_number, p_notes
        );
        
        SET p_success = TRUE;
        SET p_message = 'Stock movement processed successfully';
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check all tables
SELECT TABLE_NAME, TABLE_ROWS 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'janstro_inventory'
ORDER BY TABLE_NAME;

-- Verify BOM structure
SELECT 
    p.item_name AS parent_item,
    c.item_name AS component,
    b.quantity_required,
    b.unit
FROM bill_of_materials b
JOIN items p ON b.parent_item_id = p.item_id
JOIN items c ON b.component_item_id = c.item_id
ORDER BY p.item_name, c.item_name;

-- ============================================
-- END OF SCHEMA
-- ============================================