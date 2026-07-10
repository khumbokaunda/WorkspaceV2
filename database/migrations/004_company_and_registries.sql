-- 004 Company profile, compliance document library, and the client and
-- supplier registries.
--
-- This is the foundation the tender module later assembles from: the company
-- responds to tenders by reusing official documents (registration, tax
-- compliance, permits, audited accounts, insurance, references) and by naming
-- the procuring entity (a client) and any manufacturer authorizations (held
-- against a supplier). Storing these once, with expiry tracking, means a
-- tender is built by selecting from a library rather than hunting for files.
--
-- Single company per instance: no tenant_id, no cross-company path.
--
-- Enablement. Three new optional modules are registered off by default, even
-- on an install that has already been through setup, because they are new and
-- empty here and an administrator turns them on under Settings, Modules when
-- ready. Default role visibility gives them to Administrator and Manager and
-- hides them from Staff, since they hold commercial data. Re-runnable.

-- ---------------------------------------------------------------------------
-- Company profile (single row) and its compliance library
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS company_profile (
    id             TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    legal_name     VARCHAR(200) NULL,
    trading_name   VARCHAR(200) NULL,
    reg_number     VARCHAR(120) NULL,
    tax_id         VARCHAR(120) NULL,
    phys_address   VARCHAR(400) NULL,
    postal_address VARCHAR(400) NULL,
    phone          VARCHAR(60)  NULL,
    email          VARCHAR(190) NULL,
    logo_path      VARCHAR(255) NULL,
    overview       TEXT NULL,
    mission        TEXT NULL,
    updated_by     INT UNSIGNED NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_profile_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the single row from the values setup captured into settings, keeping an
-- existing row untouched on a re-run.
INSERT INTO company_profile (id, legal_name, trading_name, reg_number, tax_id, phys_address, postal_address, phone, email, logo_path)
VALUES (
    1,
    (SELECT value FROM settings WHERE setting_key = 'legal_name'),
    (SELECT value FROM settings WHERE setting_key = 'trading_name'),
    (SELECT value FROM settings WHERE setting_key = 'reg_number'),
    (SELECT value FROM settings WHERE setting_key = 'tax_id'),
    (SELECT value FROM settings WHERE setting_key = 'phys_address'),
    (SELECT value FROM settings WHERE setting_key = 'postal_address'),
    (SELECT value FROM settings WHERE setting_key = 'company_phone'),
    (SELECT value FROM settings WHERE setting_key = 'company_email'),
    (SELECT value FROM settings WHERE setting_key = 'logo_path')
)
ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS company_documents (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_type          ENUM('Registration Certificate','Tax Compliance','Business Permit','Audited Accounts','Insurance','Company Profile','Reference Letter','Other') NOT NULL DEFAULT 'Other',
    title             VARCHAR(200) NOT NULL,
    stored_name       VARCHAR(80)  NOT NULL UNIQUE,
    original_name     VARCHAR(200) NOT NULL,
    mime              VARCHAR(120) NOT NULL,
    size_bytes        INT UNSIGNED NOT NULL,
    issue_date        DATE NULL,
    expiry_date       DATE NULL,
    issuing_authority VARCHAR(200) NULL,
    reference_number  VARCHAR(120) NULL,
    notes             VARCHAR(500) NULL,
    uploaded_by       INT UNSIGNED NULL,
    uploaded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_compdoc_type (doc_type),
    KEY idx_compdoc_expiry (expiry_date),
    CONSTRAINT fk_compdoc_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Past performance records for the experience criteria in tenders. The
-- completion certificate is an optional gated file stored like every other.
CREATE TABLE IF NOT EXISTS company_references (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_name        VARCHAR(200) NOT NULL,
    project_title      VARCHAR(250) NOT NULL,
    contract_value     DECIMAL(15,2) NULL,
    currency           VARCHAR(3) NULL,
    start_date         DATE NULL,
    end_date           DATE NULL,
    scope_summary      TEXT NULL,
    ref_contact_name   VARCHAR(160) NULL,
    ref_contact_detail VARCHAR(200) NULL,
    cert_stored_name   VARCHAR(80)  NULL UNIQUE,
    cert_original_name VARCHAR(200) NULL,
    cert_mime          VARCHAR(120) NULL,
    cert_size_bytes    INT UNSIGNED NULL,
    created_by         INT UNSIGNED NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_compref_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Clients, contacts and the opportunity pipeline
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS clients (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    client_type  ENUM('Government','Parastatal','Private','NGO') NOT NULL DEFAULT 'Private',
    sector       VARCHAR(120) NULL,
    address      VARCHAR(400) NULL,
    main_contact VARCHAR(160) NULL,
    phone        VARCHAR(60)  NULL,
    email        VARCHAR(190) NULL,
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client_type (client_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contacts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id  INT UNSIGNED NOT NULL,
    name       VARCHAR(160) NOT NULL,
    title      VARCHAR(120) NULL,
    phone      VARCHAR(60)  NULL,
    email      VARCHAR(190) NULL,
    notes      VARCHAR(400) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ccontact_client (client_id),
    CONSTRAINT fk_ccontact_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunities (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id              INT UNSIGNED NULL,
    title                  VARCHAR(250) NOT NULL,
    estimated_value        DECIMAL(15,2) NULL,
    currency               VARCHAR(3) NULL,
    source                 VARCHAR(120) NULL,
    stage                  ENUM('Lead','Qualifying','Bidding','Won','Lost') NOT NULL DEFAULT 'Lead',
    expected_decision_date DATE NULL,
    owner_id               INT UNSIGNED NULL,
    notes                  TEXT NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_opp_client (client_id),
    KEY idx_opp_stage (stage),
    CONSTRAINT fk_opp_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_opp_owner  FOREIGN KEY (owner_id)  REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Suppliers, manufacturers and their authorizations
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS suppliers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    category        ENUM('Distributor','Manufacturer','Service Provider','Contractor') NOT NULL DEFAULT 'Distributor',
    product_lines   VARCHAR(400) NULL,
    contact_name    VARCHAR(160) NULL,
    phone           VARCHAR(60)  NULL,
    email           VARCHAR(190) NULL,
    lead_time_notes VARCHAR(400) NULL,
    rating          TINYINT UNSIGNED NULL,
    notes           TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_supplier_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manufacturer_authorizations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id       INT UNSIGNED NOT NULL,
    product_line      VARCHAR(200) NOT NULL,
    authorization_ref VARCHAR(160) NULL,
    issue_date        DATE NULL,
    expiry_date       DATE NULL,
    stored_name       VARCHAR(80)  NULL UNIQUE,
    original_name     VARCHAR(200) NULL,
    mime              VARCHAR(120) NULL,
    size_bytes        INT UNSIGNED NULL,
    status            ENUM('Active','Expired','Revoked') NOT NULL DEFAULT 'Active',
    notes             VARCHAR(500) NULL,
    uploaded_by       INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mauth_supplier (supplier_id),
    KEY idx_mauth_expiry (expiry_date),
    CONSTRAINT fk_mauth_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    CONSTRAINT fk_mauth_user     FOREIGN KEY (uploaded_by) REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('company_docs.view',   'View the company profile and compliance library'),
    ('company_docs.manage', 'Manage compliance documents and company references'),
    ('clients.view',        'View clients and the opportunity pipeline'),
    ('clients.manage',      'Add and edit clients, contacts and opportunities'),
    ('suppliers.view',      'View suppliers and manufacturer authorizations'),
    ('suppliers.manage',    'Add and edit suppliers and manufacturer authorizations');

-- Administrator and Manager get view and manage; Staff get nothing by default
-- (these registries carry commercial data). Grant per user with an override.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key IN ('admin','manager')
  AND p.permission_key IN (
      'company_docs.view','company_docs.manage',
      'clients.view','clients.manage',
      'suppliers.view','suppliers.manage');

-- ---------------------------------------------------------------------------
-- Module registration and default visibility
-- ---------------------------------------------------------------------------

-- New optional modules, off by default for every install. An administrator
-- turns them on under Settings, Modules.
INSERT INTO modules (module_key, is_enabled, is_core) VALUES
    ('company_docs', 0, 0),
    ('clients',      0, 0),
    ('suppliers',    0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- Default role visibility: shown to Administrator and Manager, hidden from
-- Staff. Instance enablement still gates everything above this.
INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key IN ('admin','manager'), 1, 0)
FROM roles r
JOIN (
    SELECT 'company_docs' AS module_key UNION ALL
    SELECT 'clients' UNION ALL
    SELECT 'suppliers' UNION ALL
    SELECT 'widget.compliance_expiry'
) m;
