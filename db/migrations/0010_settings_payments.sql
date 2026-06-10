-- Self-service settings (encrypted secrets), the payments ledger (webhook-fed,
-- a real table not a view, so reconciliation is stable), and webhook idempotency.

CREATE TABLE settings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key  VARCHAR(160) NOT NULL,
    value_text   LONGTEXT NULL,                           -- AES-GCM ciphertext when is_secret=1
    is_secret    TINYINT(1) NOT NULL DEFAULT 0,
    updated_by   BIGINT UNSIGNED NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key),
    CONSTRAINT fk_setting_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments ledger, fed by the Stripe webhook (source of truth for reconciliation).
CREATE TABLE payments (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     BIGINT UNSIGNED NULL,
    stripe_payment_intent VARCHAR(64) NULL,
    stripe_charge_id VARCHAR(64) NULL,
    type         ENUM('payment','refund','dispute') NOT NULL DEFAULT 'payment',
    amount_cents BIGINT NOT NULL DEFAULT 0,               -- signed: +payment, -refund
    currency     CHAR(3) NOT NULL DEFAULT 'USD',
    status       VARCHAR(40) NOT NULL,
    payout_id    VARCHAR(64) NULL,                        -- for payout reconciliation
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pay_order (order_id),
    KEY idx_pay_intent (stripe_payment_intent),
    KEY idx_pay_payout (payout_id),
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook idempotency: process each Stripe event exactly once.
CREATE TABLE webhook_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id     VARCHAR(64) NOT NULL,                    -- Stripe evt_...
    type         VARCHAR(80) NOT NULL,
    status       ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
    payload      LONGTEXT NULL,
    error_message VARCHAR(500) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_event (event_id),
    KEY idx_webhook_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
