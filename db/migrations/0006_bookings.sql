-- Reservations: capacity-booked against booking_slots; a physical `tables` row
-- is assigned at seat time (bookings.table_id NULL until seated) — C-V5-1.

CREATE TABLE booking_resources (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    type        ENUM('dining','event') NOT NULL DEFAULT 'dining',
    capacity_per_slot INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Physical floor (host assigns at seat time).
CREATE TABLE tables (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    label       VARCHAR(40) NOT NULL,
    section     VARCHAR(60) NULL,
    min_party   INT NOT NULL DEFAULT 1,
    max_party   INT NOT NULL DEFAULT 4,
    status      ENUM('open','seated','dirty','blocked') NOT NULL DEFAULT 'open',
    sort        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_table_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-generated rolling slots; booked_count guarded atomically (no double-book).
CREATE TABLE booking_slots (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id  BIGINT UNSIGNED NOT NULL,
    slot_start   DATETIME NOT NULL,
    capacity     INT NOT NULL DEFAULT 0,
    booked_count INT NOT NULL DEFAULT 0,
    is_blackout  TINYINT(1) NOT NULL DEFAULT 0,
    event_product_id BIGINT UNSIGNED NULL,                -- Sunday Supper / ticketed event
    PRIMARY KEY (id),
    UNIQUE KEY uq_bslot (resource_id, slot_start),
    KEY idx_bslot_window (slot_start),
    CONSTRAINT fk_bslot_resource FOREIGN KEY (resource_id) REFERENCES booking_resources (id) ON DELETE CASCADE,
    CONSTRAINT fk_bslot_event FOREIGN KEY (event_product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_token CHAR(32) NOT NULL,                       -- tokenized modify/cancel link
    slot_id      BIGINT UNSIGNED NOT NULL,
    table_id     BIGINT UNSIGNED NULL,                    -- assigned at seat time
    customer_id  BIGINT UNSIGNED NULL,
    party_size   INT NOT NULL DEFAULT 1,
    status       ENUM('held','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'held',
    deposit_order_id BIGINT UNSIGNED NULL,                -- large-party/event deposit (Stripe)
    contact_name  VARCHAR(160) NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(32) NULL,
    notes        VARCHAR(500) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_token (public_token),
    KEY idx_booking_slot (slot_id),
    KEY idx_booking_status (status),
    CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id) REFERENCES booking_slots (id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_table FOREIGN KEY (table_id) REFERENCES tables (id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_deposit FOREIGN KEY (deposit_order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waitlist (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot_id      BIGINT UNSIGNED NULL,
    party_size   INT NOT NULL DEFAULT 1,
    contact_name  VARCHAR(160) NULL,
    contact_phone VARCHAR(32) NULL,
    contact_email VARCHAR(255) NULL,
    status       ENUM('waiting','offered','converted','expired') NOT NULL DEFAULT 'waiting',
    offered_at   DATETIME NULL,
    offer_expires_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_waitlist_status (status),
    CONSTRAINT fk_waitlist_slot FOREIGN KEY (slot_id) REFERENCES booking_slots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
