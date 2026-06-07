-- SmartStock business workflow expansion
-- Run once after the grouped orders migration.
-- This script is written to be rerunnable on MySQL 5.7+.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product' AND COLUMN_NAME = 'sku') = 0,
    'ALTER TABLE tbl_product ADD COLUMN sku VARCHAR(80) NULL AFTER product_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product' AND COLUMN_NAME = 'reorder_level') = 0,
    'ALTER TABLE tbl_product ADD COLUMN reorder_level INT NOT NULL DEFAULT 10 AFTER stock_quantity',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE tbl_product
    MODIFY COLUMN stock_quantity INT NOT NULL DEFAULT 0,
    MODIFY COLUMN featured VARCHAR(10) NOT NULL DEFAULT 'No',
    MODIFY COLUMN active VARCHAR(10) NOT NULL DEFAULT 'Yes';

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product' AND INDEX_NAME = 'idx_tbl_product_sku') = 0,
    'ALTER TABLE tbl_product ADD INDEX idx_tbl_product_sku (sku)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product' AND INDEX_NAME = 'idx_tbl_product_title') = 0,
    'ALTER TABLE tbl_product ADD INDEX idx_tbl_product_title (title)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product' AND INDEX_NAME = 'idx_tbl_product_active_category_stock') = 0,
    'ALTER TABLE tbl_product ADD INDEX idx_tbl_product_active_category_stock (active, category_id, stock_quantity)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE tbl_category
    MODIFY COLUMN featured VARCHAR(10) NOT NULL DEFAULT 'No',
    MODIFY COLUMN active VARCHAR(10) NOT NULL DEFAULT 'Yes';

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_category' AND INDEX_NAME = 'idx_tbl_category_active_featured_title') = 0,
    'ALTER TABLE tbl_category ADD INDEX idx_tbl_category_active_featured_title (active, featured, title)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'subtotal_amount') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER customer_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER subtotal_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'delivery_fee') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'coupon_code') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN coupon_code VARCHAR(60) NULL AFTER delivery_fee',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'delivery_name') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN delivery_name VARCHAR(150) NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'delivery_phone') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN delivery_phone VARCHAR(30) NULL AFTER delivery_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'delivery_address') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN delivery_address VARCHAR(255) NULL AFTER delivery_phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'delivery_city') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN delivery_city VARCHAR(100) NULL AFTER delivery_address',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'expected_delivery_date') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN expected_delivery_date DATE NULL AFTER delivery_city',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'courier_name') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN courier_name VARCHAR(120) NULL AFTER expected_delivery_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'tracking_number') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN tracking_number VARCHAR(120) NULL AFTER courier_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'payment_method') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN payment_method VARCHAR(60) NULL AFTER tracking_number',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'payment_status') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT ''Unpaid'' AFTER payment_method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'paid_amount') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER payment_status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'due_amount') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN due_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER paid_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'receipt_image') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN receipt_image VARCHAR(255) NULL AFTER due_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND COLUMN_NAME = 'customer_note') = 0,
    'ALTER TABLE tbl_orders ADD COLUMN customer_note TEXT NULL AFTER receipt_image',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_order_date') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_order_date (order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_payment_status') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_payment_status (payment_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_tracking_number') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_tracking_number (tracking_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_customer_date') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_customer_date (customer_id, order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_status_date') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_status_date (status, order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_payment_date') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_payment_date (payment_status, order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_orders' AND INDEX_NAME = 'idx_tbl_orders_coupon_code') = 0,
    'ALTER TABLE tbl_orders ADD INDEX idx_tbl_orders_coupon_code (coupon_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE tbl_orders
SET subtotal_amount = total_amount
WHERE subtotal_amount = 0;

UPDATE tbl_orders
SET due_amount = total_amount - paid_amount
WHERE due_amount = 0;

CREATE TABLE IF NOT EXISTS tbl_order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by_admin_id INT NULL,
    changed_by_customer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_status_history_order_id (order_id),
    FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL,
    FOREIGN KEY (changed_by_customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_order_status_history' AND INDEX_NAME = 'idx_order_status_history_order_created') = 0,
    'ALTER TABLE tbl_order_status_history ADD INDEX idx_order_status_history_order_created (order_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tbl_inventory_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    variant_id INT NULL,
    adjustment_type VARCHAR(40) NOT NULL,
    quantity_change INT NOT NULL,
    stock_after INT NULL,
    reason VARCHAR(255) NULL,
    related_order_id INT NULL,
    admin_id INT NULL,
    customer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_adjustments_product_id (product_id),
    INDEX idx_inventory_adjustments_created_at (created_at),
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE SET NULL,
    FOREIGN KEY (related_order_id) REFERENCES tbl_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND COLUMN_NAME = 'variant_id') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD COLUMN variant_id INT NULL AFTER product_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND INDEX_NAME = 'idx_inventory_adjustments_variant_id') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD INDEX idx_inventory_adjustments_variant_id (variant_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND INDEX_NAME = 'idx_inventory_adjustments_related_order_id') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD INDEX idx_inventory_adjustments_related_order_id (related_order_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND INDEX_NAME = 'idx_inventory_adjustments_admin_id') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD INDEX idx_inventory_adjustments_admin_id (admin_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND INDEX_NAME = 'idx_inventory_adjustments_customer_id') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD INDEX idx_inventory_adjustments_customer_id (customer_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND INDEX_NAME = 'idx_inventory_adjustments_product_created') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD INDEX idx_inventory_adjustments_product_created (product_id, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tbl_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    active VARCHAR(10) NOT NULL DEFAULT 'Yes',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    usage_limit INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbl_coupons_active_code (active, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_coupons' AND INDEX_NAME = 'idx_tbl_coupons_validity_window') = 0,
    'ALTER TABLE tbl_coupons ADD INDEX idx_tbl_coupons_validity_window (starts_at, ends_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tbl_product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sku VARCHAR(80) NULL,
    variant_name VARCHAR(150) NOT NULL,
    size VARCHAR(80) NULL,
    color VARCHAR(80) NULL,
    pack_size VARCHAR(80) NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
    active VARCHAR(10) NOT NULL DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_variants_product_id (product_id),
    INDEX idx_product_variants_sku (sku),
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_product_variants' AND INDEX_NAME = 'idx_product_variants_product_active') = 0,
    'ALTER TABLE tbl_product_variants ADD INDEX idx_product_variants_product_active (product_id, active)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_inventory_adjustments' AND CONSTRAINT_NAME = 'fk_inventory_adjustments_variant' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE tbl_inventory_adjustments ADD CONSTRAINT fk_inventory_adjustments_variant FOREIGN KEY (variant_id) REFERENCES tbl_product_variants(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE contact_messages
    MODIFY COLUMN name VARCHAR(100) NULL,
    MODIFY COLUMN email VARCHAR(150) NULL,
    MODIFY COLUMN message TEXT NULL;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE contact_messages ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT ''Open''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'assigned_admin_id') = 0,
    'ALTER TABLE contact_messages ADD COLUMN assigned_admin_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'admin_notes') = 0,
    'ALTER TABLE contact_messages ADD COLUMN admin_notes TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'reply_message') = 0,
    'ALTER TABLE contact_messages ADD COLUMN reply_message TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'replied_at') = 0,
    'ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'resolved_at') = 0,
    'ALTER TABLE contact_messages ADD COLUMN resolved_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = 'idx_contact_messages_status') = 0,
    'ALTER TABLE contact_messages ADD INDEX idx_contact_messages_status (status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = 'idx_contact_messages_submitted_at') = 0,
    'ALTER TABLE contact_messages ADD INDEX idx_contact_messages_submitted_at (submitted_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = 'idx_contact_messages_assigned_status') = 0,
    'ALTER TABLE contact_messages ADD INDEX idx_contact_messages_assigned_status (assigned_admin_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND CONSTRAINT_NAME = 'fk_contact_messages_assigned_admin' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE contact_messages ADD CONSTRAINT fk_contact_messages_assigned_admin FOREIGN KEY (assigned_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE customers_sms
    MODIFY COLUMN name VARCHAR(100) NULL,
    MODIFY COLUMN email VARCHAR(150) NULL,
    MODIFY COLUMN subject VARCHAR(150) NULL,
    MODIFY COLUMN message TEXT NULL;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE customers_sms ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT ''Open''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'assigned_admin_id') = 0,
    'ALTER TABLE customers_sms ADD COLUMN assigned_admin_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'admin_notes') = 0,
    'ALTER TABLE customers_sms ADD COLUMN admin_notes TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'reply_message') = 0,
    'ALTER TABLE customers_sms ADD COLUMN reply_message TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'replied_at') = 0,
    'ALTER TABLE customers_sms ADD COLUMN replied_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND COLUMN_NAME = 'resolved_at') = 0,
    'ALTER TABLE customers_sms ADD COLUMN resolved_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND INDEX_NAME = 'idx_customers_sms_status') = 0,
    'ALTER TABLE customers_sms ADD INDEX idx_customers_sms_status (status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND INDEX_NAME = 'idx_customers_sms_created_at') = 0,
    'ALTER TABLE customers_sms ADD INDEX idx_customers_sms_created_at (created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND INDEX_NAME = 'idx_customers_sms_assigned_status') = 0,
    'ALTER TABLE customers_sms ADD INDEX idx_customers_sms_assigned_status (assigned_admin_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers_sms' AND CONSTRAINT_NAME = 'fk_customers_sms_assigned_admin' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE customers_sms ADD CONSTRAINT fk_customers_sms_assigned_admin FOREIGN KEY (assigned_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND INDEX_NAME = 'idx_password_resets_customer_used') = 0,
    'ALTER TABLE password_resets ADD INDEX idx_password_resets_customer_used (customer_id, used_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_order_items' AND INDEX_NAME = 'idx_tbl_order_items_order_product') = 0,
    'ALTER TABLE tbl_order_items ADD INDEX idx_tbl_order_items_order_product (order_id, product_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE tbl_admin
SET role = 'super_admin'
WHERE role IN ('admin', '', 'administrator')
ORDER BY id ASC
LIMIT 1;
