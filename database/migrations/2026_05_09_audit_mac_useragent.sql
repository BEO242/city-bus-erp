-- Journal d'audit : ajout colonnes adresse MAC et User-Agent
-- Idempotent (IF NOT EXISTS, MariaDB 10.5+)

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS mac_address VARCHAR(17)  NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS user_agent  VARCHAR(512) NULL AFTER mac_address;
