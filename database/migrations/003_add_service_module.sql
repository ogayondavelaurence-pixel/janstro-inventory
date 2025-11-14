-- ============================================
-- Service Module Migration
-- Based on: Operations-Management-with-Analytics Exercise 8.1
-- ============================================
USE janstro_inventory;

-- ============================================
-- 1. SERVICE CALLS
-- ============================================
CREATE TABLE IF NOT EXISTS service_calls (
    service_call_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    item_id INT NOT NULL,
    serial_number VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    service_type ENUM('warranty', 'maintenance', 'repair', 'installation') DEFAULT 'warranty',
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'cancelled') DEFAULT 'open',
    assignee_id INT,
    open_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    scheduled_start DATETIME,
    scheduled_end DATETIME,
    resolved_datetime DATETIME NULL,
    resolution TEXT,
    closed_by INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    FOREIGN KEY (assignee_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_service_type (service_type),
    INDEX idx_customer (customer_id),
    INDEX idx_open_date (open_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. SERVICE ACTIVITIES
-- ============================================
CREATE TABLE IF NOT EXISTS service_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    service_call_id INT NOT NULL,
    activity_type ENUM('task', 'phone_call', 'visit', 'inspection', 'repair') DEFAULT 'task',
    description TEXT NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME NULL,
    technician_id INT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_call_id) REFERENCES service_calls(service_call_id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_service_call (service_call_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. SERVICE SOLUTIONS (Knowledge Base)
-- ============================================
CREATE TABLE IF NOT EXISTS service_solutions (
    solution_id INT AUTO_INCREMENT PRIMARY KEY,
    service_call_id INT NOT NULL,
    problem_description TEXT NOT NULL,
    root_cause TEXT,
    solution_description TEXT NOT NULL,
    parts_used TEXT,
    resolved_by INT NOT NULL,
    resolved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_call_id) REFERENCES service_calls(service_call_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_service_call (service_call_id),
    INDEX idx_resolved_at (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. SERVICE PARTS CONSUMED
-- ============================================
CREATE TABLE IF NOT EXISTS service_parts (
    service_part_id INT AUTO_INCREMENT PRIMARY KEY,
    service_call_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    notes TEXT,
    consumed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_call_id) REFERENCES service_calls(service_call_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    INDEX idx_service_call (service_call_id),
    CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. VIEW: Active Service Calls Dashboard
-- 
CREATE OR REPLACE VIEW v_active_service_calls AS
SELECT 
    sc.service_call_id,
    sc.subject,
    sc.priority,
    sc.status,
    sc.service_type,
    c.customer_name,
    c.contact_no,
    i.item_name,
    sc.open_datetime,
    sc.scheduled_start,
    u.name AS assignee_name,
    TIMESTAMPDIFF(HOUR, sc.open_datetime, NOW()) AS hours_open
FROM service_calls sc
LEFT JOIN customers c ON sc.customer_id = c.customer_id
LEFT JOIN items i ON sc.item_id = i.item_id
LEFT JOIN users u ON sc.assignee_id = u.user_id
WHERE sc.status IN ('open', 'in_progress')
ORDER BY 
    FIELD(sc.priority, 'high', 'medium', 'low'),
    sc.open_datetime ASC;

-- ============================================
-- MIGRATION COMPLETE
-- ============================================
SELECT 'Service Module Migration completed successfully!' AS message;