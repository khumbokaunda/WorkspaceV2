-- 015 Fleet and vehicle management.
--
-- Track each vehicle, its assignment, service due dates, and insurance and
-- license expiry, all expiry-alert driven the way certifications are. Single
-- company per instance, no tenant_id.
--
-- Enablement. fleet is a new optional module, off by default, for Administrator
-- and Manager by default. Re-runnable.

CREATE TABLE IF NOT EXISTS vehicles (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration     VARCHAR(40) NOT NULL,
    make             VARCHAR(80) NULL,
    model            VARCHAR(80) NULL,
    year             SMALLINT UNSIGNED NULL,
    assigned_to      INT UNSIGNED NULL,
    status           ENUM('Active','In Service','Retired') NOT NULL DEFAULT 'Active',
    service_due      DATE NULL,
    insurance_expiry DATE NULL,
    license_expiry   DATE NULL,
    notes            VARCHAR(500) NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vehicle_status (status),
    KEY idx_vehicle_assignee (assigned_to),
    CONSTRAINT fk_vehicle_person FOREIGN KEY (assigned_to) REFERENCES people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id  INT UNSIGNED NOT NULL,
    log_type    ENUM('Service','Repair','Fuel','Incident','Other') NOT NULL DEFAULT 'Service',
    log_date    DATE NOT NULL,
    odometer    INT UNSIGNED NULL,
    cost        DECIMAL(15,2) NULL,
    description VARCHAR(500) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_vlog_vehicle (vehicle_id),
    CONSTRAINT fk_vlog_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    CONSTRAINT fk_vlog_user    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('fleet.view',   'View vehicles and service history'),
    ('fleet.manage', 'Add and edit vehicles and service logs');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key IN ('admin','manager') AND p.permission_key IN ('fleet.view','fleet.manage');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('fleet', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key IN ('admin','manager'), 1, 0)
FROM roles r
JOIN (SELECT 'fleet' AS module_key UNION ALL SELECT 'widget.fleet_expiries') m;
