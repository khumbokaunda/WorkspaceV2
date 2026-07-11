-- 013 Company noticeboard and policies.
--
-- Internal announcements and a read-only library of company policies and
-- procedures that all staff can see, separate from the private document vaults.
-- Single company per instance, no tenant_id.
--
-- Enablement. noticeboard is a new optional module, off by default, visible to
-- every role so all staff see announcements and policies. Re-runnable.

CREATE TABLE IF NOT EXISTS announcements (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    body         TEXT NULL,
    is_pinned    TINYINT(1) NOT NULL DEFAULT 0,
    published_by INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ann_pinned (is_pinned, created_at),
    CONSTRAINT fk_ann_user FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policies (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(200) NOT NULL,
    category       VARCHAR(120) NULL,
    version_label  VARCHAR(60) NULL,
    effective_date DATE NULL,
    stored_name    VARCHAR(80)  NOT NULL UNIQUE,
    original_name  VARCHAR(200) NOT NULL,
    mime           VARCHAR(120) NOT NULL,
    size_bytes     INT UNSIGNED NOT NULL,
    notes          VARCHAR(500) NULL,
    uploaded_by    INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_policy_category (category),
    CONSTRAINT fk_policy_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('noticeboard.view',   'View announcements and company policies'),
    ('noticeboard.manage', 'Post announcements and manage the policy library');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('noticeboard.view','noticeboard.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('noticeboard.view','noticeboard.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('noticeboard.view');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('noticeboard', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'noticeboard', 1 FROM roles r;
