-- 010 Payroll and HR expansion, part two: payroll runs, payslips and staff
-- loans.
--
-- A monthly run computes each active employee's payslip from their salary
-- structure and the component rules. HR reviews the register, a manager
-- approves, and only then are payslips visible to employees. An approved loan
-- becomes a recurring deduction on the payslip until it is cleared.
--
-- Single company per instance, no tenant_id. Re-runnable.

CREATE TABLE IF NOT EXISTS payroll_runs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period      VARCHAR(7) NOT NULL,
    label       VARCHAR(120) NULL,
    status      ENUM('Draft','Approved','Paid') NOT NULL DEFAULT 'Draft',
    created_by  INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    paid_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_run_period (period),
    CONSTRAINT fk_run_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_run_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payslips (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id           INT UNSIGNED NOT NULL,
    person_id        INT UNSIGNED NOT NULL,
    basic            DECIMAL(15,2) NOT NULL DEFAULT 0,
    gross            DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
    net_pay          DECIMAL(15,2) NOT NULL DEFAULT 0,
    breakdown        JSON NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payslip (run_id, person_id),
    KEY idx_payslip_person (person_id),
    CONSTRAINT fk_payslip_run    FOREIGN KEY (run_id)    REFERENCES payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_payslip_person FOREIGN KEY (person_id) REFERENCES people(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_loans (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id           INT UNSIGNED NOT NULL,
    principal           DECIMAL(15,2) NOT NULL DEFAULT 0,
    installment         DECIMAL(15,2) NOT NULL DEFAULT 0,
    outstanding_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    reason              VARCHAR(500) NULL,
    status              ENUM('Pending','Active','Cleared','Rejected') NOT NULL DEFAULT 'Pending',
    approved_by         INT UNSIGNED NULL,
    approved_at         DATETIME NULL,
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loan_person (person_id),
    KEY idx_loan_status (status),
    CONSTRAINT fk_loan_person   FOREIGN KEY (person_id)   REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_loan_approver FOREIGN KEY (approved_by) REFERENCES users(id)  ON DELETE SET NULL,
    CONSTRAINT fk_loan_creator  FOREIGN KEY (created_by)  REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
