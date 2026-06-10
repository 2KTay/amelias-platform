-- Notification queue (email now, SMS gated) + send-once log. The unique key on
-- (entity_type, entity_id, template, channel) makes reminders/confirmations
-- send EXACTLY once even under cron overlap or webhook retry (C-V7-9).

CREATE TABLE notification_queue (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel      ENUM('email','sms') NOT NULL,
    category     ENUM('transactional','marketing') NOT NULL DEFAULT 'transactional',
    recipient    VARCHAR(255) NOT NULL,
    template     VARCHAR(120) NOT NULL,
    payload_json LONGTEXT NULL,
    status       ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
    attempts     INT NOT NULL DEFAULT 0,
    last_error   VARCHAR(500) NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_nq_status (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Send-once idempotency ledger.
CREATE TABLE notification_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type  VARCHAR(60) NOT NULL,                    -- e.g. 'order','booking'
    entity_id    VARCHAR(64) NOT NULL,
    template     VARCHAR(120) NOT NULL,
    channel      ENUM('email','sms') NOT NULL,
    sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notif_once (entity_type, entity_id, template, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
