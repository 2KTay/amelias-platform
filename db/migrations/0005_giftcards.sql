-- Gift cards (real money owed): an append-only ledger is the source of truth;
-- current_balance is a cached value reconciled to the ledger. Redemption uses a
-- guarded atomic UPDATE ... WHERE current_balance >= :amt (C-V7-1).

CREATE TABLE gift_cards (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(32) NOT NULL,                 -- emailed redemption code
    initial_cents   BIGINT NOT NULL DEFAULT 0,
    current_balance BIGINT NOT NULL DEFAULT 0,            -- cached; reconciled to ledger
    status          ENUM('active','depleted','void') NOT NULL DEFAULT 'active',
    purchaser_email VARCHAR(255) NULL,
    recipient_email VARCHAR(255) NULL,
    source          ENUM('native','square_import') NOT NULL DEFAULT 'native',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gc_code (code),
    KEY idx_gc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only ledger: purchases (+), redemptions (-), refund credits (+).
CREATE TABLE gift_card_transactions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gift_card_id    BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NULL,
    type            ENUM('issue','redeem','refund_credit','adjust','void') NOT NULL,
    amount_cents    BIGINT NOT NULL,                      -- signed: +issue/refund, -redeem
    balance_after   BIGINT NOT NULL,
    note            VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gct_card (gift_card_id),
    KEY idx_gct_order (order_id),
    CONSTRAINT fk_gct_card FOREIGN KEY (gift_card_id) REFERENCES gift_cards (id) ON DELETE CASCADE,
    CONSTRAINT fk_gct_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-product allowed ship-to states for DTC wine (gate stays closed until
-- Q#1/#2 confirm legality per state) — C-DATA-3.
CREATE TABLE product_shippable_states (
    product_id  BIGINT UNSIGNED NOT NULL,
    state       CHAR(2) NOT NULL,
    PRIMARY KEY (product_id, state),
    CONSTRAINT fk_pss_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
