-- 003 Instance-level module enablement and first-run setup flag.
--
-- Adds a modules table that records, per install, which optional modules the
-- company has turned on. This sits above the existing per-role and per-user
-- visibility: a disabled module does not exist for anyone. Core modules are
-- always enabled and cannot be switched off.
--
-- Upgrade safety: an install that is already in use (more than the single
-- seeded admin) keeps every current module enabled and is marked setup
-- complete, so this migration never hides working modules or forces an
-- existing deployment back through the wizard. A pristine install leaves the
-- optional modules off for the setup wizard to enable. Re-runnable.

CREATE TABLE IF NOT EXISTS modules (
    module_key VARCHAR(60) NOT NULL PRIMARY KEY,
    is_enabled TINYINT(1)  NOT NULL DEFAULT 0,
    is_core    TINYINT(1)  NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Is this an install that has already been used? The baseline seeds exactly
-- one user (admin), so more than one means real use.
SET @existing := (SELECT COUNT(*) FROM users) > 1;

-- Core modules: always enabled, cannot be disabled.
INSERT INTO modules (module_key, is_enabled, is_core) VALUES
    ('dashboard', 1, 1),
    ('people', 1, 1),
    ('admin_users', 1, 1),
    ('admin_roles', 1, 1),
    ('admin_audit', 1, 1),
    ('admin_settings', 1, 1)
ON DUPLICATE KEY UPDATE is_core = 1, is_enabled = 1;

-- Optional modules: on for an existing install, off for a fresh one. On a
-- re-run the existing value is kept.
INSERT INTO modules (module_key, is_enabled, is_core)
SELECT k, @existing, 0 FROM (
    SELECT 'attendance' AS k UNION ALL
    SELECT 'leave' UNION ALL
    SELECT 'projects' UNION ALL
    SELECT 'assets' UNION ALL
    SELECT 'certifications'
) t
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- Setup flag. An existing install is already configured.
INSERT INTO settings (setting_key, value)
VALUES ('setup_completed', IF(@existing, '1', '0'))
ON DUPLICATE KEY UPDATE value = value;
