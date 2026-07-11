-- 008 Expenses and petty cash.
--
-- An employee submits an expense claim with lines and attached receipts; it
-- moves through approval and, once approved, is marked reimbursed. Petty cash
-- is a float with issues and replenishments and a running balance, for the
-- small day-to-day office spending an administrator handles.
--
-- Single company per instance, no tenant_id.
--
-- Enablement. expenses is a new optional module, off by default. Default role
-- visibility gives it to every role, since staff submit their own claims;
-- viewing everyone, approving and petty cash are permission gated. Re-runnable.

-- ---------------------------------------------------------------------------
-- Expense claims
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS expense_claims (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claimant_id   INT UNSIGNED NULL,
    title         VARCHAR(200) NOT NULL,
    status        ENUM('Draft','Submitted','Approved','Rejected','Reimbursed') NOT NULL DEFAULT 'Draft',
    total         DECIMAL(15,2) NOT NULL DEFAULT 0,
    submitted_at  DATETIME NULL,
    approved_by   INT UNSIGNED NULL,
    approved_at   DATETIME NULL,
    approval_note VARCHAR(500) NULL,
    reimbursed_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_exp_claimant (claimant_id),
    KEY idx_exp_status (status),
    CONSTRAINT fk_exp_claimant FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_exp_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_lines (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_id             INT UNSIGNED NOT NULL,
    expense_date         DATE NULL,
    category             VARCHAR(120) NULL,
    description          VARCHAR(500) NOT NULL,
    amount               DECIMAL(15,2) NOT NULL DEFAULT 0,
    receipt_stored_name  VARCHAR(80)  NULL UNIQUE,
    receipt_original_name VARCHAR(200) NULL,
    receipt_mime         VARCHAR(120) NULL,
    receipt_size_bytes   INT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_expline_claim (claim_id),
    CONSTRAINT fk_expline_claim FOREIGN KEY (claim_id) REFERENCES expense_claims(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Petty cash ledger
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS petty_cash (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_type      ENUM('Replenishment','Disbursement') NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    entry_date      DATE NOT NULL,
    purpose         VARCHAR(300) NULL,
    item_request_id INT UNSIGNED NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_petty_date (entry_date),
    CONSTRAINT fk_petty_request FOREIGN KEY (item_request_id) REFERENCES item_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_petty_user    FOREIGN KEY (created_by)      REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('expenses.submit',    'Submit and manage own expense claims'),
    ('expenses.view_all',  'View expense claims for anyone'),
    ('expenses.approve',   'Approve, reject and reimburse expense claims'),
    ('pettycash.manage',   'Record petty cash issues and replenishments');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin'
  AND p.permission_key IN ('expenses.submit','expenses.view_all','expenses.approve','pettycash.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager'
  AND p.permission_key IN ('expenses.submit','expenses.view_all','expenses.approve','pettycash.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff'
  AND p.permission_key IN ('expenses.submit');

-- ---------------------------------------------------------------------------
-- Module registration and default visibility
-- ---------------------------------------------------------------------------

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('expenses', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- Expenses is visible to every role (staff submit their own claims).
INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'expenses', 1 FROM roles r;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'widget.expense_approvals', IF(r.role_key IN ('admin','manager'), 1, 0) FROM roles r;
