-- Orders, snapshotted line items, tax lines, discounts/promotions, pickup
-- slots, inventory holds, and catering requests. Orders are created `pending`
-- at checkout initiation and flipped to `paid` by the Stripe webhook.

CREATE TABLE orders (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_token    CHAR(32) NOT NULL,                    -- tokenized status/cancel link (no enumeration)
    customer_id     BIGINT UNSIGNED NULL,                 -- NULL = pure guest (data still in customers)
    kind            ENUM('standard','catering_deposit','catering_balance') NOT NULL DEFAULT 'standard',
    catering_request_id BIGINT UNSIGNED NULL,
    fulfillment     ENUM('pickup','delivery','dine_in','shipping') NOT NULL DEFAULT 'pickup',
    status          ENUM('pending','paid','preparing','ready','fulfilled','cancelled','refunded','partially_refunded','comped','voided','disputed','expired') NOT NULL DEFAULT 'pending',
    -- money: all integer cents, snapshotted at creation
    subtotal_cents  BIGINT NOT NULL DEFAULT 0,
    discount_cents  BIGINT NOT NULL DEFAULT 0,
    tax_cents       BIGINT NOT NULL DEFAULT 0,
    tip_cents       BIGINT NOT NULL DEFAULT 0,
    total_cents     BIGINT NOT NULL DEFAULT 0,
    giftcard_applied_cents BIGINT NOT NULL DEFAULT 0,
    amount_due_cents BIGINT NOT NULL DEFAULT 0,
    contact_email   VARCHAR(255) NULL,
    contact_phone   VARCHAR(32) NULL,
    contact_name    VARCHAR(160) NULL,
    pickup_slot_id  BIGINT UNSIGNED NULL,
    notes           VARCHAR(500) NULL,
    stripe_payment_intent VARCHAR(64) NULL,
    placed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at         DATETIME NULL,
    fulfilled_at    DATETIME NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_token (public_token),
    KEY idx_order_status (status),
    KEY idx_order_customer (customer_id),
    KEY idx_order_placed (placed_at),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshotted line items: never re-read live product price.
CREATE TABLE order_items (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NULL,                 -- NULL-safe: product may be deleted later
    name_snapshot   VARCHAR(200) NOT NULL,
    unit_price_cents BIGINT NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    tax_treatment   ENUM('prepared_food','retail_goods','grocery','service','non_taxable') NOT NULL,
    tax_amount_cents BIGINT NOT NULL DEFAULT 0,
    line_total_cents BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_oi_order (order_id),
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_item_modifiers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_item_id   BIGINT UNSIGNED NOT NULL,
    name_snapshot   VARCHAR(200) NOT NULL,
    price_delta_cents BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_oim_item (order_item_id),
    CONSTRAINT fk_oim_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-category tax lines (prepared food vs retail), snapshotted.
CREATE TABLE order_tax_lines (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NOT NULL,
    tax_category    VARCHAR(40) NOT NULL,
    taxable_base_cents BIGINT NOT NULL DEFAULT 0,
    rate_bps        INT NOT NULL DEFAULT 0,               -- basis points snapshot
    tax_cents       BIGINT NOT NULL DEFAULT 0,
    source          ENUM('internal','stripe_tax') NOT NULL DEFAULT 'internal',
    PRIMARY KEY (id),
    KEY idx_otl_order (order_id),
    CONSTRAINT fk_otl_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotions + snapshotted applied discounts (C-V7-6).
CREATE TABLE promotions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40) NOT NULL,
    type            ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value           INT NOT NULL DEFAULT 0,               -- percent (bps) or fixed cents per `type`
    min_subtotal_cents BIGINT NOT NULL DEFAULT 0,
    starts_at       DATETIME NULL,
    ends_at         DATETIME NULL,
    max_redemptions INT NULL,
    per_customer_limit INT NULL,
    redemption_count INT NOT NULL DEFAULT 0,
    applies_to      ENUM('all','food','market','wine') NOT NULL DEFAULT 'all',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promo_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_discounts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        BIGINT UNSIGNED NOT NULL,
    promotion_id    BIGINT UNSIGNED NULL,
    code_snapshot   VARCHAR(40) NULL,
    discount_amount_cents BIGINT NOT NULL DEFAULT 0,      -- snapshotted, pre-tax
    PRIMARY KEY (id),
    KEY idx_od_order (order_id),
    CONSTRAINT fk_od_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_od_promo FOREIGN KEY (promotion_id) REFERENCES promotions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Maintained AZ TPT rates (fallback / non-Stripe-Tax path) — C-TAX-5.
CREATE TABLE tax_rates (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tax_category ENUM('prepared_food','retail_goods','grocery','service','non_taxable') NOT NULL,
    rate_bps     INT NOT NULL DEFAULT 0,
    label        VARCHAR(120) NULL,
    effective_from DATE NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_taxrate_cat (tax_category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pickup capacity lives ONLY here (C-DATA-2). booked_count guarded atomically.
CREATE TABLE pickup_slots (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slot_start   DATETIME NOT NULL,
    slot_end     DATETIME NOT NULL,
    capacity     INT NOT NULL DEFAULT 0,
    booked_count INT NOT NULL DEFAULT 0,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_slot_start (slot_start),
    KEY idx_slot_window (slot_start, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory holds: created with the pending order, swept on expiry.
CREATE TABLE inventory_holds (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     BIGINT UNSIGNED NOT NULL,
    product_id   BIGINT UNSIGNED NULL,
    quantity     INT NOT NULL DEFAULT 0,
    pickup_slot_id BIGINT UNSIGNED NULL,
    status       ENUM('active','converted','released') NOT NULL DEFAULT 'active',
    expires_at   DATETIME NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hold_order (order_id),
    KEY idx_hold_sweep (status, expires_at),
    KEY idx_hold_product (product_id),
    CONSTRAINT fk_hold_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_hold_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_hold_slot FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catering enquiries the admin prices; acceptance spawns deposit + balance orders.
CREATE TABLE catering_requests (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     BIGINT UNSIGNED NULL,
    offering        ENUM('party_platters','boutique','meal_delivery') NOT NULL DEFAULT 'party_platters',
    event_date      DATE NULL,
    headcount       INT NULL,
    details         TEXT NULL,
    quoted_total_cents BIGINT NULL,
    deposit_cents   BIGINT NULL,
    status          ENUM('new','quoted','accepted','deposit_paid','balance_due','paid','declined','expired') NOT NULL DEFAULT 'new',
    contact_email   VARCHAR(255) NULL,
    contact_phone   VARCHAR(32) NULL,
    contact_name    VARCHAR(160) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cater_status (status),
    CONSTRAINT fk_cater_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Late FK: orders.catering_request_id -> catering_requests.
ALTER TABLE orders
    ADD CONSTRAINT fk_order_catering FOREIGN KEY (catering_request_id) REFERENCES catering_requests (id) ON DELETE SET NULL;
ALTER TABLE orders
    ADD CONSTRAINT fk_order_slot FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots (id) ON DELETE SET NULL;
