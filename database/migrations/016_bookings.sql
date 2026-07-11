-- 016 Meeting room and resource booking.
--
-- A simple calendar for shared rooms and equipment, to stop double booking.
-- Single company per instance, no tenant_id.
--
-- Enablement. bookings is a new optional module, off by default, visible to
-- every role so staff can book shared resources. Re-runnable.

CREATE TABLE IF NOT EXISTS resources (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(160) NOT NULL,
    resource_type ENUM('Room','Equipment','Vehicle','Other') NOT NULL DEFAULT 'Room',
    location      VARCHAR(160) NULL,
    capacity      SMALLINT UNSIGNED NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    notes         VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_id INT UNSIGNED NOT NULL,
    person_id   INT UNSIGNED NULL,
    booked_by   INT UNSIGNED NULL,
    title       VARCHAR(200) NOT NULL,
    start_at    DATETIME NOT NULL,
    end_at      DATETIME NOT NULL,
    notes       VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_booking_resource (resource_id, start_at),
    KEY idx_booking_range (start_at, end_at),
    CONSTRAINT fk_booking_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_person   FOREIGN KEY (person_id)   REFERENCES people(id)    ON DELETE SET NULL,
    CONSTRAINT fk_booking_user     FOREIGN KEY (booked_by)   REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('bookings.book',   'View the calendar and book shared resources'),
    ('bookings.manage', 'Manage resources and any booking');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('bookings.book','bookings.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('bookings.book','bookings.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('bookings.book');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('bookings', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'bookings', 1 FROM roles r;
