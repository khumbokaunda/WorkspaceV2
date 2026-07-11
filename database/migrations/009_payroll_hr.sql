-- 009 Payroll and HR expansion, part one: personal records, the configurable
-- pay component catalogue, and per-person salary structures.
--
-- Sensitive and liability bearing. The system is a configurable engine, not
-- hardcoded tax logic, because it is sold to companies in different situations
-- and tax rules change. The seeded components are placeholders each company
-- confirms with their accountant against current law; they are not presented
-- as authoritative rates.
--
-- Single company per instance, no tenant_id.
--
-- Enablement. payroll is a new optional module, off by default. Access is
-- tightly gated: only payroll.view_all sees anyone's figures, and each employee
-- sees only their own. Re-runnable.

-- ---------------------------------------------------------------------------
-- Personal and HR records (self-service)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS person_details (
    person_id               INT UNSIGNED NOT NULL PRIMARY KEY,
    date_of_birth           DATE NULL,
    national_id             VARCHAR(60) NULL,
    gender                  VARCHAR(30) NULL,
    marital_status          VARCHAR(30) NULL,
    tax_id                  VARCHAR(60) NULL,
    home_address            VARCHAR(400) NULL,
    personal_phone          VARCHAR(60) NULL,
    personal_email          VARCHAR(190) NULL,
    emergency_contact_name  VARCHAR(160) NULL,
    emergency_contact_phone VARCHAR(60) NULL,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pdetails_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS person_beneficiaries (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id              INT UNSIGNED NOT NULL,
    name                   VARCHAR(160) NOT NULL,
    relationship           VARCHAR(80) NULL,
    share_percent          DECIMAL(5,2) NULL,
    contact                VARCHAR(160) NULL,
    is_payroll_beneficiary TINYINT(1) NOT NULL DEFAULT 0,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_beneficiary_person (person_id),
    CONSTRAINT fk_beneficiary_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS person_bank_accounts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id      INT UNSIGNED NOT NULL,
    bank_name      VARCHAR(120) NOT NULL,
    branch         VARCHAR(120) NULL,
    account_name   VARCHAR(160) NULL,
    account_number VARCHAR(60) NOT NULL,
    is_primary     TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bank_person (person_id),
    CONSTRAINT fk_bank_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pay components and salary structures
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS pay_components (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comp_type    ENUM('Earning','Deduction') NOT NULL,
    name         VARCHAR(120) NOT NULL,
    calc_method  ENUM('Fixed Amount','Percentage of Basic','Percentage of Gross','Banded') NOT NULL DEFAULT 'Fixed Amount',
    default_rate DECIMAL(12,4) NULL,
    bands        JSON NULL,
    is_taxable   TINYINT(1) NOT NULL DEFAULT 1,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_component_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salary_structures (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id     INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    basic_salary  DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_current    TINYINT(1) NOT NULL DEFAULT 1,
    notes         VARCHAR(500) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_structure_person (person_id),
    CONSTRAINT fk_structure_person  FOREIGN KEY (person_id)  REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_structure_creator FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salary_structure_lines (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    structure_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    amount       DECIMAL(15,2) NULL,
    rate         DECIMAL(12,4) NULL,
    KEY idx_sline_structure (structure_id),
    CONSTRAINT fk_sline_structure FOREIGN KEY (structure_id) REFERENCES salary_structures(id) ON DELETE CASCADE,
    CONSTRAINT fk_sline_component FOREIGN KEY (component_id) REFERENCES pay_components(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed placeholder components. These are common examples a company confirms
-- with their accountant and against current law; they are not authoritative
-- rates. PAYE bands are illustrative only.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO pay_components (comp_type, name, calc_method, default_rate, bands, is_taxable, sort_order) VALUES
    ('Earning',   'Housing Allowance',   'Percentage of Basic', 15.0000, NULL, 1, 10),
    ('Earning',   'Transport Allowance', 'Fixed Amount',        NULL,    NULL, 1, 20),
    ('Deduction', 'Pension Employee',    'Percentage of Basic', 5.0000,  NULL, 0, 30),
    ('Deduction', 'PAYE',                'Banded',              NULL,
        '[{"upto":100000,"rate":0},{"upto":330000,"rate":25},{"upto":3000000,"rate":30},{"upto":null,"rate":35}]',
        0, 40);

-- ---------------------------------------------------------------------------
-- Permissions, module, visibility, settings
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('payroll.view_own',  'View own payslips and maintain own personal records'),
    ('payroll.view_all',  'View payroll figures and personal records for anyone'),
    ('payroll.manage',    'Manage pay components, salary structures, loans and payroll runs'),
    ('payroll.approve',   'Approve payroll runs');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin'
  AND p.permission_key IN ('payroll.view_own','payroll.view_all','payroll.manage','payroll.approve');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager'
  AND p.permission_key IN ('payroll.view_own','payroll.view_all','payroll.manage','payroll.approve');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff'
  AND p.permission_key IN ('payroll.view_own');

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('payroll', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- Payroll is visible to every role, since each employee has their own personal
-- records and payslips; the figures for others are permission gated.
INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'payroll', 1 FROM roles r;

INSERT INTO settings (setting_key, value) VALUES
    ('hr_self_service_approval', '0'),
    ('payroll_statutory_mode',   'simple')
ON DUPLICATE KEY UPDATE value = value;
