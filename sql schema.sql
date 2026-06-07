-- ============================================================================
-- SmartStock - Product Ordering and Inventory Management Database Schema
-- Company: Laobaan Bangladesh LTD.
-- This file represents the current full schema for a fresh installation.
-- It also includes a small demo seed so a new install can be used quickly.
-- The migration files are for upgrading older databases and should not be run
-- after importing this full schema from scratch.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS products_ordering_db;
USE products_ordering_db;

CREATE TABLE tbl_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    image_name VARCHAR(255) NULL,
    featured VARCHAR(10) NOT NULL DEFAULT 'No',
    active VARCHAR(10) NOT NULL DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbl_category_active_featured_title (active, featured, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_product (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(80) NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2) NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    image_name VARCHAR(255) NULL,
    category_id INT NULL,
    featured VARCHAR(10) NOT NULL DEFAULT 'No',
    active VARCHAR(10) NOT NULL DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbl_product_sku (sku),
    INDEX idx_tbl_product_title (title),
    INDEX idx_tbl_product_active_category_stock (active, category_id, stock_quantity),
    FOREIGN KEY (category_id) REFERENCES tbl_category(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_product_variants (
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
    INDEX idx_product_variants_product_active (product_id, active),
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_registration (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    company_name VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    customer_email VARCHAR(150) UNIQUE NOT NULL,
    customer_address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    password VARCHAR(255) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id) ON DELETE CASCADE,
    INDEX idx_password_resets_token_hash (token_hash),
    INDEX idx_password_resets_expires_at (expires_at),
    INDEX idx_password_resets_customer_used (customer_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(200) UNIQUE NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy single-line-order table retained for compatibility with old installs.

CREATE TABLE tbl_order (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    customer_id INT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NULL,
    total DECIMAL(10,2) NULL,
    status VARCHAR(25) NOT NULL DEFAULT 'Pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    coupon_code VARCHAR(60) NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(25) NOT NULL DEFAULT 'Pending',
    delivery_name VARCHAR(150) NULL,
    delivery_phone VARCHAR(30) NULL,
    delivery_address VARCHAR(255) NULL,
    delivery_city VARCHAR(100) NULL,
    expected_delivery_date DATE NULL,
    courier_name VARCHAR(120) NULL,
    tracking_number VARCHAR(120) NULL,
    payment_method VARCHAR(60) NULL,
    payment_status VARCHAR(40) NOT NULL DEFAULT 'Unpaid',
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    receipt_image VARCHAR(255) NULL,
    customer_note TEXT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    stock_restored TINYINT(1) NOT NULL DEFAULT 0,
    legacy_order_id INT NULL UNIQUE,
    INDEX idx_tbl_orders_customer_id (customer_id),
    INDEX idx_tbl_orders_status (status),
    INDEX idx_tbl_orders_order_date (order_date),
    INDEX idx_tbl_orders_payment_status (payment_status),
    INDEX idx_tbl_orders_tracking_number (tracking_number),
    INDEX idx_tbl_orders_customer_date (customer_id, order_date),
    INDEX idx_tbl_orders_status_date (status, order_date),
    INDEX idx_tbl_orders_payment_date (payment_status, order_date),
    INDEX idx_tbl_orders_coupon_code (coupon_code),
    FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name_snapshot VARCHAR(150) NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tbl_order_items_order_id (order_id),
    INDEX idx_tbl_order_items_product_id (product_id),
    INDEX idx_tbl_order_items_order_product (order_id, product_id),
    FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    gateway VARCHAR(40) NOT NULL DEFAULT 'sslcommerz',
    transaction_id VARCHAR(80) NOT NULL UNIQUE,
    val_id VARCHAR(120) NULL,
    bank_tran_id VARCHAR(120) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'BDT',
    status VARCHAR(40) NOT NULL DEFAULT 'Initiated',
    session_key VARCHAR(120) NULL,
    gateway_url TEXT NULL,
    card_type VARCHAR(120) NULL,
    card_issuer VARCHAR(120) NULL,
    card_brand VARCHAR(120) NULL,
    risk_level VARCHAR(40) NULL,
    raw_init_response LONGTEXT NULL,
    raw_ipn_payload LONGTEXT NULL,
    raw_validation_response LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_payment_transactions_order_id (order_id),
    INDEX idx_payment_transactions_status (status),
    INDEX idx_payment_transactions_gateway (gateway),
    FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by_admin_id INT NULL,
    changed_by_customer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_status_history_order_id (order_id),
    INDEX idx_order_status_history_order_created (order_id, created_at),
    FOREIGN KEY (order_id) REFERENCES tbl_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL,
    FOREIGN KEY (changed_by_customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_inventory_adjustments (
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
    INDEX idx_inventory_adjustments_variant_id (variant_id),
    INDEX idx_inventory_adjustments_related_order_id (related_order_id),
    INDEX idx_inventory_adjustments_admin_id (admin_id),
    INDEX idx_inventory_adjustments_customer_id (customer_id),
    INDEX idx_inventory_adjustments_product_created (product_id, created_at),
    FOREIGN KEY (product_id) REFERENCES tbl_product(product_id) ON DELETE SET NULL,
    FOREIGN KEY (variant_id) REFERENCES tbl_product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (related_order_id) REFERENCES tbl_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_coupons (
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
    INDEX idx_tbl_coupons_active_code (active, code),
    INDEX idx_tbl_coupons_validity_window (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    message TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Open',
    assigned_admin_id INT NULL,
    admin_notes TEXT NULL,
    reply_message TEXT NULL,
    replied_at DATETIME NULL,
    resolved_at DATETIME NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_status (status),
    INDEX idx_contact_messages_submitted_at (submitted_at),
    INDEX idx_contact_messages_assigned_status (assigned_admin_id, status),
    FOREIGN KEY (assigned_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    subject VARCHAR(150) NULL,
    message TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Open',
    assigned_admin_id INT NULL,
    admin_notes TEXT NULL,
    reply_message TEXT NULL,
    replied_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customers_sms_status (status),
    INDEX idx_customers_sms_created_at (created_at),
    INDEX idx_customers_sms_assigned_status (assigned_admin_id, status),
    FOREIGN KEY (assigned_admin_id) REFERENCES tbl_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Quick demo seed data for fresh installs
-- Login details:
--   Admin username: admin
--   Admin password: Admin@12345
--   Customer email: customer@smartstock.local
--   Customer password: Customer@12345
-- ============================================================================

INSERT IGNORE INTO tbl_category (id, title, image_name, featured, active) VALUES
    (1, 'Office Supplies', NULL, 'Yes', 'Yes'),
    (2, 'Electronics', NULL, 'Yes', 'Yes'),
    (3, 'Food and Drinks', NULL, 'No', 'Yes');

INSERT IGNORE INTO tbl_product (
    product_id, sku, title, description, price, original_price, stock_quantity,
    reorder_level, image_name, category_id, featured, active
) VALUES
    (
        1,
        'ELEC-MOUSE-001',
        'Wireless Mouse',
        'Comfortable wireless mouse for everyday office and home use.',
        1250.00,
        1490.00,
        25,
        10,
        NULL,
        2,
        'Yes',
        'Yes'
    ),
    (
        2,
        'ELEC-CHARGER-001',
        'USB-C Fast Charger',
        'Compact fast charger with dependable daily charging performance.',
        890.00,
        1090.00,
        20,
        8,
        NULL,
        2,
        'Yes',
        'Yes'
    ),
    (
        3,
        'OFF-NOTEBOOK-001',
        'A5 Notebook Pack',
        'Pack of premium A5 notebooks for meetings, notes, and planning.',
        320.00,
        399.00,
        60,
        15,
        NULL,
        1,
        'No',
        'Yes'
    ),
    (
        4,
        'FOOD-TEA-001',
        'Premium Tea Box',
        'A small everyday tea selection for office pantry and home use.',
        450.00,
        NULL,
        40,
        12,
        NULL,
        3,
        'No',
        'Yes'
    );

INSERT IGNORE INTO tbl_product_variants (
    id, product_id, sku, variant_name, size, color, pack_size,
    stock_quantity, price_adjustment, active
) VALUES
    (1, 1, 'ELEC-MOUSE-001-BLK', 'Black', NULL, 'Black', NULL, 15, 0.00, 'Yes'),
    (2, 3, 'OFF-NOTEBOOK-001-3PK', 'Pack of 3', NULL, NULL, '3 notebooks', 20, 50.00, 'Yes');

INSERT IGNORE INTO customer_registration (
    customer_id, customer_name, company_name, phone, customer_email,
    customer_address, city, password
) VALUES
    (
        1,
        'Demo Customer',
        'Demo Trading Co.',
        '01700000000',
        'customer@smartstock.local',
        'Dhaka, Bangladesh',
        'Dhaka',
        '$2y$10$T78WZUcPWyqmoRcbHdOXke6ce2wVbzsAMprBn8v.RhokgvE1A5lUG'
    );

INSERT IGNORE INTO tbl_admin (
    id, full_name, username, email, password, role
) VALUES
    (
        1,
        'Demo Administrator',
        'admin',
        'admin@smartstock.local',
        '$2y$10$0NXu1LUe7qPJtMqJnWPTsOyHzrFb8xfbqEkPrMowSXorhcbizfXgm',
        'super_admin'
    );

INSERT IGNORE INTO tbl_coupons (
    id, code, description, discount_type, discount_value, min_order_amount,
    active, starts_at, ends_at, usage_limit, used_count
) VALUES
    (
        1,
        'WELCOME10',
        'Quick-start welcome coupon for demo orders.',
        'percentage',
        10.00,
        1000.00,
        'Yes',
        NULL,
        NULL,
        100,
        0
    );
