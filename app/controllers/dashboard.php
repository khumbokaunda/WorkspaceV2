<?php
// Role-aware dashboard. Which widgets render is decided by visible_modules()
// (module_visibility rows plus permissions), so the surface is customizable
// per role and per person from the admin area.

declare(strict_types=1);

function home(): void
{
    redirect('/dashboard');
}

function index(): void
{
    $user = current_user();
    $personId = $user['person_id'] !== null ? (int)$user['person_id'] : null;
    $today = date('Y-m-d');
    $widgets = [];

    if (module_visible('widget.attendance') && module_enabled('attendance') && $personId) {
        $widgets['attendance'] = db_row(
            'SELECT * FROM attendance WHERE person_id = ? AND work_date = ?',
            [$personId, $today]
        );
    }

    if (module_visible('widget.my_tasks') && module_enabled('projects') && $personId) {
        $widgets['my_tasks'] = db_all(
            "SELECT t.id, t.title, t.priority, t.status, t.due_date, p.name AS project_name
             FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
             WHERE t.assignee_id = ? AND t.status <> 'Done'
             ORDER BY t.due_date IS NULL, t.due_date ASC LIMIT 8",
            [$personId]
        );
    }

    if (module_visible('widget.my_leave') && module_enabled('leave') && $personId) {
        $widgets['my_leave'] = db_all(
            "SELECT lr.*, lt.name AS type_name FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.person_id = ? AND (lr.status = 'Pending' OR lr.end_date >= CURDATE())
             ORDER BY lr.start_date ASC LIMIT 5",
            [$personId]
        );
    }

    if (module_visible('widget.cert_expiry') && module_enabled('certifications') && $personId) {
        $widgets['cert_expiry'] = db_all(
            "SELECT id, name, code, expires_on, DATEDIFF(expires_on, CURDATE()) AS days_left
             FROM certifications
             WHERE person_id = ? AND expires_on IS NOT NULL
               AND expires_on <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
             ORDER BY expires_on ASC LIMIT 6",
            [$personId]
        );
    }

    if (module_visible('widget.compliance_expiry') && module_enabled('company_docs')) {
        // Compliance documents and manufacturer authorizations lapsing soon or
        // already expired. A lapsed tax compliance certificate can disqualify a
        // bid, so these surface here the way certifications do. The two sources
        // are merged and sorted by the soonest expiry.
        $docs = db_all(
            "SELECT title AS label, 'Document' AS kind, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) AS days_left
             FROM company_documents
             WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
        );
        $auths = db_all(
            "SELECT CONCAT(s.name, ' - ', ma.product_line) AS label, 'Authorization' AS kind, ma.expiry_date,
                    DATEDIFF(ma.expiry_date, CURDATE()) AS days_left
             FROM manufacturer_authorizations ma
             JOIN suppliers s ON s.id = ma.supplier_id
             WHERE ma.status <> 'Revoked' AND ma.expiry_date IS NOT NULL
               AND ma.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
        );
        $items = array_merge($docs, $auths);
        usort($items, fn($a, $b) => (int)$a['days_left'] <=> (int)$b['days_left']);
        $widgets['compliance_expiry'] = array_slice($items, 0, 6);
    }

    if (module_visible('widget.tender_deadlines') && module_enabled('tenders')) {
        // Open tenders closing soon and bid securities lapsing soon. Missing a
        // closing date loses the bid, and a lapsed bid security ties up money
        // or invalidates a submission, so both surface here.
        $closing = db_all(
            "SELECT title AS label, 'Closing' AS kind, closing_date AS due,
                    DATEDIFF(closing_date, CURDATE()) AS days_left
             FROM tenders
             WHERE status NOT IN ('Won','Lost','Cancelled') AND closing_date IS NOT NULL
               AND closing_date <= DATE_ADD(NOW(), INTERVAL 14 DAY)"
        );
        $secs = db_all(
            "SELECT CONCAT(security_type, ' - ', COALESCE(reference,'')) AS label, 'Security' AS kind, expiry_date AS due,
                    DATEDIFF(expiry_date, CURDATE()) AS days_left
             FROM tender_securities
             WHERE status = 'Active' AND expiry_date IS NOT NULL
               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        );
        $items = array_merge($closing, $secs);
        usort($items, fn($a, $b) => (int)$a['days_left'] <=> (int)$b['days_left']);
        $widgets['tender_deadlines'] = array_slice($items, 0, 6);
    }

    if (module_visible('widget.procurement_approvals') && module_enabled('procurement')) {
        $widgets['procurement_approvals'] = db_all(
            "SELECT rq.id, rq.reference, rq.purpose, rq.total_estimate, rq.department
             FROM requisitions rq WHERE rq.status = 'Submitted' ORDER BY rq.created_at LIMIT 6"
        );
    }

    if (module_visible('widget.expense_approvals') && module_enabled('expenses')) {
        $widgets['expense_approvals'] = db_all(
            "SELECT ec.id, ec.title, ec.total,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS claimant
             FROM expense_claims ec LEFT JOIN users u ON u.id = ec.claimant_id LEFT JOIN people p ON p.id = u.person_id
             WHERE ec.status = 'Submitted' ORDER BY ec.submitted_at LIMIT 6"
        );
    }

    if (module_visible('widget.contract_renewals') && module_enabled('contracts')) {
        $widgets['contract_renewals'] = db_all(
            "SELECT id, title, counterparty, end_date, DATEDIFF(end_date, CURDATE()) AS days_left
             FROM contracts
             WHERE status = 'Active' AND end_date IS NOT NULL
               AND end_date <= DATE_ADD(CURDATE(), INTERVAL renewal_reminder_days DAY)
             ORDER BY end_date LIMIT 6"
        );
    }

    if (module_visible('widget.helpdesk_queue') && module_enabled('helpdesk')) {
        $widgets['helpdesk_queue'] = db_all(
            "SELECT id, subject, priority, status FROM tickets WHERE status <> 'Closed'
             ORDER BY FIELD(priority,'Urgent','High','Normal','Low'), created_at LIMIT 6"
        );
    }

    if (module_visible('widget.approvals') && module_enabled('leave')) {
        $widgets['approvals'] = db_all(
            "SELECT lr.id, lr.start_date, lr.end_date, lr.working_days, lt.name AS type_name,
                    p.first_name, p.last_name
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN people p ON p.id = lr.person_id
             WHERE lr.status = 'Pending'
             ORDER BY lr.created_at ASC LIMIT 8"
        );
    }

    if (module_visible('widget.org_overview')) {
        // Each figure is computed only when its module is enabled, so a
        // disabled module's tables are never queried and its tile is omitted.
        $overview = [
            'headcount' => (int)db_val("SELECT COUNT(*) FROM people WHERE employment_status = 'Active'"),
        ];
        if (module_enabled('attendance')) {
            $overview['present'] = (int)db_val(
                "SELECT COUNT(*) FROM attendance WHERE work_date = ? AND check_in IS NOT NULL", [$today]
            );
        }
        if (module_enabled('leave')) {
            $overview['on_leave'] = (int)db_val(
                "SELECT COUNT(DISTINCT person_id) FROM leave_requests
                 WHERE status = 'Approved' AND start_date <= ? AND end_date >= ?", [$today, $today]
            );
        }
        if (module_enabled('certifications')) {
            $overview['cert_expiring'] = (int)db_val(
                "SELECT COUNT(*) FROM certifications c
                 JOIN people pe ON pe.id = c.person_id AND pe.employment_status = 'Active'
                 WHERE c.expires_on IS NOT NULL AND c.expires_on <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                   AND c.expires_on >= CURDATE()"
            );
        }
        if (module_enabled('assets')) {
            $overview['warranty_expiring'] = (int)db_val(
                "SELECT COUNT(*) FROM assets
                 WHERE warranty_expiry IS NOT NULL AND status <> 'Retired'
                   AND warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND warranty_expiry >= CURDATE()"
            );
        }
        if (module_enabled('company_docs')) {
            $overview['compliance_expiring'] = (int)db_val(
                "SELECT COUNT(*) FROM company_documents
                 WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)"
            );
        }
        if (module_enabled('tenders')) {
            $overview['open_tenders'] = (int)db_val(
                "SELECT COUNT(*) FROM tenders WHERE status NOT IN ('Won','Lost','Cancelled')"
            );
        }
        $widgets['org_overview'] = $overview;
    }

    if (module_visible('widget.task_throughput') && module_enabled('projects')) {
        $counts = ['To Do' => 0, 'In Progress' => 0, 'Blocked' => 0, 'Done' => 0];
        foreach (db_all('SELECT status, COUNT(*) AS c FROM tasks GROUP BY status') as $row) {
            $counts[$row['status']] = (int)$row['c'];
        }
        $widgets['task_throughput'] = $counts;
    }

    if (module_visible('widget.asset_utilization') && module_enabled('assets')) {
        $counts = ['Available' => 0, 'Assigned' => 0, 'In Repair' => 0, 'Retired' => 0];
        foreach (db_all('SELECT status, COUNT(*) AS c FROM assets GROUP BY status') as $row) {
            $counts[$row['status']] = (int)$row['c'];
        }
        $widgets['asset_utilization'] = $counts;
    }

    render('dashboard', [
        'pageTitle' => 'Dashboard',
        'breadcrumbs' => ['Dashboard' => null],
        'widgets' => $widgets,
        'personId' => $personId,
        'lateThreshold' => setting('late_threshold', '08:30'),
    ]);
}
