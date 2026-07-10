-- 006 Sales and Tenders module.
--
-- Manages the full life of a tender response, from spotting the opportunity to
-- recording the outcome. Its design principle is assembly: it links people,
-- CVs, certifications, company documents, references and manufacturer
-- authorizations that already exist elsewhere, rather than re-entering them.
--
-- Single company per instance: no tenant_id.
--
-- Enablement. tenders is a new optional module, off by default for every
-- install, turned on under Settings, Modules. Pricing is need to know, so a
-- separate tenders.pricing.view permission gates the internal cost and margin
-- view apart from tenders.view. Default role visibility gives the module to
-- Administrator and Manager and hides it from Staff. Re-runnable.

-- ---------------------------------------------------------------------------
-- Tender record and pipeline
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tenders (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_number     VARCHAR(120) NULL,
    title                VARCHAR(250) NOT NULL,
    client_id            INT UNSIGNED NULL,
    opportunity_id       INT UNSIGNED NULL,
    category             VARCHAR(120) NULL,
    description          TEXT NULL,
    source               ENUM('Portal','Newspaper','Invitation','Referral','Other') NOT NULL DEFAULT 'Portal',
    issue_date           DATE NULL,
    closing_date         DATETIME NULL,
    tender_validity_days INT UNSIGNED NULL,
    clarification_deadline DATETIME NULL,
    site_visit_at        DATETIME NULL,
    submission_method    ENUM('Portal','Physical','Email') NOT NULL DEFAULT 'Portal',
    estimated_value      DECIMAL(15,2) NULL,
    currency             VARCHAR(3) NULL,
    vat_percent          DECIMAL(6,2) NULL,
    bid_owner_id         INT UNSIGNED NULL,
    status               ENUM('Identified','Go Decision Pending','Preparing','Submitted','Under Evaluation','Won','Lost','Cancelled') NOT NULL DEFAULT 'Identified',
    award_value          DECIMAL(15,2) NULL,
    outcome_notes        TEXT NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tender_status (status),
    KEY idx_tender_client (client_id),
    KEY idx_tender_closing (closing_date),
    CONSTRAINT fk_tender_client FOREIGN KEY (client_id)      REFERENCES clients(id)       ON DELETE SET NULL,
    CONSTRAINT fk_tender_opp    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL,
    CONSTRAINT fk_tender_owner  FOREIGN KEY (bid_owner_id)   REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Go or No-Go assessment (one row per tender)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_go_assessments (
    tender_id           INT UNSIGNED NOT NULL PRIMARY KEY,
    meets_eligibility   ENUM('Yes','No','Unsure') NULL,
    has_authorizations  ENUM('Yes','No','Unsure') NULL,
    can_meet_delivery   ENUM('Yes','No','Unsure') NULL,
    value_worth_effort  ENUM('Yes','No','Unsure') NULL,
    has_experience      ENUM('Yes','No','Unsure') NULL,
    decision            ENUM('Go','No-Go','Pending') NOT NULL DEFAULT 'Pending',
    rationale           TEXT NULL,
    assessed_by         INT UNSIGNED NULL,
    assessed_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_goassess_tender FOREIGN KEY (tender_id)   REFERENCES tenders(id) ON DELETE CASCADE,
    CONSTRAINT fk_goassess_user   FOREIGN KEY (assessed_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Compliance and requirements matrix
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_requirements (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id          INT UNSIGNED NOT NULL,
    category           ENUM('Eligibility','Technical','Financial','Documentary') NOT NULL DEFAULT 'Eligibility',
    requirement_text   VARCHAR(1000) NOT NULL,
    is_mandatory       TINYINT(1) NOT NULL DEFAULT 1,
    our_compliance     ENUM('Complies','Partial','Does Not Comply','Not Yet Assessed') NOT NULL DEFAULT 'Not Yet Assessed',
    evidence_reference VARCHAR(500) NULL,
    remarks            VARCHAR(1000) NULL,
    sort_order         INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_treq_tender (tender_id),
    CONSTRAINT fk_treq_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Required documents checklist and assembly
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_documents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id   INT UNSIGNED NOT NULL,
    item_label  VARCHAR(250) NOT NULL,
    -- Either a standard form to complete, or a reference to an existing
    -- artifact pulled from the library. source_id points at the owning row for
    -- a library reference and is null for a standard form.
    source_kind ENUM('Standard Form','Company Document','Manufacturer Authorization','Person CV','Certification','Other') NOT NULL DEFAULT 'Standard Form',
    source_id   INT UNSIGNED NULL,
    is_ready    TINYINT(1) NOT NULL DEFAULT 0,
    notes       VARCHAR(500) NULL,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_tdoc_tender (tender_id),
    CONSTRAINT fk_tdoc_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Proposed team and evaluation criteria
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_team (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id     INT UNSIGNED NOT NULL,
    person_id     INT UNSIGNED NOT NULL,
    proposed_role VARCHAR(120) NOT NULL,
    notes         VARCHAR(500) NULL,
    KEY idx_tteam_tender (tender_id),
    UNIQUE KEY uq_tteam (tender_id, person_id),
    CONSTRAINT fk_tteam_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE,
    CONSTRAINT fk_tteam_person FOREIGN KEY (person_id) REFERENCES people(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tender_evaluation_criteria (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id    INT UNSIGNED NOT NULL,
    criterion    VARCHAR(500) NOT NULL,
    max_points   DECIMAL(8,2) NOT NULL DEFAULT 0,
    weight       DECIMAL(6,2) NULL,
    our_evidence VARCHAR(1000) NULL,
    self_score   DECIMAL(8,2) NULL,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_tcrit_tender (tender_id),
    CONSTRAINT fk_tcrit_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Bill of quantities and pricing
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_boq_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id      INT UNSIGNED NOT NULL,
    item_no        VARCHAR(40) NULL,
    description    VARCHAR(500) NOT NULL,
    specification  VARCHAR(1000) NULL,
    quantity       DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit           VARCHAR(40) NULL,
    unit_cost      DECIMAL(15,2) NULL,
    markup_percent DECIMAL(6,2) NULL,
    unit_price     DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_optional    TINYINT(1) NOT NULL DEFAULT 0,
    sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_tboq_tender (tender_id),
    CONSTRAINT fk_tboq_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Securities (expiry sensitive)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tender_securities (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tender_id          INT UNSIGNED NOT NULL,
    security_type      ENUM('Bid Security','Performance Security') NOT NULL DEFAULT 'Bid Security',
    amount             DECIMAL(15,2) NULL,
    currency           VARCHAR(3) NULL,
    form               ENUM('Bank Guarantee','Insurance Bond','Cash') NULL,
    issuing_institution VARCHAR(200) NULL,
    issue_date         DATE NULL,
    expiry_date        DATE NULL,
    status             ENUM('Active','Returned','Forfeited','Released') NOT NULL DEFAULT 'Active',
    reference          VARCHAR(160) NULL,
    KEY idx_tsec_tender (tender_id),
    KEY idx_tsec_expiry (expiry_date),
    CONSTRAINT fk_tsec_tender FOREIGN KEY (tender_id) REFERENCES tenders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('tenders.view',         'View tenders and the bid pipeline'),
    ('tenders.manage',       'Create and edit tenders and their bid content'),
    ('tenders.pricing.view', 'See internal cost and margin on the bill of quantities');

-- Administrator and Manager get all three; Staff get none by default, since a
-- tender holds commercial and pricing data. Grant per user with an override.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key IN ('admin','manager')
  AND p.permission_key IN ('tenders.view','tenders.manage','tenders.pricing.view');

-- ---------------------------------------------------------------------------
-- Module registration and default visibility
-- ---------------------------------------------------------------------------

INSERT INTO modules (module_key, is_enabled, is_core) VALUES ('tenders', 0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key IN ('admin','manager'), 1, 0)
FROM roles r
JOIN (
    SELECT 'tenders' AS module_key UNION ALL
    SELECT 'widget.tender_deadlines'
) m;
