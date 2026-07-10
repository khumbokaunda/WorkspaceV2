-- 007 Procurement and office administration, with optional budgets.
--
-- Models the flow: staff request items, the office administrator consolidates
-- them into a requisition, an approver authorises it and releases funds, a
-- purchase order is raised to a supplier, and goods are received against it.
-- Trackable items received can be pushed into the existing Assets module,
-- closing the loop from request to tracked asset.
--
-- Single company per instance, no tenant_id.
--
-- Enablement. procurement is a new optional module, off by default. budgets is
-- a separate optional module behind its own switch, so companies that do not
-- want budgeting are not burdened. Default role visibility gives procurement
-- to every role (staff raise requests) and budgets to Administrator and
-- Manager. Re-runnable.

-- ---------------------------------------------------------------------------
-- Requisitions
-- ---------------------------------------------------------------------------

-- Requisitions first so an item request can reference one inline, keeping the
-- migration re-runnable without a follow-up ALTER.
CREATE TABLE IF NOT EXISTS requisitions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference      VARCHAR(60) NULL,
    purpose        VARCHAR(250) NOT NULL,
    period         VARCHAR(60) NULL,
    department     VARCHAR(120) NULL,
    total_estimate DECIMAL(15,2) NOT NULL DEFAULT 0,
    status         ENUM('Draft','Submitted','Approved','Rejected') NOT NULL DEFAULT 'Draft',
    created_by     INT UNSIGNED NULL,
    approved_by    INT UNSIGNED NULL,
    approved_at    DATETIME NULL,
    approval_note  VARCHAR(500) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_req_status (status),
    CONSTRAINT fk_req_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_req_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requested_by        INT UNSIGNED NULL,
    item_description    VARCHAR(500) NOT NULL,
    quantity            DECIMAL(12,2) NOT NULL DEFAULT 1,
    estimated_unit_cost DECIMAL(15,2) NULL,
    justification       VARCHAR(1000) NULL,
    urgency             ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
    needed_by           DATE NULL,
    department          VARCHAR(120) NULL,
    status              ENUM('Submitted','Consolidated','Approved','Rejected','Fulfilled') NOT NULL DEFAULT 'Submitted',
    requisition_id      INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ireq_by (requested_by),
    KEY idx_ireq_status (status),
    KEY idx_ireq_req (requisition_id),
    CONSTRAINT fk_ireq_user        FOREIGN KEY (requested_by)   REFERENCES users(id)        ON DELETE SET NULL,
    CONSTRAINT fk_ireq_requisition FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fund_releases (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT UNSIGNED NOT NULL,
    amount         DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(80) NULL,
    account        VARCHAR(120) NULL,
    authorised_by  INT UNSIGNED NULL,
    released_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note           VARCHAR(500) NULL,
    KEY idx_fund_req (requisition_id),
    CONSTRAINT fk_fund_req  FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_fund_user FOREIGN KEY (authorised_by)  REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Purchase orders and receipt
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS purchase_orders (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number         VARCHAR(60) NULL,
    requisition_id    INT UNSIGNED NULL,
    supplier_id       INT UNSIGNED NULL,
    status            ENUM('Issued','Partially Received','Received','Cancelled') NOT NULL DEFAULT 'Issued',
    expected_delivery DATE NULL,
    total             DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes             VARCHAR(500) NULL,
    created_by        INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_po_status (status),
    KEY idx_po_supplier (supplier_id),
    CONSTRAINT fk_po_req      FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE SET NULL,
    CONSTRAINT fk_po_creator  FOREIGN KEY (created_by)     REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id               INT UNSIGNED NOT NULL,
    item_request_id     INT UNSIGNED NULL,
    description         VARCHAR(500) NOT NULL,
    quantity            DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_trackable_asset  TINYINT(1) NOT NULL DEFAULT 0,
    asset_category      VARCHAR(40) NULL,
    KEY idx_poi_po (po_id),
    CONSTRAINT fk_poi_po      FOREIGN KEY (po_id)           REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_request FOREIGN KEY (item_request_id) REFERENCES item_requests(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id         INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    received_by   INT UNSIGNED NULL,
    note          VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_grn_po (po_id),
    CONSTRAINT fk_grn_po   FOREIGN KEY (po_id)       REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_grn_user FOREIGN KEY (received_by) REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_id        INT UNSIGNED NOT NULL,
    po_item_id        INT UNSIGNED NOT NULL,
    quantity_received DECIMAL(12,2) NOT NULL DEFAULT 0,
    asset_id          INT UNSIGNED NULL,
    KEY idx_grni_receipt (receipt_id),
    KEY idx_grni_item (po_item_id),
    CONSTRAINT fk_grni_receipt FOREIGN KEY (receipt_id) REFERENCES goods_receipts(id)     ON DELETE CASCADE,
    CONSTRAINT fk_grni_poitem  FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_grni_asset   FOREIGN KEY (asset_id)   REFERENCES assets(id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Budgets (optional, own enablement)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS budgets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(120) NOT NULL,
    period     VARCHAR(60) NOT NULL,
    amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes      VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_budget (department, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO permissions (permission_key, description) VALUES
    ('procurement.view',    'View procurement requests, requisitions and purchase orders'),
    ('procurement.request', 'Raise item requests'),
    ('procurement.manage',  'Consolidate requisitions, raise purchase orders and receive goods'),
    ('procurement.approve', 'Approve requisitions and release funds'),
    ('budgets.view',        'View department budgets'),
    ('budgets.manage',      'Create and edit department budgets');

-- Administrator: everything. Manager: view, manage, approve and budgets. Staff:
-- view and raise requests only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'admin'
  AND p.permission_key IN ('procurement.view','procurement.request','procurement.manage','procurement.approve','budgets.view','budgets.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'manager'
  AND p.permission_key IN ('procurement.view','procurement.request','procurement.manage','procurement.approve','budgets.view','budgets.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.role_key = 'staff'
  AND p.permission_key IN ('procurement.view','procurement.request');

-- ---------------------------------------------------------------------------
-- Module registration and default visibility
-- ---------------------------------------------------------------------------

INSERT INTO modules (module_key, is_enabled, is_core) VALUES
    ('procurement', 0, 0),
    ('budgets',     0, 0)
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- Procurement is visible to every role (staff raise requests); budgets and the
-- approvals widget are for Administrator and Manager.
INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, 'procurement', 1 FROM roles r;

INSERT IGNORE INTO module_visibility (scope, scope_id, module_key, is_visible)
SELECT 'role', r.id, m.module_key, IF(r.role_key IN ('admin','manager'), 1, 0)
FROM roles r
JOIN (
    SELECT 'budgets' AS module_key UNION ALL
    SELECT 'widget.procurement_approvals'
) m;
