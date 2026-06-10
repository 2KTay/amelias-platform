-- Wine Club subscriptions via Stripe Billing. subscription_tiers define the
-- plans; subscriptions track member lifecycle mirrored from Stripe webhooks.

CREATE TABLE subscription_tiers (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(80) NOT NULL,
    name            VARCHAR(160) NOT NULL,
    price_cents     BIGINT NOT NULL DEFAULT 0,
    `interval`      ENUM('month','year') NOT NULL DEFAULT 'month',
    stripe_price_id VARCHAR(64) NULL,
    perks           TEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tier_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     BIGINT UNSIGNED NOT NULL,
    tier_id         BIGINT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(64) NULL,
    status          ENUM('active','paused','past_due','canceled','incomplete') NOT NULL DEFAULT 'incomplete',
    current_period_end DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sub_customer (customer_id),
    KEY idx_sub_status (status),
    UNIQUE KEY uq_sub_stripe (stripe_subscription_id),
    CONSTRAINT fk_sub_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_tier FOREIGN KEY (tier_id) REFERENCES subscription_tiers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
