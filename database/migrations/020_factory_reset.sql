-- 020 Factory reset support.
--
-- Adds the durable reset audit table, which survives the wipe it records, and
-- the restricted system.reset permission that gates the reset area. The
-- permission is granted only to the protected Administrators group and is
-- marked restricted so it is never offered in the ordinary permission editors.
--
-- Additive only. Idempotent, and safe on a fresh install.

-- ---------------------------------------------------------------------------
-- The reset audit table. Never truncated by a reset (it is on the preserve
-- list), so the history of resets outlives every reset.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reset_log (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    initiated_user_id  INT UNSIGNED NULL,           -- id at the time, may no longer resolve after wipe
    initiated_username VARCHAR(255) NOT NULL,       -- text snapshot, survives the user wipe
    initiated_email    VARCHAR(255) NULL,
    reason_category    VARCHAR(64) NOT NULL,
    reason_text        TEXT NOT NULL,
    archive_filename   VARCHAR(255) NULL,
    archive_checksum   CHAR(64) NULL,
    archive_size_bytes BIGINT UNSIGNED NULL,
    app_version        VARCHAR(32) NULL,
    ip_address         VARCHAR(45) NULL,
    scope              VARCHAR(32) NOT NULL DEFAULT 'full',
    success            TINYINT(1) NOT NULL DEFAULT 0,
    notes              TEXT NULL,
    reset_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Approvals for the optional two-person rule. A pending reset request that a
-- second administrator must approve before execution. Also on the preserve
-- list so an in-flight request is not lost, though a completed reset makes any
-- pending request moot.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reset_approvals (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requested_by      INT UNSIGNED NOT NULL,
    reason_category   VARCHAR(64) NOT NULL,
    reason_text       TEXT NOT NULL,
    scope             VARCHAR(32) NOT NULL DEFAULT 'full',
    status            ENUM('pending','approved','used','cancelled') NOT NULL DEFAULT 'pending',
    approved_by       INT UNSIGNED NULL,
    approved_at       DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reset_appr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- The restricted permission that gates the reset area. system.reset is granted
-- only to the Administrators group and is never surfaced in the permission
-- editors (see restricted_permissions() in the resolver).
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('system.reset', 'Perform a guarded factory reset of the instance');

INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id
FROM `groups` g JOIN permissions p ON p.permission_key = 'system.reset'
WHERE g.group_key = 'administrators';

-- Navigation for the reset area and its history. Core admin modules, always
-- enabled, gated by the restricted system.reset permission.
INSERT INTO modules (module_key, is_enabled, is_core) VALUES
    ('admin_reset', 1, 1)
ON DUPLICATE KEY UPDATE is_core = 1, is_enabled = 1;
