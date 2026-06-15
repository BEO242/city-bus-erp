-- =====================================================================
-- 2026-05-02 — Module Administration & Durcissement Sécurité
-- =====================================================================
-- Roles + Permissions DB (RBAC granulaire)
-- App Settings (clé-valeur typée)
-- Sécurité : login_attempts, password_history, 2FA, user_sessions
-- Extension table users (lockout, 2FA, password_changed_at)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop FK users.role_id si existe (sera reposée plus bas)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_users_role');
SET @sql := IF(@fk_exists > 0, 'ALTER TABLE users DROP FOREIGN KEY fk_users_role', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop tables Spatie/Laravel résiduelles si présentes (pour repartir propre)
DROP TABLE IF EXISTS role_has_permissions;
DROP TABLE IF EXISTS model_has_roles;
DROP TABLE IF EXISTS model_has_permissions;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

-- ─── ROLES ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(50)  NOT NULL UNIQUE,
  label        VARCHAR(120) NOT NULL,
  description  VARCHAR(255) NULL,
  is_system    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 100,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PERMISSIONS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permissions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(80)  NOT NULL UNIQUE,
  module       VARCHAR(40)  NOT NULL,
  action       VARCHAR(40)  NOT NULL,
  label        VARCHAR(180) NOT NULL,
  description  VARCHAR(255) NULL,
  sort_order   INT          NOT NULL DEFAULT 100,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_permissions_module (module)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

-- Overrides per user (grant=1 ajoute, grant=0 retire)
CREATE TABLE IF NOT EXISTS user_permissions (
  user_id        BIGINT UNSIGNED NOT NULL,
  permission_id  BIGINT UNSIGNED NOT NULL,
  granted        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
  CONSTRAINT fk_up_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ─── SETTINGS ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key  VARCHAR(100) NOT NULL PRIMARY KEY,
  category     VARCHAR(40)  NOT NULL,
  setting_type ENUM('string','int','bool','json','secret','text') NOT NULL DEFAULT 'string',
  setting_value TEXT NULL,
  label        VARCHAR(180) NOT NULL,
  description  VARCHAR(255) NULL,
  is_secret    TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order   INT          NOT NULL DEFAULT 100,
  updated_by   BIGINT UNSIGNED NULL,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_settings_cat (category, sort_order)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SÉCURITÉ ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(120) NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  user_agent   VARCHAR(255) NULL,
  success      TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_email_time (email, attempted_at),
  INDEX idx_attempts_ip_time    (ip_address, attempted_at)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_history (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pwh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pwh_user_time (user_id, created_at)
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS two_factor_secrets (
  user_id        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  secret         VARCHAR(64)  NOT NULL,
  recovery_codes JSON NULL,
  enabled        TINYINT(1)   NOT NULL DEFAULT 0,
  confirmed_at   TIMESTAMP NULL,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
  id            VARCHAR(128) NOT NULL PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  fingerprint   VARCHAR(64) NULL,
  last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_us_user (user_id, last_activity)
) ENGINE=InnoDB CHARSET=utf8mb4;

-- ─── EXTENSION users ─────────────────────────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS role_id              BIGINT UNSIGNED NULL AFTER role,
  ADD COLUMN IF NOT EXISTS password_changed_at  TIMESTAMP NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS password_expires_at  TIMESTAMP NULL AFTER password_changed_at,
  ADD COLUMN IF NOT EXISTS failed_login_count   INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS locked_until         TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS two_factor_enabled   TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_verified_at    TIMESTAMP NULL;

-- Index idempotents
SET @i1 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_role_id');
SET @sql := IF(@i1 = 0, 'ALTER TABLE users ADD INDEX idx_users_role_id (role_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @i2 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_locked');
SET @sql := IF(@i2 = 0, 'ALTER TABLE users ADD INDEX idx_users_locked (locked_until)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK users.role_id → roles.id (idempotent)
SET @fk_now := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_users_role');
SET @sql := IF(@fk_now = 0, 'ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── SEED ROLES ──────────────────────────────────────────────────────
INSERT INTO roles (slug, label, description, is_system, sort_order) VALUES
  ('admin',        'Administrateur-Gérant',          'Accès total système',                  1, 1),
  ('raf',          'Resp. Administratif et Financier','Pilotage finance, paie, audit',         0, 10),
  ('exploitation', 'Resp. Exploitation',             'Voyages, lignes, flotte',               0, 20),
  ('chef_agence',  'Chef d''agence',                 'Caisse, billetterie, RH agence',        0, 30),
  ('caissier',     'Caissier Bus',                   'Vente billets, ouverture/fermeture',    0, 40),
  ('controleur',   'Contrôleur',                     'Validation embarquement',               0, 50),
  ('mecanicien',   'Chef mécanicien',                'Maintenance flotte',                    0, 60),
  ('chauffeur',    'Chauffeur',                      'Lecture voyages assignés',              0, 70)
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description);

-- ─── SEED PERMISSIONS (catalogue exhaustif granulaire) ───────────────
INSERT INTO permissions (slug, module, action, label, sort_order) VALUES
  -- Administration
  ('admin.users.view',        'admin', 'view',   'Voir utilisateurs',          10),
  ('admin.users.create',      'admin', 'create', 'Créer utilisateurs',         11),
  ('admin.users.edit',        'admin', 'edit',   'Modifier utilisateurs',      12),
  ('admin.users.delete',      'admin', 'delete', 'Supprimer utilisateurs',     13),
  ('admin.users.reset_pwd',   'admin', 'reset',  'Réinitialiser mots de passe',14),
  ('admin.users.unlock',      'admin', 'unlock', 'Déverrouiller comptes',      15),
  ('admin.users.impersonate', 'admin', 'impersonate','Se connecter en tant que',16),
  ('admin.roles.view',        'admin', 'view',   'Voir rôles',                 20),
  ('admin.roles.manage',      'admin', 'manage', 'Gérer rôles & permissions', 21),
  ('admin.settings.view',     'admin', 'view',   'Voir paramètres',            30),
  ('admin.settings.edit',     'admin', 'edit',   'Modifier paramètres',        31),
  ('admin.audit.view',        'admin', 'view',   'Consulter journaux audit',   40),
  ('admin.audit.export',      'admin', 'export', 'Exporter journaux',          41),
  ('admin.maintenance.toggle','admin', 'toggle', 'Activer mode maintenance',   50),
  -- Référentiel
  ('referentiel.view',           'referentiel', 'view',   'Voir référentiel',           100),
  ('referentiel.create',         'referentiel', 'create', 'Créer entrées référentiel', 101),
  ('referentiel.edit',           'referentiel', 'edit',   'Modifier référentiel',       102),
  ('referentiel.delete',         'referentiel', 'delete', 'Supprimer référentiel',      103),
  ('referentiel.tariffs.manage', 'referentiel', 'tariffs','Gérer grille tarifaire',     104),
  -- Voyages
  ('voyages.view',     'voyages', 'view',   'Voir voyages',          200),
  ('voyages.create',   'voyages', 'create', 'Créer voyages',         201),
  ('voyages.edit',     'voyages', 'edit',   'Modifier voyages',      202),
  ('voyages.close',    'voyages', 'close',  'Clôturer voyages',      203),
  ('voyages.cancel',   'voyages', 'cancel', 'Annuler voyages',       204),
  -- Billetterie
  ('billetterie.view',     'billetterie', 'view',     'Voir billets',         300),
  ('billetterie.create',   'billetterie', 'create',   'Vendre billets',       301),
  ('billetterie.cancel',   'billetterie', 'cancel',   'Annuler billets',      302),
  ('billetterie.reprint',  'billetterie', 'reprint',  'Réimprimer billets',   303),
  ('billetterie.preprint', 'billetterie', 'preprint', 'Pré-impression',       304),
  ('billetterie.refund',   'billetterie', 'refund',   'Rembourser billets',   305),
  ('billetterie.bagage',   'billetterie', 'bagage',   'Gérer billets bagage', 306),
  -- Contrôle
  ('controle.view',     'controle', 'view',     'Accès contrôle',     400),
  ('controle.validate', 'controle', 'validate', 'Valider embarquement',401),
  -- Caisse
  ('caisse.view',     'caisse', 'view',     'Voir caisses',     500),
  ('caisse.open',     'caisse', 'open',     'Ouvrir caisse',    501),
  ('caisse.close',    'caisse', 'close',    'Fermer caisse',    502),
  ('caisse.validate', 'caisse', 'validate', 'Valider clôtures', 503),
  ('caisse.reverse',  'caisse', 'reverse',  'Annuler clôtures', 504),
  -- Flotte
  ('flotte.view',                'flotte', 'view',     'Voir flotte',                600),
  ('flotte.maintenance.create',  'flotte', 'maint+',   'Créer ordres maintenance',   601),
  ('flotte.maintenance.edit',    'flotte', 'maint~',   'Modifier ordres maintenance',602),
  ('flotte.maintenance.close',   'flotte', 'maint-',   'Clôturer ordres maintenance',603),
  ('flotte.fuel.log',            'flotte', 'fuel',     'Saisir carburant',           604),
  ('flotte.fuel.validate',       'flotte', 'fuelv',    'Valider carburant',          605),
  -- RH
  ('rh.view',          'rh', 'view',     'Voir RH',            700),
  ('rh.create',        'rh', 'create',   'Créer employés',     701),
  ('rh.edit',          'rh', 'edit',     'Modifier employés',  702),
  ('rh.delete',        'rh', 'delete',   'Supprimer employés', 703),
  ('rh.payroll',       'rh', 'payroll',  'Gérer paie',         704),
  ('rh.payroll.validate','rh','payrollv','Valider paie',       705),
  -- Reporting
  ('reporting.view',   'reporting', 'view',   'Voir rapports',    800),
  ('reporting.export', 'reporting', 'export', 'Exporter rapports',801),
  ('reporting.financial','reporting','fin',  'Rapports financiers',802)
ON DUPLICATE KEY UPDATE label=VALUES(label), module=VALUES(module), action=VALUES(action);

-- ─── SEED role_permissions ───────────────────────────────────────────
-- admin = toutes les permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug='admin';

-- raf
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='raf' AND p.slug IN (
  'admin.users.view','admin.audit.view',
  'reporting.view','reporting.export','reporting.financial',
  'caisse.view','caisse.validate','caisse.reverse',
  'rh.view','rh.payroll','rh.payroll.validate',
  'flotte.view'
);

-- exploitation
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='exploitation' AND p.slug IN (
  'referentiel.view','referentiel.create','referentiel.edit','referentiel.tariffs.manage',
  'voyages.view','voyages.create','voyages.edit','voyages.close','voyages.cancel',
  'flotte.view','flotte.maintenance.create','flotte.maintenance.edit','flotte.maintenance.close',
  'flotte.fuel.log','flotte.fuel.validate',
  'reporting.view'
);

-- chef_agence
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='chef_agence' AND p.slug IN (
  'voyages.view',
  'billetterie.view','billetterie.create','billetterie.cancel','billetterie.reprint','billetterie.preprint','billetterie.refund','billetterie.bagage',
  'caisse.view','caisse.open','caisse.close','caisse.validate',
  'rh.view',
  'reporting.view'
);

-- caissier
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='caissier' AND p.slug IN (
  'voyages.view',
  'billetterie.view','billetterie.create','billetterie.bagage',
  'caisse.view','caisse.open','caisse.close'
);

-- controleur
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='controleur' AND p.slug IN ('controle.view','controle.validate');

-- mecanicien
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='mecanicien' AND p.slug IN (
  'flotte.view','flotte.maintenance.create','flotte.maintenance.edit','flotte.maintenance.close'
);

-- chauffeur
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug='chauffeur' AND p.slug='voyages.view';

-- ─── Lier les users existants à role_id ──────────────────────────────
UPDATE users u INNER JOIN roles r ON r.slug = u.role
   SET u.role_id = r.id
 WHERE u.role_id IS NULL;

-- ─── SEED app_settings ───────────────────────────────────────────────
INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, description, sort_order) VALUES
  -- Identité société
  ('company.name',          'company','string','City Bus',                  'Nom de la société',          'Affiché dans l''entête, billets, rapports', 10),
  ('company.legal_name',    'company','string','City Bus SARL',             'Raison sociale',             '', 11),
  ('company.address',       'company','text',  'Brazzaville, Congo',         'Adresse complète',           '', 12),
  ('company.phone',         'company','string','+242 06 000 00 00',          'Téléphone',                  '', 13),
  ('company.email',         'company','string','contact@citybus.cg',         'E-mail',                     '', 14),
  ('company.niu',           'company','string','',                            'NIU',                        'Numéro d''Identification Unique', 15),
  ('company.rccm',          'company','string','',                            'RCCM',                       'Registre Commerce', 16),
  ('company.logo_path',     'company','string','',                            'Logo (chemin)',              '', 17),
  -- Sécurité
  ('security.session_lifetime',   'security','int',  '120', 'Durée session (minutes)',                  'Délai d''inactivité avant déconnexion automatique', 100),
  ('security.password_min_length','security','int',  '12',  'Longueur minimale mot de passe',           'Recommandé : 12+', 101),
  ('security.password_require_mix','security','bool','1',   'Mixité requise',                            'Majuscule, minuscule, chiffre, symbole', 102),
  ('security.password_history',   'security','int',  '5',   'Historique mots de passe',                  'Nombre de mots de passe précédents interdits', 103),
  ('security.password_max_age',   'security','int',  '90',  'Durée de vie mot de passe (jours)',         '0 = jamais', 104),
  ('security.login_max_attempts', 'security','int',  '5',   'Tentatives login max',                      'Avant verrouillage temporaire', 105),
  ('security.login_lockout_minutes','security','int','15',  'Durée verrouillage (minutes)',              '', 106),
  ('security.rate_limit_per_minute','security','int','10',  'Rate limit /minute /IP sur /login',         '', 107),
  ('security.two_factor_required','security','bool','0',   '2FA obligatoire pour tous',                 '', 108),
  ('security.two_factor_required_admin','security','bool','1','2FA obligatoire pour admin',           '', 109),
  -- Billetterie
  ('billetterie.ticket_prefix',     'billetterie','string','CB',  'Préfixe numéro billet',         '', 200),
  ('billetterie.ticket_expiration_h','billetterie','int',  '24',  'Expiration billets non utilisés (h)','', 201),
  ('billetterie.qr_secret_rotation','billetterie','int',  '0',   'Rotation secret QR (jours)',     '0 = jamais', 202),
  -- Caisse
  ('caisse.currency',     'caisse','string','FCFA','Devise',      '', 300),
  ('caisse.rounding',     'caisse','int',  '5',   'Arrondi (multiple)','Arrondit les montants au multiple choisi (FCFA)', 301),
  ('caisse.alert_threshold','caisse','int','100000','Seuil alerte caisse (FCFA)','', 302),
  -- Notifications
  ('mail.smtp_host',     'mail','string','',     'SMTP host',     '', 400),
  ('mail.smtp_port',     'mail','int',   '587',  'SMTP port',     '', 401),
  ('mail.smtp_user',     'mail','string','',     'SMTP user',     '', 402),
  ('mail.smtp_password', 'mail','secret','',     'SMTP password', '', 403),
  ('mail.smtp_encryption','mail','string','tls','Chiffrement SMTP','tls/ssl/none', 404),
  ('mail.from_address',  'mail','string','noreply@citybus.cg','Expéditeur','', 405),
  ('mail.from_name',     'mail','string','City Bus ERP','Nom expéditeur','', 406),
  -- Backups
  ('backup.enabled',     'backup','bool','0',   'Backups automatiques',     '', 500),
  ('backup.schedule',    'backup','string','daily','Fréquence',              'daily/weekly/monthly', 501),
  ('backup.retention_days','backup','int','30',  'Rétention (jours)',        '', 502),
  ('backup.path',        'backup','string','storage/backups','Dossier sauvegardes','', 503),
  -- Maintenance
  ('maintenance.enabled', 'maintenance','bool','0',     'Mode maintenance',        'Bloque tous les accès sauf admin', 600),
  ('maintenance.message', 'maintenance','text','Maintenance en cours, retour bientôt.','Message public','', 601),
  ('maintenance.allowed_ips','maintenance','text','127.0.0.1','IPs autorisées','Une IP par ligne',     602),
  -- Audit
  ('audit.retention_days','audit','int','365', 'Rétention audit (jours)',     '', 700),
  ('audit.level',         'audit','string','info','Niveau journalisation',     'debug/info/warning/error', 701)
ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description), category=VALUES(category);

SET FOREIGN_KEY_CHECKS = 1;
