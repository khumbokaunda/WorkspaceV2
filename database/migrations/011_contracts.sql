-- 011 Contracts and renewals.
--
-- Won tenders and other agreements tracked with start and end dates, value,
-- renewal or expiry alerts, and the signed contract file. Feeds dashboard
-- alerts the way certifications do. Single company per instance, no tenant_id.
--
-- Enablement. contracts is a new optional module, off by default. Default role
-- visibility gives it to Administrator and Manager. Re-runnable.

CREATE TABLE IF NOT EXISTS contracts (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                 VARCHAR(200) NOT NULL,
    counterparty          VARCHAR(200) NULL,
    contract_type         ENUM('Client','Supplier','Employment','Service','Lease','Other') NOT NULL DEFAULT 'Client',
    reference             VARCHAR(120) NULL,
    start_date            DATE NULL,
    end_date              DATE NULL,
    value                 DECIMAL(15,2) NULL,
    currency              VARCHAR(3) NULL,
    renewal_reminder_days INT UNSIGNED NOT NULL DEFAULT 60,
    status                ENUM('Draft','Active','Expired','Renewed','Terminated') NOT NULL DEFAULT 'Active',
    tender_id             INT UNSIGNED NULL,
    stored_name           VARCHAR(80)  NULL UNIQUE,
    original_name         VARCHAR(200) NULL,
    mime                  VARCHAR(120) NULL,
    size_bytes            INT UNSIGNED NULL,
    notes                 VARCHAR(1000) NULL,
    created_by            INT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contract_status (status),
    KEY idx_contract_end (end_date),
    CONSTRAINT fk_contract_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('contracts.view',   'View contracts and renewals'),
    ('contracts.manage', 'Add and edit contracts');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key IN ('admin','manager')
  AND p.permission_key IN ('contracts.view','contracts.manage');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('contracts', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key IN ('admin','manager'), 1, 0)
FROM roles r
JOIN (SELECT 'contracts' AS module_key UNION ALL SELECT 'widget.contract_renewals') m;
