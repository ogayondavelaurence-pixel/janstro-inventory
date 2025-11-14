-- ============================================
-- Janstro Prime Inventory Management System
-- Complete Production-Ready SQL + Automation
-- Date: November 2025
-- ============================================

DROP DATABASE IF EXISTS janstro_inventory;
CREATE DATABASE janstro_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE janstro_inventory;

-- ============================================
-- 1. ROLES
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_name(role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. USERS
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    role_id INT NOT NULL,
    contact_no VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
    INDEX idx_username(username),
    INDEX idx_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CUSTOMERS
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    contact_no VARCHAR(50),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_name(customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. CATEGORIES
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_name(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ITEMS
CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category_id INT,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'pcs',
    reorder_level DECIMAL(12,2) DEFAULT 10,
    unit_price DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    INDEX idx_item_name(item_name),
    INDEX idx_quantity(quantity),
    INDEX idx_low_stock(quantity, reorder_level),
    CHECK (quantity >= 0),
    CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. SUPPLIERS
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(255) NOT NULL,
    contact_no VARCHAR(50),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_name(supplier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. PURCHASE ORDERS
CREATE TABLE purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    status ENUM('pending','approved','delivered','cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    po_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status(status),
    INDEX idx_po_date(po_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. PURCHASE ORDER ITEMS
CREATE TABLE purchase_order_items (
    po_item_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_po(po_id),
    INDEX idx_item(item_id),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. SALES ORDERS
CREATE TABLE sales_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    installation_address TEXT,
    installation_date DATE,
    total_amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','scheduled','in_progress','completed','cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_by INT,
    completed_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (completed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status(status),
    INDEX idx_installation_date(installation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. SALES ORDER ITEMS
CREATE TABLE sales_order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES sales_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_order(order_id),
    INDEX idx_item(item_id),
    CHECK (quantity > 0),
    CHECK (unit_price >= 0),
    CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. INVOICES
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    paid_amount DECIMAL(12,2) DEFAULT 0,
    paid_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (order_id) REFERENCES sales_orders(order_id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_invoice_number(invoice_number),
    INDEX idx_order(order_id),
    INDEX idx_paid_status(paid_status),
    CHECK (total_amount >= 0),
    CHECK (paid_amount >= 0),
    CHECK (paid_amount <= total_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. BILL OF MATERIALS
CREATE TABLE bill_of_materials (
    bom_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    component_item_id INT NOT NULL,
    quantity_required DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (component_item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_item(item_id),
    INDEX idx_component(component_item_id),
    CHECK (quantity_required > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. TRANSACTIONS
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_type ENUM('IN','OUT') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    notes TEXT,
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_transaction_type(transaction_type),
    INDEX idx_date_time(date_time),
    CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. AUDIT LOGS
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id(user_id),
    INDEX idx_created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- VIEWS
-- ============================================
CREATE VIEW v_inventory_summary AS
SELECT c.name AS category_name, COUNT(i.item_id) AS total_items, SUM(i.quantity) AS total_quantity,
       SUM(i.quantity * i.unit_price) AS total_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
GROUP BY c.category_id, c.name
ORDER BY total_value DESC;

CREATE VIEW v_low_stock_items AS
SELECT i.item_id, i.item_name, c.name AS category_name, i.quantity, i.reorder_level, i.unit_price,
       (i.quantity*i.unit_price) AS total_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
WHERE i.quantity <= i.reorder_level
ORDER BY i.quantity ASC;

CREATE VIEW v_active_sales_orders AS
SELECT so.order_id, cu.customer_name, so.installation_date, so.total_amount, so.status,
       so.created_at, u.name AS created_by_name, COUNT(soi.order_item_id) AS item_count
FROM sales_orders so
LEFT JOIN users u ON so.created_by = u.user_id
LEFT JOIN customers cu ON so.customer_id = cu.customer_id
LEFT JOIN sales_order_items soi ON so.order_id = soi.order_id
WHERE so.status IN ('pending','scheduled','in_progress')
GROUP BY so.order_id
ORDER BY so.installation_date ASC;

CREATE VIEW v_stock_movement_summary AS
SELECT i.item_id, i.item_name, i.category_id, c.name AS category_name,
       i.quantity AS current_stock,
       COALESCE(SUM(CASE WHEN t.transaction_type='IN' THEN t.quantity ELSE 0 END),0) AS total_stock_in,
       COALESCE(SUM(CASE WHEN t.transaction_type='OUT' THEN t.quantity ELSE 0 END),0) AS total_stock_out,
       (i.quantity*i.unit_price) AS current_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN transactions t ON i.item_id = t.item_id
GROUP BY i.item_id
ORDER BY current_value DESC;

-- ============================================
-- TRIGGERS
-- ============================================
DELIMITER $$

CREATE TRIGGER tr_update_item_timestamp
BEFORE UPDATE ON items
FOR EACH ROW
BEGIN
    IF NEW.quantity != OLD.quantity THEN
        SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
END$$

CREATE TRIGGER tr_calc_sales_order_line_total
BEFORE INSERT ON sales_order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$

CREATE TRIGGER tr_update_sales_order_line_total
BEFORE UPDATE ON sales_order_items
FOR EACH ROW
BEGIN
    SET NEW.line_total = NEW.quantity * NEW.unit_price;
END$$

-- Auto-decrement BOM + auto-create PO
CREATE TRIGGER tr_decrement_bom_components
AFTER INSERT ON sales_order_items
FOR EACH ROW
BEGIN
    DECLARE v_component_id INT;
    DECLARE v_required_qty DECIMAL(12,2);
    DECLARE bom_cursor CURSOR FOR
        SELECT component_item_id, quantity_required FROM bill_of_materials WHERE item_id = NEW.item_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_component_id = NULL;

    OPEN bom_cursor;
    read_loop: LOOP
        FETCH bom_cursor INTO v_component_id, v_required_qty;
        IF v_component_id IS NULL THEN
            LEAVE read_loop;
        END IF;

        UPDATE items SET quantity = quantity - (v_required_qty * NEW.quantity), updated_at = CURRENT_TIMESTAMP
        WHERE item_id = v_component_id;

        INSERT INTO transactions(item_id, user_id, transaction_type, quantity, notes)
        VALUES (v_component_id, NEW.order_id, 'OUT', v_required_qty * NEW.quantity,
                CONCAT('Auto-decrement for sold finished item ', NEW.item_id));

        IF (SELECT quantity FROM items WHERE item_id = v_component_id) <= 
           (SELECT reorder_level FROM items WHERE item_id = v_component_id) THEN
            INSERT INTO purchase_orders(supplier_id, status, created_by, po_date, notes)
            VALUES ((SELECT supplier_id FROM suppliers LIMIT 1), 'pending', NEW.order_id, CURRENT_TIMESTAMP,
                    CONCAT('Auto PO for low stock item ', v_component_id));
        END IF;
    END LOOP;
    CLOSE bom_cursor;
END$$

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Create Sales Order (JSON batch)
CREATE PROCEDURE sp_create_sales_order(
    IN p_customer_id INT,
    IN p_address TEXT,
    IN p_installation_date DATE,
    IN p_created_by INT,
    IN p_items JSON
)
BEGIN
    DECLARE v_order_id INT;
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    DECLARE v_item JSON;
    DECLARE v_quantity DECIMAL(12,2);
    DECLARE v_price DECIMAL(12,2);

    INSERT INTO sales_orders(customer_id, installation_address, installation_date, status, created_by, total_amount)
    VALUES(p_customer_id, p_address, p_installation_date, 'pending', p_created_by, 0);

    SET v_order_id = LAST_INSERT_ID();

    SET @i = 0;
    WHILE @i < JSON_LENGTH(p_items) DO
        SET v_item = JSON_EXTRACT(p_items, CONCAT('$[', @i, ']'));
        SET v_quantity = JSON_UNQUOTE(JSON_EXTRACT(v_item, '$.quantity'));
        SET v_price = JSON_UNQUOTE(JSON_EXTRACT(v_item, '$.unit_price'));
        INSERT INTO sales_order_items(order_id, item_id, quantity, unit_price, line_total)
        VALUES(v_order_id, JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.item_id')), v_quantity, v_price, v_quantity*v_price);
        SET v_total = v_total + (v_quantity*v_price);
        SET @i = @i + 1;
    END WHILE;

    UPDATE sales_orders SET total_amount = v_total WHERE order_id = v_order_id;
END$$

-- Apply Partial Payment
CREATE PROCEDURE sp_apply_payment(
    IN p_invoice_id INT,
    IN p_amount DECIMAL(12,2)
)
BEGIN
    UPDATE invoices
    SET paid_amount = paid_amount + p_amount,
        paid_status = CASE
                        WHEN paid_amount + p_amount >= total_amount THEN 'paid'
                        WHEN paid_amount + p_amount < total_amount THEN 'partial'
                      END,
        paid_date = IF(paid_amount + p_amount >= total_amount, CURRENT_TIMESTAMP, NULL)
    WHERE invoice_id = p_invoice_id;
END$$

DELIMITER $$

-- ============================================
-- 1. Receive Purchase Order (auto-update stock + transactions)
CREATE PROCEDURE sp_receive_purchase_order(IN p_po_id INT, IN p_received_by INT)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_qty DECIMAL(12,2);
    DECLARE po_cursor CURSOR FOR
        SELECT item_id, quantity FROM purchase_order_items WHERE po_id = p_po_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_item_id = NULL;

    OPEN po_cursor;
    read_loop: LOOP
        FETCH po_cursor INTO v_item_id, v_qty;
        IF v_item_id IS NULL THEN
            LEAVE read_loop;
        END IF;

        -- Update stock
        UPDATE items SET quantity = quantity + v_qty, updated_at = CURRENT_TIMESTAMP
        WHERE item_id = v_item_id;

        -- Log transaction
        INSERT INTO transactions(item_id, user_id, transaction_type, quantity, notes)
        VALUES(v_item_id, p_received_by, 'IN', v_qty, CONCAT('Received from PO #', p_po_id));
    END LOOP;

    CLOSE po_cursor;

    -- Update PO status
    UPDATE purchase_orders SET status='delivered', delivered_date=CURRENT_TIMESTAMP
    WHERE po_id = p_po_id;
END$$

-- ============================================
-- 2. Generate Invoice from Sales Order
CREATE PROCEDURE sp_generate_invoice(IN p_order_id INT, IN p_generated_by INT)
BEGIN
    DECLARE v_total DECIMAL(12,2);

    -- Calculate total
    SELECT SUM(line_total) INTO v_total
    FROM sales_order_items
    WHERE order_id = p_order_id;

    -- Insert invoice
    INSERT INTO invoices(invoice_number, order_id, customer_id, total_amount, generated_by)
    SELECT CONCAT('INV-', LPAD(p_order_id,6,'0')), order_id, customer_id, v_total, p_generated_by
    FROM sales_orders WHERE order_id = p_order_id;
END$$

-- ============================================
-- 3. Reserve Stock for Pending Sales Orders
CREATE PROCEDURE sp_reserve_stock(IN p_order_id INT)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_qty DECIMAL(12,2);
    DECLARE so_cursor CURSOR FOR
        SELECT item_id, quantity FROM sales_order_items WHERE order_id = p_order_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_item_id = NULL;

    OPEN so_cursor;
    read_loop: LOOP
        FETCH so_cursor INTO v_item_id, v_qty;
        IF v_item_id IS NULL THEN
            LEAVE read_loop;
        END IF;

        -- Check stock availability
        IF (SELECT quantity FROM items WHERE item_id = v_item_id) < v_qty THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = CONCAT('Insufficient stock for item ID ', v_item_id);
        ELSE
            -- Deduct reserved stock
            UPDATE items SET quantity = quantity - v_qty, updated_at = CURRENT_TIMESTAMP
            WHERE item_id = v_item_id;

            INSERT INTO transactions(item_id, user_id, transaction_type, quantity, notes)
            VALUES(v_item_id, p_order_id, 'OUT', v_qty, CONCAT('Reserved for Sales Order #', p_order_id));
        END IF;
    END LOOP;

    CLOSE so_cursor;
END$$

DELIMITER ;