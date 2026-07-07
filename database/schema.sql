-- Meridian baseline schema and seed.
-- MySQL 8.x / MariaDB 10.11+, utf8mb4, InnoDB, foreign keys enforced.
-- All later structural changes go in database/migrations/ as numbered files.
-- Never rewrite this baseline in place once deployed.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS audit_log, password_resets, login_attempts, notifications,
    documents, certifications, asset_assignments, assets, task_comments, tasks,
    projects, leave_balances, leave_requests, leave_types, attendance,
    module_visibility, user_permission_overrides, role_permissions, permissions,
    users, people, roles, settings;

SET foreign_key_checks = 1;

-- ---------------------------------------------------------------------------
-- Access control
-- ---------------------------------------------------------------------------

CREATE TABLE roles (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key     VARCHAR(50)  NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    is_system    TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(80)  NOT NULL UNIQUE,
    description    VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE people (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name        VARCHAR(80)  NOT NULL,
    last_name         VARCHAR(80)  NOT NULL,
    email             VARCHAR(190) NOT NULL UNIQUE,
    phone             VARCHAR(40)  NULL,
    job_title         VARCHAR(120) NULL,
    department        VARCHAR(120) NULL,
    manager_id        INT UNSIGNED NULL,
    employment_status ENUM('Active','On Leave','Terminated') NOT NULL DEFAULT 'Active',
    start_date        DATE NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_people_manager FOREIGN KEY (manager_id) REFERENCES people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username             VARCHAR(60)  NOT NULL UNIQUE,
    email                VARCHAR(190) NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    person_id            INT UNSIGNED NULL,
    role_id              INT UNSIGNED NOT NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 1,
    totp_secret          VARCHAR(64)  NULL,
    last_login_at        DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_role   FOREIGN KEY (role_id)   REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permission_overrides (
    user_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    effect        ENUM('grant','revoke') NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_upo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_upo_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE module_visibility (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope      ENUM('role','user') NOT NULL,
    scope_id   INT UNSIGNED NOT NULL,
    module_key VARCHAR(60)  NOT NULL,
    is_visible TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_mv (scope, scope_id, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Attendance and leave
-- ---------------------------------------------------------------------------

CREATE TABLE attendance (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    check_in  TIME NULL,
    check_out TIME NULL,
    mode      ENUM('On-site','Remote') NOT NULL DEFAULT 'On-site',
    state     ENUM('Present','Late','Absent') NOT NULL DEFAULT 'Present',
    note      VARCHAR(255) NULL,
    UNIQUE KEY uq_attendance (person_id, work_date),
    CONSTRAINT fk_att_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_types (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                      VARCHAR(80) NOT NULL UNIQUE,
    is_paid                   TINYINT(1)  NOT NULL DEFAULT 1,
    default_annual_allocation DECIMAL(5,1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id     INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    start_date    DATE NOT NULL,
    end_date      DATE NOT NULL,
    working_days  DECIMAL(5,1) NOT NULL,
    reason        VARCHAR(500) NULL,
    status        ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    reviewed_by   INT UNSIGNED NULL,
    reviewed_at   DATETIME NULL,
    review_note   VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lr_person (person_id, status),
    KEY idx_lr_status (status),
    CONSTRAINT fk_lr_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_lr_type   FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    CONSTRAINT fk_lr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_balances (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id     INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    year          SMALLINT UNSIGNED NOT NULL,
    allocated     DECIMAL(5,1) NOT NULL DEFAULT 0,
    used          DECIMAL(5,1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_balance (person_id, leave_type_id, year),
    CONSTRAINT fk_lb_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_lb_type   FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Projects and tasks
-- ---------------------------------------------------------------------------

CREATE TABLE projects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status      ENUM('Active','On Hold','Completed','Archived') NOT NULL DEFAULT 'Active',
    lead_id     INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proj_lead FOREIGN KEY (lead_id) REFERENCES people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   INT UNSIGNED NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT NULL,
    assignee_id  INT UNSIGNED NULL,
    created_by   INT UNSIGNED NOT NULL,
    priority     ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    status       ENUM('To Do','In Progress','Blocked','Done') NOT NULL DEFAULT 'To Do',
    due_date     DATE NULL,
    completed_at DATETIME NULL,
    position     INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tasks_status (status, position),
    KEY idx_tasks_assignee (assignee_id, status),
    CONSTRAINT fk_task_project  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_assignee FOREIGN KEY (assignee_id) REFERENCES people(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_creator  FOREIGN KEY (created_by)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id    INT UNSIGNED NOT NULL,
    author_id  INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tc_task (task_id),
    CONSTRAINT fk_tc_task   FOREIGN KEY (task_id)   REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tc_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Assets
-- ---------------------------------------------------------------------------

CREATE TABLE assets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_tag       VARCHAR(60)  NOT NULL UNIQUE,
    name            VARCHAR(160) NOT NULL,
    category        ENUM('Laptop','Desktop','Monitor','Phone','Network','Peripheral','Furniture','Other') NOT NULL DEFAULT 'Other',
    serial_number   VARCHAR(120) NULL,
    purchase_date   DATE NULL,
    warranty_expiry DATE NULL,
    status          ENUM('Available','Assigned','In Repair','Retired') NOT NULL DEFAULT 'Available',
    note            VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_assignments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id    INT UNSIGNED NOT NULL,
    person_id   INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at DATETIME NULL,
    KEY idx_aa_asset (asset_id),
    KEY idx_aa_person (person_id),
    CONSTRAINT fk_aa_asset    FOREIGN KEY (asset_id)    REFERENCES assets(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_person   FOREIGN KEY (person_id)   REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_assigner FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Certifications and documents
-- ---------------------------------------------------------------------------

CREATE TABLE certifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id     INT UNSIGNED NOT NULL,
    name          VARCHAR(160) NOT NULL,
    issuing_body  VARCHAR(160) NULL,
    code          VARCHAR(60)  NOT NULL,
    earned_on     DATE NULL,
    expires_on    DATE NULL,
    credential_id VARCHAR(120) NULL,
    verify_url    VARCHAR(255) NULL,
    status        ENUM('In Progress','Active','Expired') NOT NULL DEFAULT 'Active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cert_person (person_id),
    KEY idx_cert_code (code),
    KEY idx_cert_expiry (expires_on),
    CONSTRAINT fk_cert_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id     INT UNSIGNED NOT NULL,
    doc_type      ENUM('CV','Contract','Other') NOT NULL DEFAULT 'Other',
    version_label VARCHAR(60)  NULL,
    stored_name   VARCHAR(80)  NOT NULL UNIQUE,
    original_name VARCHAR(200) NOT NULL,
    mime          VARCHAR(120) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    uploaded_by   INT UNSIGNED NOT NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note          VARCHAR(255) NULL,
    KEY idx_doc_person (person_id),
    CONSTRAINT fk_doc_person   FOREIGN KEY (person_id)   REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Notifications, security bookkeeping, audit
-- ---------------------------------------------------------------------------

CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    role_id    INT UNSIGNED NULL,
    body       VARCHAR(500) NOT NULL,
    link       VARCHAR(255) NULL,
    module_key VARCHAR(60)  NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id, is_read),
    KEY idx_notif_role (role_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60)  NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    successful   TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_la_user (username, attempted_at),
    KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    KEY idx_pr_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(80)  NOT NULL,
    entity     VARCHAR(60)  NOT NULL,
    entity_id  INT UNSIGNED NULL,
    detail     JSON NULL,
    ip_address VARCHAR(45)  NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_time (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    value       VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------------

INSERT INTO roles (role_key, display_name, is_system) VALUES
    ('admin',   'Administrator', 1),
    ('manager', 'Manager',       1),
    ('staff',   'Staff',         1);

INSERT INTO permissions (permission_key, description) VALUES
    ('dashboard.view',            'View the dashboard'),
    ('people.view',               'View the people directory'),
    ('people.create',             'Add new people and run onboarding'),
    ('people.edit',               'Edit people records'),
    ('people.terminate',          'Terminate employment'),
    ('documents.view',            'View own documents'),
    ('documents.view_all',        'View documents for any person'),
    ('documents.upload',          'Upload documents'),
    ('attendance.record',         'Check in and check out'),
    ('attendance.view',           'View own attendance'),
    ('attendance.view_all',       'View attendance for the whole team'),
    ('attendance.correct',        'Correct attendance records'),
    ('leave.request',             'Request leave'),
    ('leave.view',                'View own leave'),
    ('leave.view_all',            'View leave for the whole team'),
    ('leave.approve',             'Approve or reject leave requests'),
    ('projects.view',             'View projects and tasks'),
    ('projects.manage',           'Create and edit projects'),
    ('tasks.create',              'Create tasks'),
    ('tasks.edit',                'Edit any task'),
    ('tasks.comment',             'Comment on tasks'),
    ('assets.view',               'View the asset register'),
    ('assets.manage',             'Create, edit, import and retire assets'),
    ('assets.assign',             'Assign and return assets'),
    ('certifications.view',       'View certifications and the skills matrix'),
    ('certifications.manage_own', 'Manage own certifications'),
    ('certifications.manage_all', 'Manage certifications for anyone'),
    ('admin.users',               'Manage user accounts and access'),
    ('admin.roles',               'Manage roles, permissions and visibility'),
    ('admin.audit',               'View the audit log'),
    ('admin.settings',            'Manage application settings');

-- Administrator gets every permission.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.role_key = 'admin';

-- Manager: everything except the admin area and termination.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager'
  AND p.permission_key NOT IN ('admin.users','admin.roles','admin.audit','admin.settings','people.terminate');

-- Staff: own-record actions plus read access to shared areas.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff'
  AND p.permission_key IN (
      'dashboard.view','people.view','documents.view','documents.upload',
      'attendance.record','attendance.view','leave.request','leave.view',
      'projects.view','tasks.create','tasks.comment',
      'assets.view','certifications.view','certifications.manage_own');

-- Default module and widget visibility per role. A per-user row overrides these.
INSERT INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, m.vis
FROM roles r
JOIN (
    SELECT 'dashboard' AS module_key, 1 AS vis UNION ALL
    SELECT 'attendance', 1 UNION ALL
    SELECT 'leave', 1 UNION ALL
    SELECT 'projects', 1 UNION ALL
    SELECT 'people', 1 UNION ALL
    SELECT 'certifications', 1 UNION ALL
    SELECT 'assets', 1 UNION ALL
    SELECT 'widget.attendance', 1 UNION ALL
    SELECT 'widget.my_tasks', 1 UNION ALL
    SELECT 'widget.my_leave', 1 UNION ALL
    SELECT 'widget.cert_expiry', 1 UNION ALL
    SELECT 'widget.approvals', 1 UNION ALL
    SELECT 'widget.org_overview', 1 UNION ALL
    SELECT 'widget.task_throughput', 1 UNION ALL
    SELECT 'widget.asset_utilization', 1
) m;

-- Staff do not see the management widgets by default.
UPDATE module_visibility mv
JOIN roles r ON r.id = mv.scope_id AND mv.scope = 'role'
SET mv.is_visible = 0
WHERE r.role_key = 'staff'
  AND mv.module_key IN ('widget.approvals','widget.org_overview','widget.task_throughput','widget.asset_utilization');

-- Admin-only modules.
INSERT INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key = 'admin', 1, 0)
FROM roles r
JOIN (
    SELECT 'admin_users' AS module_key UNION ALL
    SELECT 'admin_roles' UNION ALL
    SELECT 'admin_audit' UNION ALL
    SELECT 'admin_settings'
) m;

INSERT INTO leave_types (name, is_paid, default_annual_allocation) VALUES
    ('Annual',        1, 21),
    ('Sick',          1, 14),
    ('Maternity',     1, 90),
    ('Paternity',     1, 10),
    ('Compassionate', 1, 5),
    ('Unpaid',        0, 0);

INSERT INTO settings (setting_key, value) VALUES
    ('org_name',            'Meridian'),
    ('late_threshold',      '08:30'),
    ('timezone',            'Africa/Blantyre'),
    ('leave_year_start',    '01-01'),
    ('cert_reminder_days',  '90,30,7'),
    ('cert_notify_manager', '1'),
    ('mail_notifications',  '1'),
    ('totp_required_admin', '0');

-- Seed person and administrator account.
INSERT INTO people (first_name, last_name, email, job_title, department, employment_status, start_date)
VALUES ('System', 'Administrator', 'admin@example.com', 'Administrator', 'IT', 'Active', CURDATE());

-- Seeded admin login. Username: admin  Password: ChangeMe!12345
-- must_change_password is set, so the first login forces a new password.
INSERT INTO users (username, email, password_hash, person_id, role_id, is_active, must_change_password)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$12$iczEzkjwDrPfUyoXFs9ynOdgsEH1SJNSkZMTZRN3T9RRB6eq2tX.O',
    1,
    (SELECT id FROM roles WHERE role_key = 'admin'),
    1,
    1
);
