-- Customers, staff users, audit log, and abuse-prevention tables.
-- Money is integer cents; timestamps are UTC (stored via the +00:00 connection tz).

-- Customers. Guests have no password_hash; optional accounts set one.
CREATE TABLE customers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(32) NULL,
    first_name      VARCHAR(120) NULL,
    last_name       VARCHAR(120) NULL,
    password_hash   VARCHAR(255) NULL,
    must_reset_password TINYINT(1) NOT NULL DEFAULT 0,   -- Woo imports force a reset (C-MIG-6)
    notify_channel  ENUM('email','sms','both') NOT NULL DEFAULT 'email', -- C-V5
    marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    stripe_customer_id VARCHAR(64) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved favorites (account portal reorder / quick-add) — C-V7-5.
CREATE TABLE customer_favorites (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fav (customer_id, product_id),
    CONSTRAINT fk_fav_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved addresses. Dormant for shipping until the wine DTC gate clears; billing
-- address may feed Stripe/AVS (C-V7-5).
CREATE TABLE customer_addresses (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    label       VARCHAR(60) NULL,
    line1       VARCHAR(180) NOT NULL,
    line2       VARCHAR(180) NULL,
    city        VARCHAR(120) NOT NULL,
    state       CHAR(2) NOT NULL,
    postal      VARCHAR(12) NOT NULL,
    purpose     ENUM('billing','shipping') NOT NULL DEFAULT 'shipping',
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_addr_customer (customer_id),
    CONSTRAINT fk_addr_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff users with role-based access.
CREATE TABLE users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255) NOT NULL,
    name          VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only audit log for sensitive actions (settings, comps/voids, roles) — C-DATA-3.
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type  ENUM('user','customer','system') NOT NULL DEFAULT 'user',
    actor_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id   VARCHAR(64) NULL,
    before_json LONGTEXT NULL,
    after_json  LONGTEXT NULL,
    ip          VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_actor (actor_type, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed-login tracking for lockout (5x/15min) — C-T1-7.
CREATE TABLE login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip          VARCHAR(45) NOT NULL,
    username    VARCHAR(255) NOT NULL,
    successful  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_lookup (ip, username, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic sliding-window rate limiter (checkout, reset, QR-feedback POST) — C-T1-7.
CREATE TABLE rate_limits (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rl_key      VARCHAR(160) NOT NULL,
    window_start DATETIME NOT NULL,
    counter     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rl (rl_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
