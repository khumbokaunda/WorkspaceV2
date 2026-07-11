-- 014 Internal helpdesk.
--
-- A lightweight ticket system for staff to report IT or facilities issues, with
-- assignment and status. Single company per instance, no tenant_id.
--
-- Enablement. helpdesk is a new optional module, off by default, visible to
-- every role so staff can raise tickets. Re-runnable.

CREATE TABLE IF NOT EXISTS tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject     VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category    ENUM('IT','Facilities','HR','Finance','Other') NOT NULL DEFAULT 'IT',
    priority    ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
    status      ENUM('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
    raised_by   INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_status (status),
    KEY idx_ticket_raiser (raised_by),
    KEY idx_ticket_assignee (assigned_to),
    CONSTRAINT fk_ticket_raiser   FOREIGN KEY (raised_by)   REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    body       VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tcomment_ticket (ticket_id),
    CONSTRAINT fk_tcomment_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_tcomment_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('helpdesk.raise',  'Raise and comment on own helpdesk tickets'),
    ('helpdesk.manage', 'View, assign and resolve all helpdesk tickets');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin' AND p.permission_key IN ('helpdesk.raise','helpdesk.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager' AND p.permission_key IN ('helpdesk.raise','helpdesk.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff' AND p.permission_key IN ('helpdesk.raise');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('helpdesk', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(m.module_key = 'helpdesk', 1, IF(r.role_key IN ('admin','manager'), 1, 0))
FROM roles r
JOIN (SELECT 'helpdesk' AS module_key UNION ALL SELECT 'widget.helpdesk_queue') m;
