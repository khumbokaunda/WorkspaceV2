-- 022 Device fingerprinting and shared-device detection.
--
-- Records the device each login and check-in came from, for an admin-only view
-- that surfaces one device acting for many people (the signature of buddy
-- punching). This is a detection and audit sidecar, never a lock: it records
-- and it displays, and it never blocks a login or a check-in.
--
-- Additive only. Idempotent, and safe on a fresh install.

CREATE TABLE IF NOT EXISTS device_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    event_type  ENUM('login','check_in','check_out') NOT NULL,
    device_hash CHAR(64) NOT NULL,
    confidence  ENUM('strong','weak') NOT NULL DEFAULT 'strong',
    user_agent  VARCHAR(400) NULL,
    platform    VARCHAR(80)  NULL,
    screen      VARCHAR(40)  NULL,
    language    VARCHAR(40)  NULL,
    ip_address  VARCHAR(45)  NULL,
    latitude    DECIMAL(9,6) NULL,
    longitude   DECIMAL(9,6) NULL,
    in_range    TINYINT(1)   NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dev_hash_time (device_hash, created_at),
    KEY idx_dev_user (user_id),
    KEY idx_dev_type_time (event_type, created_at),
    CONSTRAINT fk_dev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_labels (
    device_hash CHAR(64) PRIMARY KEY,
    label       VARCHAR(120) NOT NULL,
    noted_by    INT UNSIGNED NULL,
    noted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Module and permission. Optional, off by default, admin facing only.
-- ---------------------------------------------------------------------------

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('device_audit', 0, 0)
ON DUPLICATE KEY UPDATE module_key = module_key;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('device_audit.view', 'View the device audit and shared-device detection area');

-- Granted only to administrators.
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id
FROM `groups` g JOIN permissions p ON p.permission_key = 'device_audit.view'
WHERE g.group_key = 'administrators';

-- Kept in the legacy admin role too, so the roles screen stays consistent
-- during the transition.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p ON p.permission_key = 'device_audit.view'
WHERE r.role_key = 'admin';

-- ---------------------------------------------------------------------------
-- Settings. Location capture is off by default and only ever samples at the
-- moment of check-in.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO settings (setting_key, value) VALUES
    ('attendance_capture_location', '0'),
    ('office_latitude',  ''),
    ('office_longitude', ''),
    ('office_radius_m',  '200'),
    ('device_audit_retention_days', '180');
