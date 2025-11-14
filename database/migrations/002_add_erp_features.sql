-- ============================================
-- ERP Feature Migration - Production Ready
-- Adds: BOM, Serial/Batch Tracking, Warehouse Management
-- Date: 2025-11-15
-- ============================================
USE janstro_inventory;

-- ============================================
-- 1. WAREHOUSES
-- ============================================
CREATE TABLE IF NOT EXISTS warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_code VARCHAR(50) NOT NULL UNIQUE,
    warehouse_name VARCHAR(255) NOT NULL,
    address TEXT,
    manager_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_warehouse_code (warehouse_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. BATCH MANAGEMENT
-- ============================================
CREATE TABLE IF NOT EXISTS batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(100) NOT NULL UNIQUE,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    manufacturing_date DATE,
    expiry_date DATE,
    status ENUM('active', 'expired', 'recalled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    INDEX idx_batch_number (batch_number),
    INDEX idx_item (item_id),
    INDEX idx_expiry (expiry_date),
    CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. SERIAL NUMBER TRACKING
-- ============================================
CREATE TABLE IF NOT EXISTS serial_numbers (
    serial_id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(100) NOT NULL UNIQUE,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    status ENUM('in_stock', 'sold', 'defective', 'returned') DEFAULT 'in_stock',
    sales_order_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sold_date TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(order_id) ON DELETE SET NULL,
    INDEX idx_serial_number (serial_number),
    INDEX idx_item (item_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. STOCK LOCATION (Bin Management)
-- ============================================
CREATE TABLE IF NOT EXISTS stock_locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    aisle VARCHAR(10),
    rack VARCHAR(10),
    shelf VARCHAR(10),
    bin VARCHAR(10),
    location_code VARCHAR(50) NOT NULL UNIQUE,
    capacity DECIMAL(12,2),
    status ENUM('active', 'maintenance', 'full') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE,
    INDEX idx_location_code (location_code),
    INDEX idx_warehouse (warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. ITEM LOCATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS item_locations (
    item_location_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    location_id INT NOT NULL,
    batch_id INT,
    quantity DECIMAL(12,2) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES stock_locations(location_id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL,
    UNIQUE KEY unique_item_location (item_id, location_id, batch_id),
    INDEX idx_item (item_id),
    INDEX idx_location (location_id),
    CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. REORDER ALERTS
-- ============================================
CREATE TABLE IF NOT EXISTS reorder_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    alert_level ENUM('low', 'critical', 'out_of_stock') NOT NULL,
    current_quantity DECIMAL(12,2) NOT NULL,
    reorder_level DECIMAL(12,2) NOT NULL,
    suggested_order_qty DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'acknowledged', 'ordered', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by INT,
    acknowledged_at TIMESTAMP NULL,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_item (item_id),
    INDEX idx_alert_level (alert_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. PRODUCTION ORDERS
-- ============================================
CREATE TABLE IF NOT EXISTS production_orders (
    production_order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    finished_item_id INT NOT NULL,
    quantity_planned DECIMAL(12,2) NOT NULL,
    quantity_produced DECIMAL(12,2) DEFAULT 0,
    warehouse_id INT NOT NULL,
    status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    planned_start_date DATE,
    actual_start_date DATE,
    planned_end_date DATE,
    actual_end_date DATE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (finished_item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_finished_item (finished_item_id),
    CHECK (quantity_planned > 0),
    CHECK (quantity_produced >= 0),
    CHECK (quantity_produced <= quantity_planned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. PRODUCTION ORDER MATERIALS
-- ============================================
CREATE TABLE IF NOT EXISTS production_order_materials (
    po_material_id INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT NOT NULL,
    material_item_id INT NOT NULL,
    quantity_required DECIMAL(12,2) NOT NULL,
    quantity_consumed DECIMAL(12,2) DEFAULT 0,
    warehouse_id INT NOT NULL,
    status ENUM('pending', 'issued', 'consumed') DEFAULT 'pending',
    issued_at TIMESTAMP NULL,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(production_order_id) ON DELETE CASCADE,
    FOREIGN KEY (material_item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    INDEX idx_production_order (production_order_id),
    INDEX idx_material (material_item_id),
    CHECK (quantity_required > 0),
    CHECK (quantity_consumed >= 0),
    CHECK (quantity_consumed <= quantity_required)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. STOCK TRANSFERS
-- ============================================
CREATE TABLE IF NOT EXISTS stock_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) NOT NULL UNIQUE,
    item_id INT NOT NULL,
    from_warehouse_id INT NOT NULL,
    to_warehouse_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'in_transit', 'received', 'cancelled') DEFAULT 'pending',
    requested_by INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shipped_by INT,
    shipped_at TIMESTAMP NULL,
    received_by INT,
    received_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (shipped_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_transfer_number (transfer_number),
    INDEX idx_status (status),
    INDEX idx_item (item_id),
    CHECK (quantity > 0),
    CHECK (from_warehouse_id != to_warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. ENHANCED TRANSACTIONS
-- ============================================
ALTER TABLE transactions
    ADD COLUMN warehouse_id INT AFTER user_id,
    ADD COLUMN batch_id INT AFTER warehouse_id,
    ADD COLUMN serial_id INT AFTER batch_id,
    ADD COLUMN reference_type ENUM('purchase_order','sales_order','production_order','transfer','adjustment','other') AFTER serial_id,
    ADD COLUMN reference_id INT AFTER reference_type;

ALTER TABLE transactions
    ADD FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE SET NULL,
    ADD FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE SET NULL,
    ADD FOREIGN KEY (serial_id) REFERENCES serial_numbers(serial_id) ON DELETE SET NULL;

ALTER TABLE transactions
    ADD INDEX idx_warehouse (warehouse_id),
    ADD INDEX idx_batch (batch_id),
    ADD INDEX idx_reference (reference_type, reference_id);

-- ============================================
-- 11. INSERT DEFAULT WAREHOUSE
-- ============================================
INSERT IGNORE INTO warehouses (warehouse_code, warehouse_name, address, status)
VALUES ('MAIN', 'Main Warehouse', 'Janstro HQ - Calamba, Laguna', 'active');

-- ============================================
-- 12. TRIGGER: Auto-create reorder alerts
-- ============================================
DELIMITER $$

DROP TRIGGER IF EXISTS tr_check_reorder_level $$

CREATE TRIGGER tr_check_reorder_level
AFTER UPDATE ON items
FOR EACH ROW
BEGIN
    DECLARE v_warehouse_id INT;
    DECLARE v_alert_exists INT;

    SELECT warehouse_id INTO v_warehouse_id
    FROM warehouses
    WHERE warehouse_code = 'MAIN'
    LIMIT 1;

    IF NEW.quantity <= NEW.reorder_level
       AND NEW.quantity < OLD.quantity THEN

        SELECT COUNT(*) INTO v_alert_exists
        FROM reorder_alerts
        WHERE item_id = NEW.item_id
          AND warehouse_id = v_warehouse_id
          AND status IN ('pending', 'acknowledged');

        IF v_alert_exists = 0 THEN
            INSERT INTO reorder_alerts (
                item_id, warehouse_id, alert_level,
                current_quantity, reorder_level,
                suggested_order_qty, status
            ) VALUES (
                NEW.item_id,
                v_warehouse_id,
                CASE
                    WHEN NEW.quantity = 0 THEN 'out_of_stock'
                    WHEN NEW.quantity < (NEW.reorder_level * 0.5) THEN 'critical'
                    ELSE 'low'
                END,
                NEW.quantity,
                NEW.reorder_level,
                GREATEST(NEW.reorder_level * 2 - NEW.quantity, NEW.reorder_level),
                'pending'
            );
        END IF;
    END IF;
END $$

DELIMITER ;

-- ============================================
-- 13. STORED PROCEDURE: Create Production Order w/ BOM
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_production_order $$

CREATE PROCEDURE sp_create_production_order(
    IN p_order_number VARCHAR(50),
    IN p_finished_item_id INT,
    IN p_quantity_planned DECIMAL(12,2),
    IN p_warehouse_id INT,
    IN p_created_by INT,
    OUT p_production_order_id INT
)
BEGIN
    DECLARE v_bom_exists INT;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_bom_exists
    FROM bill_of_materials
    WHERE item_id = p_finished_item_id;

    IF v_bom_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No Bill of Materials found for this item';
    END IF;

    INSERT INTO production_orders (
        order_number, finished_item_id,
        quantity_planned, warehouse_id,
        status,
        planned_start_date, planned_end_date,
        created_by
    ) VALUES (
        p_order_number,
        p_finished_item_id,
        p_quantity_planned,
        p_warehouse_id,
        'planned',
        CURDATE(),
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        p_created_by
    );

    SET p_production_order_id = LAST_INSERT_ID();

    INSERT INTO production_order_materials (
        production_order_id,
        material_item_id,
        quantity_required,
        warehouse_id,
        status
    )
    SELECT
        p_production_order_id,
        component_item_id,
        quantity_required * p_quantity_planned,
        p_warehouse_id,
        'pending'
    FROM bill_of_materials
    WHERE item_id = p_finished_item_id;

    COMMIT;
END $$

DELIMITER ;

-- ============================================
-- 14. VIEW: Inventory by Warehouse
-- ============================================
CREATE OR REPLACE VIEW v_inventory_by_warehouse AS
SELECT
    w.warehouse_code,
    w.warehouse_name,
    i.item_id,
    i.item_name,
    c.name AS category_name,
    COALESCE(SUM(il.quantity), 0) AS total_quantity,
    i.unit,
    i.reorder_level,
    CASE
        WHEN COALESCE(SUM(il.quantity), 0) = 0 THEN 'OUT'
        WHEN COALESCE(SUM(il.quantity), 0) <= i.reorder_level THEN 'LOW'
        ELSE 'OK'
    END AS stock_status
FROM warehouses w
CROSS JOIN items i
LEFT JOIN categories c ON i.category_id = c.category_id
LEFT JOIN item_locations il ON i.item_id = il.item_id
    AND il.location_id IN (
        SELECT location_id
        FROM stock_locations
        WHERE warehouse_id = w.warehouse_id
    )
WHERE w.status = 'active'
GROUP BY w.warehouse_id, i.item_id
ORDER BY w.warehouse_code, stock_status DESC, i.item_name;

-- ============================================
-- 15. VIEW: Active Reorder Alerts
-- ============================================
CREATE OR REPLACE VIEW v_active_reorder_alerts AS
SELECT
    ra.alert_id,
    ra.alert_level,
    i.item_name,
    c.name AS category_name,
    w.warehouse_name,
    ra.current_quantity,
    ra.reorder_level,
    ra.suggested_order_qty,
    i.unit_price,
    (ra.suggested_order_qty * i.unit_price) AS estimated_cost,
    ra.created_at,
    TIMESTAMPDIFF(HOUR, ra.created_at, NOW()) AS hours_pending
FROM reorder_alerts ra
JOIN items i ON ra.item_id = i.item_id
LEFT JOIN categories c ON i.category_id = c.category_id
JOIN warehouses w ON ra.warehouse_id = w.warehouse_id
WHERE ra.status = 'pending'
ORDER BY
    FIELD(ra.alert_level, 'out_of_stock', 'critical', 'low'),
    ra.created_at ASC;

-- ============================================
-- MIGRATION COMPLETE
-- ============================================
SELECT 'ERP Migration completed successfully!' AS message;
