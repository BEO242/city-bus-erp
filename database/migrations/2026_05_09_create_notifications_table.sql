-- Table de persistance des notifications envoyées (audit + retry)
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel     ENUM('email','sms','push','webhook') NOT NULL,
    recipient   VARCHAR(255)    NOT NULL,
    subject     VARCHAR(255)    NULL,
    body        TEXT            NOT NULL,
    status      ENUM('queued','sent','failed','retrying') NOT NULL DEFAULT 'sent',
    meta        JSON            NULL,
    error       VARCHAR(500)    NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_notif_channel_status (channel, status),
    INDEX idx_notif_recipient (recipient),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
