-- 019 Departments, access groups, and group-based access control.
--
-- Replaces the coarse role split with departments that carry default
-- permissions and reusable access groups for cross-cutting access. Departments
-- and access groups are the same underlying thing, a group that grants
-- permissions and module visibility; they differ by a type flag and by the
-- rule that a user has exactly one primary department but may hold many access
-- groups. Access resolves additively (the union of every group a user belongs
-- to), then per-person overrides apply with an explicit revoke winning.
--
-- Single company per instance, no tenant_id.
--
-- Migration safety. This must leave every current user with at least the access
-- they had before. Each department is seeded with the baseline (staff) role
-- permissions, so a staff member who only belongs to a department keeps their
-- access; managers and administrators additionally join a Managers or the
-- protected Administrators access group carrying their full role permissions,
-- so their resolved access is a superset of what it was. Per-person overrides
-- are preserved unchanged. The roles table and users.role_id are kept during
-- the transition (role_id becomes nullable) and retired in a later migration
-- only after resolution is verified. Idempotent, and safe on a fresh install.

-- ---------------------------------------------------------------------------
-- Schema
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `groups` (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_key      VARCHAR(64) NOT NULL UNIQUE,
    name           VARCHAR(128) NOT NULL,
    type           ENUM('department','access_group') NOT NULL,
    parent_id      INT UNSIGNED NULL,
    head_person_id INT UNSIGNED NULL,
    description    VARCHAR(255) NULL,
    is_system      TINYINT(1) NOT NULL DEFAULT 0,
    sort_order     INT NOT NULL DEFAULT 0,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_group_parent FOREIGN KEY (parent_id)      REFERENCES `groups`(id) ON DELETE SET NULL,
    CONSTRAINT fk_group_head   FOREIGN KEY (head_person_id) REFERENCES people(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_permissions (
    group_id      INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, permission_id),
    CONSTRAINT fk_gp_group FOREIGN KEY (group_id)      REFERENCES `groups`(id)    ON DELETE CASCADE,
    CONSTRAINT fk_gp_perm  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_module_visibility (
    group_id   INT UNSIGNED NOT NULL,
    module_key VARCHAR(64) NOT NULL,
    is_visible TINYINT(1) NOT NULL,
    PRIMARY KEY (group_id, module_key),
    CONSTRAINT fk_gmv_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_groups (
    user_id    INT UNSIGNED NOT NULL,
    group_id   INT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, group_id),
    KEY idx_ug_group (group_id),
    CONSTRAINT fk_ug_user  FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_ug_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employment type is a descriptive attribute of a person, not part of access.
ALTER TABLE people
    ADD COLUMN IF NOT EXISTS employment_type ENUM('Full-time','Part-time','Contract','Intern','Consultant')
        NOT NULL DEFAULT 'Full-time' AFTER job_title;

-- Access now comes from groups, so a user need not carry a role. Kept during
-- the transition and made nullable.
ALTER TABLE users MODIFY role_id INT UNSIGNED NULL;

-- ---------------------------------------------------------------------------
-- The system administration permission that the Administrators group grants.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('system.admin', 'Full system administration, short-circuits every permission check');

-- ---------------------------------------------------------------------------
-- Built-in access groups
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `groups` (group_key, name, type, is_system, sort_order) VALUES
    ('administrators', 'Administrators', 'access_group', 1, 1),
    ('managers',       'Managers',       'access_group', 0, 2);

-- Administrators: every permission the admin role held, plus system.admin.
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, rp.permission_id
FROM `groups` g JOIN roles r ON r.role_key = 'admin' JOIN role_permissions rp ON rp.role_id = r.id
WHERE g.group_key = 'administrators';
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id FROM `groups` g JOIN permissions p ON p.permission_key = 'system.admin'
WHERE g.group_key = 'administrators';

-- Managers: every permission the manager role held.
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, rp.permission_id
FROM `groups` g JOIN roles r ON r.role_key = 'manager' JOIN role_permissions rp ON rp.role_id = r.id
WHERE g.group_key = 'managers';

-- Any non-standard custom roles become their own access group, so their members
-- keep exactly what they had.
INSERT IGNORE INTO `groups` (group_key, name, type, is_system, sort_order)
SELECT CONCAT('role_', r.role_key), r.display_name, 'access_group', 0, 50
FROM roles r WHERE r.role_key NOT IN ('admin', 'manager', 'staff');
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, rp.permission_id
FROM `groups` g JOIN roles r ON g.group_key = CONCAT('role_', r.role_key) JOIN role_permissions rp ON rp.role_id = r.id
WHERE r.role_key NOT IN ('admin', 'manager', 'staff');

-- ---------------------------------------------------------------------------
-- Departments from the existing free-text people.department values, plus a
-- General department so no one is left without a home.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO `groups` (group_key, name, type, is_system, sort_order) VALUES
    ('general', 'General', 'department', 0, 100);

INSERT IGNORE INTO `groups` (group_key, name, type, is_system, sort_order)
SELECT DISTINCT LOWER(REPLACE(REPLACE(REPLACE(TRIM(department), ' ', '_'), '.', ''), '/', '_')) AS group_key,
       TRIM(department) AS name, 'department', 0, 10
FROM people
WHERE department IS NOT NULL AND TRIM(department) <> '';

-- Each department is seeded with the baseline (staff) role permissions, so a
-- member who only belongs to a department keeps staff-level access.
INSERT IGNORE INTO group_permissions (group_id, permission_id)
SELECT g.id, rp.permission_id
FROM `groups` g JOIN roles r ON r.role_key = 'staff' JOIN role_permissions rp ON rp.role_id = r.id
WHERE g.type = 'department';

-- ---------------------------------------------------------------------------
-- Membership
-- ---------------------------------------------------------------------------

-- Primary department: the group matching the person's old department string.
INSERT IGNORE INTO user_groups (user_id, group_id, is_primary)
SELECT u.id, g.id, 1
FROM users u
JOIN people pe ON pe.id = u.person_id AND pe.department IS NOT NULL AND TRIM(pe.department) <> ''
JOIN `groups` g ON g.type = 'department'
   AND g.group_key = LOWER(REPLACE(REPLACE(REPLACE(TRIM(pe.department), ' ', '_'), '.', ''), '/', '_'));

-- Anyone without a department (no person, or blank) goes to General.
INSERT IGNORE INTO user_groups (user_id, group_id, is_primary)
SELECT u.id, g.id, 1
FROM users u JOIN `groups` g ON g.group_key = 'general' AND g.type = 'department'
WHERE NOT EXISTS (SELECT 1 FROM user_groups ug WHERE ug.user_id = u.id AND ug.is_primary = 1);

-- Access groups by old role: admins join Administrators, managers join Managers,
-- and any custom role joins its own group.
INSERT IGNORE INTO user_groups (user_id, group_id, is_primary)
SELECT u.id, g.id, 0
FROM users u JOIN roles r ON r.id = u.role_id AND r.role_key = 'admin'
JOIN `groups` g ON g.group_key = 'administrators';

INSERT IGNORE INTO user_groups (user_id, group_id, is_primary)
SELECT u.id, g.id, 0
FROM users u JOIN roles r ON r.id = u.role_id AND r.role_key = 'manager'
JOIN `groups` g ON g.group_key = 'managers';

INSERT IGNORE INTO user_groups (user_id, group_id, is_primary)
SELECT u.id, g.id, 0
FROM users u JOIN roles r ON r.id = u.role_id AND r.role_key NOT IN ('admin', 'manager', 'staff')
JOIN `groups` g ON g.group_key = CONCAT('role_', r.role_key);

-- ---------------------------------------------------------------------------
-- Navigation for managing departments and access groups. Core admin modules,
-- always enabled, gated by the admin.roles permission like the roles screen
-- they sit beside.
-- ---------------------------------------------------------------------------

INSERT INTO modules (module_key, is_enabled, is_core) VALUES
    ('admin_departments',   1, 1),
    ('admin_access_groups', 1, 1)
ON DUPLICATE KEY UPDATE is_core = 1, is_enabled = 1;
