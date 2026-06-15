-- =============================================================
-- V4.K — Plateforme transverse : queue, cache, feature flags, logs
-- =============================================================

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(40) NOT NULL DEFAULT 'default',
    job_class       VARCHAR(180) NOT NULL,
    payload         LONGTEXT NOT NULL,
    status          ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    available_at    DATETIME NOT NULL,
    started_at      TIMESTAMP NULL,
    finished_at     TIMESTAMP NULL,
    error           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jobs_queue (queue, status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs_failed (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_class       VARCHAR(180),
    payload         LONGTEXT,
    error           TEXT,
    failed_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_class (job_class, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_cache (
    cache_key       VARCHAR(180) NOT NULL PRIMARY KEY,
    value           LONGTEXT NOT NULL,
    expires_at      TIMESTAMP NULL,
    INDEX idx_cache_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feature_flags (
    flag_key        VARCHAR(60) NOT NULL PRIMARY KEY,
    enabled         TINYINT(1) NOT NULL DEFAULT 0,
    rollout_pct     TINYINT UNSIGNED NOT NULL DEFAULT 100,
    target_roles   JSON NULL,
    target_users    JSON NULL,
    description     TEXT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS structured_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    level           ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
    channel         VARCHAR(60) NOT NULL DEFAULT 'app',
    message         VARCHAR(255) NOT NULL,
    context         JSON NULL,
    actor_id        BIGINT UNSIGNED NULL,
    request_id      VARCHAR(40) NULL,
    occurred_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_level_time (level, occurred_at),
    INDEX idx_log_channel (channel, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  ('platform.jobs.view',    'platform', 'jobs_view',    'Voir la file de travaux', 900),
  ('platform.jobs.manage',  'platform', 'jobs_manage',  'Relancer / supprimer des travaux', 901),
  ('platform.flags.manage', 'platform', 'flags_manage', 'Gérer les feature flags', 902),
  ('platform.logs.view',    'platform', 'logs_view',    'Consulter les logs structurés', 903)
ON DUPLICATE KEY UPDATE slug = slug;
