-- QR feedback + service-recovery alerts. The Google Review link is shown to
-- EVERY respondent (no review-gating); low scores additionally raise a staff
-- alert for service recovery.

CREATE TABLE feedback (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rating       TINYINT NOT NULL,                        -- 1..5
    comment      TEXT NULL,
    table_code   VARCHAR(40) NULL,                        -- from QR ?table= (escaped on render)
    customer_id  BIGINT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_rating (rating),
    KEY idx_feedback_created (created_at),
    CONSTRAINT fk_feedback_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback_alerts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    feedback_id  BIGINT UNSIGNED NOT NULL,
    status       ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
    assigned_to  BIGINT UNSIGNED NULL,
    resolved_at  DATETIME NULL,
    resolution_note VARCHAR(500) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alert_status (status),
    CONSTRAINT fk_alert_feedback FOREIGN KEY (feedback_id) REFERENCES feedback (id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
