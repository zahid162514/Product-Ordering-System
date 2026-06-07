-- SmartStock grouped orders migration
-- Run this once on products_ordering_db before using the updated order pages.
-- Safe to rerun: table creation is guarded and legacy data backfill skips rows
-- already migrated through legacy_order_id / existing order-item matches.

CREATE TABLE IF NOT EXISTS tbl_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(25) NOT NULL DEFAULT 'Pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    stock_restored TINYINT(1) NOT NULL DEFAULT 0,
    legacy_order_id INT NULL UNIQUE,
    INDEX idx_tbl_orders_customer_id (customer_id),
    INDEX idx_tbl_orders_status (status),
    CONSTRAINT fk_tbl_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customer_registration(customer_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_order_items (
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
    CONSTRAINT fk_tbl_order_items_order
        FOREIGN KEY (order_id) REFERENCES tbl_orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tbl_order_items_product
        FOREIGN KEY (product_id) REFERENCES tbl_product(product_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tbl_orders (customer_id, total_amount, status, order_date, cancelled_at, stock_restored, legacy_order_id)
SELECT
    o.customer_id,
    COALESCE(o.total, 0),
    CASE
        WHEN o.status = 'Canceled' THEN 'Cancelled'
        WHEN o.status = 'Cooking' THEN 'Processing'
        WHEN o.status = 'On the way' THEN 'Processing'
        ELSE COALESCE(o.status, 'Pending')
    END,
    o.order_date,
    CASE WHEN o.status IN ('Cancelled', 'Canceled') THEN o.order_date ELSE NULL END,
    CASE WHEN o.status IN ('Cancelled', 'Canceled') THEN 1 ELSE 0 END,
    o.order_id
FROM tbl_order o
LEFT JOIN tbl_orders existing ON existing.legacy_order_id = o.order_id
WHERE existing.id IS NULL;

INSERT INTO tbl_order_items (order_id, product_id, product_name_snapshot, quantity, unit_price, line_total, created_at)
SELECT
    no.id,
    o.product_id,
    p.title,
    o.quantity,
    COALESCE(o.unit_price, o.total / NULLIF(o.quantity, 0), 0),
    COALESCE(o.total, 0),
    o.order_date
FROM tbl_order o
JOIN tbl_orders no ON no.legacy_order_id = o.order_id
LEFT JOIN tbl_product p ON p.product_id = o.product_id
LEFT JOIN tbl_order_items existing ON existing.order_id = no.id AND existing.product_id <=> o.product_id
WHERE existing.id IS NULL;
