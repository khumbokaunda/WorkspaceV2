-- 018 Training and development.
--
-- Track courses and certifications in progress, linked to the certifications
-- module, so the company sees who is working toward what. Especially relevant
-- for a technical team that lives on certifications. Single company per
-- instance, no tenant_id.
--
-- Enablement. training is a new optional module, off by default, visible to
-- every role so each employee sees their own development. Re-runnable.

CREATE TABLE IF NOT EXISTS training_records (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id        INT UNSIGNED NOT NULL,
    course_name      VARCHAR(200) NOT NULL,
    provider         VARCHAR(160) NULL,
    status           ENUM('Planned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
    start_date       DATE NULL,
    target_date      DATE NULL,
    completed_date   DATE NULL,
    linked_cert_code VARCHAR(60) NULL,
    cost             DECIMAL(15,2) NULL,
    notes            VARCHAR(500) NULL,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_training_person (person_id),
    KEY idx_training_status (status),
    CONSTRAINT fk_training_person  FOREIGN KEY (person_id)  REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_training_creator FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('training.view_own', 'View and record own training'),
    ('training.manage',   'View and record training for anyone');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('training.view_own','training.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('training.view_own','training.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('training.view_own');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('training', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'training', 1 FROM roles r;
