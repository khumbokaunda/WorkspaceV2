-- 012 Timesheets and billable hours.
--
-- For project based technical work, staff log hours against projects and
-- tasks, supporting utilisation reporting and, where relevant, client billing.
-- Ties into the existing Projects and Tasks. Single company per instance, no
-- tenant_id.
--
-- Enablement. timesheets is a new optional module, off by default, visible to
-- every role since staff log their own hours. Re-runnable.

CREATE TABLE IF NOT EXISTS timesheet_entries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id   INT UNSIGNED NOT NULL,
    project_id  INT UNSIGNED NULL,
    task_id     INT UNSIGNED NULL,
    work_date   DATE NOT NULL,
    hours       DECIMAL(5,2) NOT NULL DEFAULT 0,
    is_billable TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ts_person (person_id, work_date),
    KEY idx_ts_project (project_id),
    CONSTRAINT fk_ts_person  FOREIGN KEY (person_id)  REFERENCES people(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ts_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_ts_task    FOREIGN KEY (task_id)    REFERENCES tasks(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('timesheets.log',      'Log own timesheet hours'),
    ('timesheets.view_all', 'View timesheets and utilisation for everyone');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('timesheets.log','timesheets.view_all');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('timesheets.log','timesheets.view_all');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('timesheets.log');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('timesheets', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'timesheets', 1 FROM roles r;
