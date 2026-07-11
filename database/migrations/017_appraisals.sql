-- 017 Performance and appraisals.
--
-- Periodic review cycles with goals and ratings per employee, feeding
-- development and training needs. Single company per instance, no tenant_id.
--
-- Enablement. appraisals is a new optional module, off by default, visible to
-- every role so each employee sees their own reviews. Re-runnable.

CREATE TABLE IF NOT EXISTS review_cycles (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(160) NOT NULL,
    start_date DATE NULL,
    end_date   DATE NULL,
    status     ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appraisals (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_id       INT UNSIGNED NOT NULL,
    person_id      INT UNSIGNED NOT NULL,
    reviewer_id    INT UNSIGNED NULL,
    overall_rating DECIMAL(3,1) NULL,
    status         ENUM('Draft','Submitted','Acknowledged') NOT NULL DEFAULT 'Draft',
    summary        TEXT NULL,
    acknowledged_at DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appraisal (cycle_id, person_id),
    KEY idx_appraisal_person (person_id),
    CONSTRAINT fk_appraisal_cycle    FOREIGN KEY (cycle_id)    REFERENCES review_cycles(id) ON DELETE CASCADE,
    CONSTRAINT fk_appraisal_person   FOREIGN KEY (person_id)   REFERENCES people(id)        ON DELETE CASCADE,
    CONSTRAINT fk_appraisal_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appraisal_goals (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appraisal_id INT UNSIGNED NOT NULL,
    goal         VARCHAR(500) NOT NULL,
    rating       DECIMAL(3,1) NULL,
    comments     VARCHAR(1000) NULL,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_goal_appraisal (appraisal_id),
    CONSTRAINT fk_goal_appraisal FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('appraisals.view_own', 'View and acknowledge own appraisals'),
    ('appraisals.manage',   'Manage review cycles and conduct appraisals');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('appraisals.view_own','appraisals.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('appraisals.view_own','appraisals.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('appraisals.view_own');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('appraisals', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'appraisals', 1 FROM roles r;
