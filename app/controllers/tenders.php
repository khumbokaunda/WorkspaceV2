<?php
// Sales and Tenders. Manages the full life of a tender response: the record and
// status pipeline, the go or no-go assessment, the compliance requirements
// matrix, the required-documents checklist assembled from the library, the
// proposed team and evaluation criteria, the bill of quantities and pricing,
// securities, the outcome, pipeline analytics, and a printable tender pack.
//
// Assembly is the design principle: documents, CVs, certifications and
// manufacturer authorizations are linked from where they already live rather
// than re-entered. Pricing is need to know: internal cost and margin are shown
// only to holders of tenders.pricing.view.

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Vocabulary
// ---------------------------------------------------------------------------

function tender_statuses(): array
{
    return ['Identified', 'Go Decision Pending', 'Preparing', 'Submitted', 'Under Evaluation', 'Won', 'Lost', 'Cancelled'];
}
function tender_sources(): array { return ['Portal', 'Newspaper', 'Invitation', 'Referral', 'Other']; }
function tender_submission_methods(): array { return ['Portal', 'Physical', 'Email']; }
function tender_requirement_categories(): array { return ['Eligibility', 'Technical', 'Financial', 'Documentary']; }
function tender_compliance_states(): array { return ['Complies', 'Partial', 'Does Not Comply', 'Not Yet Assessed']; }
function tender_doc_source_kinds(): array { return ['Standard Form', 'Company Document', 'Manufacturer Authorization', 'Person CV', 'Certification', 'Other']; }
function tender_security_types(): array { return ['Bid Security', 'Performance Security']; }
function tender_security_forms(): array { return ['Bank Guarantee', 'Insurance Bond', 'Cash']; }
function tender_security_statuses(): array { return ['Active', 'Returned', 'Forfeited', 'Released']; }
function tender_yesno(): array { return ['Yes', 'No', 'Unsure']; }
function tender_go_decisions(): array { return ['Go', 'No-Go', 'Pending']; }

function tenders_can_price(): bool
{
    return user_can('tenders.pricing.view');
}

function tender_or_404(int $tenderId): array
{
    $t = db_row('SELECT * FROM tenders WHERE id = ?', [$tenderId]);
    if (!$t) {
        json_err('That tender does not exist.', 404);
    }
    return $t;
}

// ---------------------------------------------------------------------------
// List, board, record
// ---------------------------------------------------------------------------

function index(): void
{
    render('tenders/index', [
        'pageTitle' => 'Tenders',
        'breadcrumbs' => ['Sales' => null, 'Tenders' => null],
        'canManage' => user_can('tenders.manage'),
        'statuses' => tender_statuses(),
        'sources' => tender_sources(),
        'submissionMethods' => tender_submission_methods(),
        'clients' => module_enabled('clients') ? db_all('SELECT id, name, client_type FROM clients ORDER BY name') : [],
        'owners' => db_all("SELECT u.id, u.username, p.first_name, p.last_name FROM users u LEFT JOIN people p ON p.id = u.person_id WHERE u.is_active = 1 ORDER BY p.first_name, u.username"),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function list_json(): void
{
    $rows = db_all(
        "SELECT t.id, t.reference_number, t.title, t.category, t.status, t.closing_date, t.estimated_value,
                t.currency, c.name AS client_name, c.client_type,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS owner_name,
                (SELECT COUNT(*) FROM tender_requirements r WHERE r.tender_id = t.id AND r.is_mandatory = 1
                    AND r.our_compliance IN ('Does Not Comply','Not Yet Assessed')) AS mandatory_unmet
         FROM tenders t
         LEFT JOIN clients c ON c.id = t.client_id
         LEFT JOIN users u ON u.id = t.bid_owner_id
         LEFT JOIN people p ON p.id = u.person_id
         ORDER BY FIELD(t.status,'Identified','Go Decision Pending','Preparing','Submitted','Under Evaluation','Won','Lost','Cancelled'),
                  t.closing_date IS NULL, t.closing_date"
    );
    json_out(['ok' => true, 'tenders' => $rows]);
}

// Validate and collect the tender record fields from a JSON or form body.
function tender_input(): array
{
    $title = in_str('title');
    $errors = [];
    if ($title === '') {
        $errors['title'] = 'Required.';
    }
    $status = in_str('status');
    if ($status !== '' && !in_array($status, tender_statuses(), true)) {
        $errors['status'] = 'Invalid status.';
    }
    $source = in_str('source');
    if ($source !== '' && !in_array($source, tender_sources(), true)) {
        $errors['source'] = 'Invalid source.';
    }
    $method = in_str('submission_method');
    if ($method !== '' && !in_array($method, tender_submission_methods(), true)) {
        $errors['submission_method'] = 'Invalid method.';
    }
    if (in_str('issue_date') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('issue_date'))) {
        $errors['issue_date'] = 'Invalid date.';
    }
    $value = in_str('estimated_value');
    if ($value !== '' && !is_numeric($value)) {
        $errors['estimated_value'] = 'Enter a number.';
    }
    $vat = in_str('vat_percent');
    if ($vat !== '' && !is_numeric($vat)) {
        $errors['vat_percent'] = 'Enter a number.';
    }
    $clientId = in_int('client_id');
    if ($clientId && !db_val('SELECT id FROM clients WHERE id = ?', [$clientId])) {
        $errors['client_id'] = 'Unknown client.';
    }
    $ownerId = in_int('bid_owner_id');
    if ($ownerId && !db_val('SELECT id FROM users WHERE id = ? AND is_active = 1', [$ownerId])) {
        $errors['bid_owner_id'] = 'Unknown owner.';
    }
    // closing_date arrives as an HTML datetime-local value (YYYY-MM-DDTHH:MM).
    $closing = str_replace('T', ' ', in_str('closing_date'));
    if ($closing !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $closing)) {
        $errors['closing_date'] = 'Invalid date and time.';
    }
    $clarify = str_replace('T', ' ', in_str('clarification_deadline'));
    $siteVisit = str_replace('T', ' ', in_str('site_visit_at'));
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'reference_number' => mb_substr(in_str('reference_number'), 0, 120) ?: null,
        'title' => mb_substr($title, 0, 250),
        'client_id' => $clientId ?: null,
        'opportunity_id' => in_int('opportunity_id') ?: null,
        'category' => in_str('category') ?: null,
        'description' => in_str('description') ?: null,
        'source' => $source ?: 'Portal',
        'issue_date' => in_str('issue_date') ?: null,
        'closing_date' => $closing ?: null,
        'tender_validity_days' => in_int('tender_validity_days') ?: null,
        'clarification_deadline' => $clarify ?: null,
        'site_visit_at' => $siteVisit ?: null,
        'submission_method' => $method ?: 'Portal',
        'estimated_value' => $value !== '' ? (float)$value : null,
        'currency' => strtoupper(in_str('currency')) ?: null,
        'vat_percent' => $vat !== '' ? (float)$vat : null,
        'bid_owner_id' => $ownerId ?: null,
        'status' => $status ?: 'Identified',
    ];
}

function create(): void
{
    $t = tender_input();
    if ($t['bid_owner_id'] === null) {
        $t['bid_owner_id'] = (int)current_user()['id'];
    }
    db_query(
        'INSERT INTO tenders
            (reference_number, title, client_id, opportunity_id, category, description, source, issue_date,
             closing_date, tender_validity_days, clarification_deadline, site_visit_at, submission_method,
             estimated_value, currency, vat_percent, bid_owner_id, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $t['reference_number'], $t['title'], $t['client_id'], $t['opportunity_id'], $t['category'],
            $t['description'], $t['source'], $t['issue_date'], $t['closing_date'], $t['tender_validity_days'],
            $t['clarification_deadline'], $t['site_visit_at'], $t['submission_method'], $t['estimated_value'],
            $t['currency'], $t['vat_percent'], $t['bid_owner_id'], $t['status'],
        ]
    );
    $id = db_insert_id();
    audit('tender.create', 'tender', $id, ['title' => $t['title'], 'status' => $t['status']]);
    json_ok(['tender_id' => $id]);
}

function update(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $t = tender_input();
    db_query(
        'UPDATE tenders SET reference_number = ?, title = ?, client_id = ?, opportunity_id = ?, category = ?,
                description = ?, source = ?, issue_date = ?, closing_date = ?, tender_validity_days = ?,
                clarification_deadline = ?, site_visit_at = ?, submission_method = ?, estimated_value = ?,
                currency = ?, vat_percent = ?, bid_owner_id = ?, status = ? WHERE id = ?',
        [
            $t['reference_number'], $t['title'], $t['client_id'], $t['opportunity_id'], $t['category'],
            $t['description'], $t['source'], $t['issue_date'], $t['closing_date'], $t['tender_validity_days'],
            $t['clarification_deadline'], $t['site_visit_at'], $t['submission_method'], $t['estimated_value'],
            $t['currency'], $t['vat_percent'], $t['bid_owner_id'], $t['status'], $tenderId,
        ]
    );
    audit('tender.update', 'tender', $tenderId, ['title' => $t['title'], 'status' => $t['status']]);
    json_ok();
}

function destroy(string $id): void
{
    $tenderId = (int)$id;
    $t = tender_or_404($tenderId);
    db_query('DELETE FROM tenders WHERE id = ?', [$tenderId]);
    audit('tender.delete', 'tender', $tenderId, ['title' => $t['title']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Detail page: loads the tender and every sub-record, rendered server side.
// ---------------------------------------------------------------------------

function boq_rows(int $tenderId): array
{
    $rows = db_all('SELECT * FROM tender_boq_items WHERE tender_id = ? ORDER BY sort_order, id', [$tenderId]);
    foreach ($rows as &$r) {
        $r['line_total'] = (float)$r['quantity'] * (float)$r['unit_price'];
        $r['line_cost'] = $r['unit_cost'] !== null ? (float)$r['quantity'] * (float)$r['unit_cost'] : null;
    }
    return $rows;
}

// Client price subtotal, VAT and grand total, plus internal cost and margin.
// Optional lines are summed separately so they do not inflate the base bid.
function boq_totals(array $rows, ?float $vatPercent): array
{
    $subtotal = 0.0;
    $optional = 0.0;
    $cost = 0.0;
    foreach ($rows as $r) {
        if ((int)$r['is_optional'] === 1) {
            $optional += $r['line_total'];
        } else {
            $subtotal += $r['line_total'];
        }
        $cost += (float)($r['line_cost'] ?? 0);
    }
    $vat = $vatPercent ? $subtotal * ($vatPercent / 100) : 0.0;
    $grand = $subtotal + $vat;
    return [
        'subtotal' => $subtotal,
        'optional' => $optional,
        'vat' => $vat,
        'grand_total' => $grand,
        'cost' => $cost,
        'margin' => $subtotal - $cost,
        'margin_percent' => $subtotal > 0 ? ($subtotal - $cost) / $subtotal * 100 : 0.0,
    ];
}

function show(string $id): void
{
    $tenderId = (int)$id;
    $t = db_row(
        "SELECT t.*, c.name AS client_name, c.client_type,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), u.username) AS owner_name
         FROM tenders t
         LEFT JOIN clients c ON c.id = t.client_id
         LEFT JOIN users u ON u.id = t.bid_owner_id
         LEFT JOIN people p ON p.id = u.person_id
         WHERE t.id = ?",
        [$tenderId]
    );
    if (!$t) {
        render_error(404, 'Not found', 'That tender does not exist.');
    }
    $canPrice = tenders_can_price();
    $boq = boq_rows($tenderId);

    // Library options for the document checklist and team, only from modules
    // that are enabled so a disabled module contributes nothing.
    $companyDocs = module_enabled('company_docs')
        ? db_all('SELECT id, title, doc_type, expiry_date FROM company_documents ORDER BY doc_type, title') : [];
    $authorizations = module_enabled('suppliers')
        ? db_all('SELECT ma.id, ma.product_line, ma.expiry_date, s.name AS supplier_name
                  FROM manufacturer_authorizations ma JOIN suppliers s ON s.id = ma.supplier_id
                  ORDER BY s.name, ma.product_line') : [];
    $certs = module_enabled('certifications')
        ? db_all("SELECT ce.id, ce.code, ce.name, p.first_name, p.last_name
                  FROM certifications ce JOIN people p ON p.id = ce.person_id
                  WHERE p.employment_status <> 'Terminated' ORDER BY p.first_name, ce.code") : [];
    $people = db_all("SELECT id, first_name, last_name, job_title FROM people WHERE employment_status = 'Active' ORDER BY first_name");

    render('tenders/show', [
        'pageTitle' => $t['title'],
        'breadcrumbs' => ['Sales' => null, 'Tenders' => '/tenders', ($t['reference_number'] ?: $t['title']) => null],
        'tender' => $t,
        'canManage' => user_can('tenders.manage'),
        'canPrice' => $canPrice,
        'go' => db_row('SELECT * FROM tender_go_assessments WHERE tender_id = ?', [$tenderId]),
        'requirements' => db_all('SELECT * FROM tender_requirements WHERE tender_id = ? ORDER BY category, sort_order, id', [$tenderId]),
        'checklist' => tender_checklist_resolved($tenderId),
        'team' => db_all(
            "SELECT tt.*, p.first_name, p.last_name, p.job_title,
                    (SELECT COUNT(*) FROM certifications ce WHERE ce.person_id = tt.person_id) AS cert_count,
                    (SELECT COUNT(*) FROM documents d WHERE d.person_id = tt.person_id AND d.doc_type = 'CV') AS cv_count
             FROM tender_team tt JOIN people p ON p.id = tt.person_id WHERE tt.tender_id = ? ORDER BY tt.id",
            [$tenderId]
        ),
        'criteria' => db_all('SELECT * FROM tender_evaluation_criteria WHERE tender_id = ? ORDER BY sort_order, id', [$tenderId]),
        'boq' => $boq,
        'boqTotals' => boq_totals($boq, $t['vat_percent'] !== null ? (float)$t['vat_percent'] : null),
        'securities' => db_all('SELECT * FROM tender_securities WHERE tender_id = ? ORDER BY id', [$tenderId]),
        'statuses' => tender_statuses(),
        'sources' => tender_sources(),
        'submissionMethods' => tender_submission_methods(),
        'requirementCategories' => tender_requirement_categories(),
        'complianceStates' => tender_compliance_states(),
        'docSourceKinds' => tender_doc_source_kinds(),
        'securityTypes' => tender_security_types(),
        'securityForms' => tender_security_forms(),
        'securityStatuses' => tender_security_statuses(),
        'yesno' => tender_yesno(),
        'goDecisions' => tender_go_decisions(),
        'clients' => module_enabled('clients') ? db_all('SELECT id, name FROM clients ORDER BY name') : [],
        'owners' => db_all("SELECT u.id, u.username, p.first_name, p.last_name FROM users u LEFT JOIN people p ON p.id = u.person_id WHERE u.is_active = 1 ORDER BY p.first_name, u.username"),
        'companyDocs' => $companyDocs,
        'authorizations' => $authorizations,
        'certOptions' => $certs,
        'people' => $people,
        'currency' => $t['currency'] ?: setting('currency', 'MWK'),
        'projectsEnabled' => module_enabled('projects'),
    ]);
}

// Resolve each checklist row to a readable source label and an expiry flag when
// it links to a compliance document or authorization that has lapsed.
function tender_checklist_resolved(int $tenderId): array
{
    $rows = db_all('SELECT * FROM tender_documents WHERE tender_id = ? ORDER BY sort_order, id', [$tenderId]);
    foreach ($rows as &$r) {
        $r['source_label'] = '';
        $r['expiry_flag'] = null; // null none, or 'Expired'/'Expiring'
        if ($r['source_id'] === null) {
            continue;
        }
        $sid = (int)$r['source_id'];
        if ($r['source_kind'] === 'Company Document') {
            $d = db_row('SELECT title, expiry_date FROM company_documents WHERE id = ?', [$sid]);
            if ($d) {
                $r['source_label'] = $d['title'];
                $r['expiry_flag'] = expiry_status($d['expiry_date']);
            }
        } elseif ($r['source_kind'] === 'Manufacturer Authorization') {
            $a = db_row('SELECT ma.product_line, ma.expiry_date, s.name FROM manufacturer_authorizations ma JOIN suppliers s ON s.id = ma.supplier_id WHERE ma.id = ?', [$sid]);
            if ($a) {
                $r['source_label'] = $a['name'] . ' - ' . $a['product_line'];
                $r['expiry_flag'] = expiry_status($a['expiry_date']);
            }
        } elseif ($r['source_kind'] === 'Person CV') {
            $p = db_row('SELECT first_name, last_name FROM people WHERE id = ?', [$sid]);
            if ($p) {
                $r['source_label'] = trim($p['first_name'] . ' ' . $p['last_name']) . ' CV';
            }
        } elseif ($r['source_kind'] === 'Certification') {
            $ce = db_row('SELECT ce.code, ce.expires_on, p.first_name, p.last_name FROM certifications ce JOIN people p ON p.id = ce.person_id WHERE ce.id = ?', [$sid]);
            if ($ce) {
                $r['source_label'] = trim($ce['first_name'] . ' ' . $ce['last_name']) . ' - ' . $ce['code'];
                $r['expiry_flag'] = expiry_status($ce['expires_on']);
            }
        }
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// Go or No-Go
// ---------------------------------------------------------------------------

function save_go_assessment(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $yn = fn($k) => in_array(in_str($k), tender_yesno(), true) ? in_str($k) : null;
    $decision = in_array(in_str('decision'), tender_go_decisions(), true) ? in_str('decision') : 'Pending';
    db_query(
        'INSERT INTO tender_go_assessments
            (tender_id, meets_eligibility, has_authorizations, can_meet_delivery, value_worth_effort, has_experience, decision, rationale, assessed_by)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE meets_eligibility = VALUES(meets_eligibility), has_authorizations = VALUES(has_authorizations),
             can_meet_delivery = VALUES(can_meet_delivery), value_worth_effort = VALUES(value_worth_effort),
             has_experience = VALUES(has_experience), decision = VALUES(decision), rationale = VALUES(rationale),
             assessed_by = VALUES(assessed_by)',
        [
            $tenderId, $yn('meets_eligibility'), $yn('has_authorizations'), $yn('can_meet_delivery'),
            $yn('value_worth_effort'), $yn('has_experience'), $decision, in_str('rationale') ?: null,
            (int)current_user()['id'],
        ]
    );
    audit('tender.go_assessment', 'tender', $tenderId, ['decision' => $decision]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Requirements matrix
// ---------------------------------------------------------------------------

function add_requirement(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $text = in_str('requirement_text');
    if ($text === '') {
        json_err('The requirement text is required.', 422, ['requirement_text' => 'Required.']);
    }
    $category = in_array(in_str('category'), tender_requirement_categories(), true) ? in_str('category') : 'Eligibility';
    $compliance = in_array(in_str('our_compliance'), tender_compliance_states(), true) ? in_str('our_compliance') : 'Not Yet Assessed';
    db_query(
        'INSERT INTO tender_requirements (tender_id, category, requirement_text, is_mandatory, our_compliance, evidence_reference, remarks, sort_order)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            $tenderId, $category, mb_substr($text, 0, 1000), in_int('is_mandatory') ? 1 : 0, $compliance,
            in_str('evidence_reference') ?: null, in_str('remarks') ?: null, in_int('sort_order') ?: 0,
        ]
    );
    audit('tender.requirement.add', 'tender', $tenderId, ['category' => $category]);
    json_ok(['requirement_id' => db_insert_id()]);
}

function update_requirement(string $id): void
{
    $reqId = (int)$id;
    $req = db_row('SELECT * FROM tender_requirements WHERE id = ?', [$reqId]);
    if (!$req) {
        json_err('That requirement does not exist.', 404);
    }
    $text = in_str('requirement_text');
    if ($text === '') {
        json_err('The requirement text is required.', 422, ['requirement_text' => 'Required.']);
    }
    $category = in_array(in_str('category'), tender_requirement_categories(), true) ? in_str('category') : $req['category'];
    $compliance = in_array(in_str('our_compliance'), tender_compliance_states(), true) ? in_str('our_compliance') : $req['our_compliance'];
    db_query(
        'UPDATE tender_requirements SET category = ?, requirement_text = ?, is_mandatory = ?, our_compliance = ?, evidence_reference = ?, remarks = ? WHERE id = ?',
        [
            $category, mb_substr($text, 0, 1000), in_int('is_mandatory') ? 1 : 0, $compliance,
            in_str('evidence_reference') ?: null, in_str('remarks') ?: null, $reqId,
        ]
    );
    audit('tender.requirement.update', 'tender', (int)$req['tender_id'], ['requirement_id' => $reqId]);
    json_ok();
}

function delete_requirement(string $id): void
{
    $reqId = (int)$id;
    $req = db_row('SELECT tender_id FROM tender_requirements WHERE id = ?', [$reqId]);
    if (!$req) {
        json_err('That requirement does not exist.', 404);
    }
    db_query('DELETE FROM tender_requirements WHERE id = ?', [$reqId]);
    audit('tender.requirement.delete', 'tender', (int)$req['tender_id'], ['requirement_id' => $reqId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Document checklist
// ---------------------------------------------------------------------------

function add_document(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $label = in_str('item_label');
    if ($label === '') {
        json_err('A checklist item label is required.', 422, ['item_label' => 'Required.']);
    }
    $kind = in_array(in_str('source_kind'), tender_doc_source_kinds(), true) ? in_str('source_kind') : 'Standard Form';
    $sourceId = $kind === 'Standard Form' || $kind === 'Other' ? null : (in_int('source_id') ?: null);
    db_query(
        'INSERT INTO tender_documents (tender_id, item_label, source_kind, source_id, is_ready, notes, sort_order)
         VALUES (?,?,?,?,?,?,?)',
        [$tenderId, mb_substr($label, 0, 250), $kind, $sourceId, in_int('is_ready') ? 1 : 0, in_str('notes') ?: null, in_int('sort_order') ?: 0]
    );
    audit('tender.document.add', 'tender', $tenderId, ['label' => $label, 'kind' => $kind]);
    json_ok(['document_id' => db_insert_id()]);
}

function update_document(string $id): void
{
    $docId = (int)$id;
    $doc = db_row('SELECT * FROM tender_documents WHERE id = ?', [$docId]);
    if (!$doc) {
        json_err('That checklist item does not exist.', 404);
    }
    $label = in_str('item_label') ?: $doc['item_label'];
    $kind = in_array(in_str('source_kind'), tender_doc_source_kinds(), true) ? in_str('source_kind') : $doc['source_kind'];
    $sourceId = $kind === 'Standard Form' || $kind === 'Other' ? null : (in_int('source_id') ?: null);
    db_query(
        'UPDATE tender_documents SET item_label = ?, source_kind = ?, source_id = ?, is_ready = ?, notes = ? WHERE id = ?',
        [mb_substr($label, 0, 250), $kind, $sourceId, in_int('is_ready') ? 1 : 0, in_str('notes') ?: null, $docId]
    );
    audit('tender.document.update', 'tender', (int)$doc['tender_id'], ['document_id' => $docId]);
    json_ok();
}

function delete_document(string $id): void
{
    $docId = (int)$id;
    $doc = db_row('SELECT tender_id FROM tender_documents WHERE id = ?', [$docId]);
    if (!$doc) {
        json_err('That checklist item does not exist.', 404);
    }
    db_query('DELETE FROM tender_documents WHERE id = ?', [$docId]);
    audit('tender.document.delete', 'tender', (int)$doc['tender_id'], ['document_id' => $docId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Proposed team
// ---------------------------------------------------------------------------

function add_team(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $personId = in_int('person_id');
    if (!$personId || !db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('Choose a valid person.', 422, ['person_id' => 'Invalid person.']);
    }
    $role = in_str('proposed_role');
    if ($role === '') {
        json_err('A proposed role is required.', 422, ['proposed_role' => 'Required.']);
    }
    if (db_val('SELECT id FROM tender_team WHERE tender_id = ? AND person_id = ?', [$tenderId, $personId])) {
        json_err('That person is already on this tender team.', 409);
    }
    db_query(
        'INSERT INTO tender_team (tender_id, person_id, proposed_role, notes) VALUES (?,?,?,?)',
        [$tenderId, $personId, mb_substr($role, 0, 120), in_str('notes') ?: null]
    );
    audit('tender.team.add', 'tender', $tenderId, ['person_id' => $personId, 'role' => $role]);
    json_ok(['team_id' => db_insert_id()]);
}

function delete_team(string $id): void
{
    $teamId = (int)$id;
    $row = db_row('SELECT tender_id FROM tender_team WHERE id = ?', [$teamId]);
    if (!$row) {
        json_err('That team member does not exist.', 404);
    }
    db_query('DELETE FROM tender_team WHERE id = ?', [$teamId]);
    audit('tender.team.remove', 'tender', (int)$row['tender_id'], ['team_id' => $teamId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Evaluation criteria
// ---------------------------------------------------------------------------

function add_criterion(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $criterion = in_str('criterion');
    if ($criterion === '') {
        json_err('The criterion text is required.', 422, ['criterion' => 'Required.']);
    }
    foreach (['max_points', 'weight', 'self_score'] as $numField) {
        $v = in_str($numField);
        if ($v !== '' && !is_numeric($v)) {
            json_err('Enter a number.', 422, [$numField => 'Enter a number.']);
        }
    }
    db_query(
        'INSERT INTO tender_evaluation_criteria (tender_id, criterion, max_points, weight, our_evidence, self_score, sort_order)
         VALUES (?,?,?,?,?,?,?)',
        [
            $tenderId, mb_substr($criterion, 0, 500), (float)(in_str('max_points') ?: 0),
            in_str('weight') !== '' ? (float)in_str('weight') : null, in_str('our_evidence') ?: null,
            in_str('self_score') !== '' ? (float)in_str('self_score') : null, in_int('sort_order') ?: 0,
        ]
    );
    audit('tender.criterion.add', 'tender', $tenderId, ['criterion' => $criterion]);
    json_ok(['criterion_id' => db_insert_id()]);
}

function update_criterion(string $id): void
{
    $critId = (int)$id;
    $crit = db_row('SELECT * FROM tender_evaluation_criteria WHERE id = ?', [$critId]);
    if (!$crit) {
        json_err('That criterion does not exist.', 404);
    }
    $criterion = in_str('criterion') ?: $crit['criterion'];
    foreach (['max_points', 'weight', 'self_score'] as $numField) {
        $v = in_str($numField);
        if ($v !== '' && !is_numeric($v)) {
            json_err('Enter a number.', 422, [$numField => 'Enter a number.']);
        }
    }
    db_query(
        'UPDATE tender_evaluation_criteria SET criterion = ?, max_points = ?, weight = ?, our_evidence = ?, self_score = ? WHERE id = ?',
        [
            mb_substr($criterion, 0, 500), (float)(in_str('max_points') ?: 0),
            in_str('weight') !== '' ? (float)in_str('weight') : null, in_str('our_evidence') ?: null,
            in_str('self_score') !== '' ? (float)in_str('self_score') : null, $critId,
        ]
    );
    audit('tender.criterion.update', 'tender', (int)$crit['tender_id'], ['criterion_id' => $critId]);
    json_ok();
}

function delete_criterion(string $id): void
{
    $critId = (int)$id;
    $crit = db_row('SELECT tender_id FROM tender_evaluation_criteria WHERE id = ?', [$critId]);
    if (!$crit) {
        json_err('That criterion does not exist.', 404);
    }
    db_query('DELETE FROM tender_evaluation_criteria WHERE id = ?', [$critId]);
    audit('tender.criterion.delete', 'tender', (int)$crit['tender_id'], ['criterion_id' => $critId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Bill of quantities
// ---------------------------------------------------------------------------

// Collect a BoQ line. Cost and markup are optional internal fields; unit_price
// is the client-facing figure. When a markup is given and no explicit price,
// the price is derived from cost and markup.
function boq_input(): array
{
    $desc = in_str('description');
    if ($desc === '') {
        json_err('A line description is required.', 422, ['description' => 'Required.']);
    }
    foreach (['quantity', 'unit_cost', 'markup_percent', 'unit_price'] as $numField) {
        $v = in_str($numField);
        if ($v !== '' && !is_numeric($v)) {
            json_err('Enter a number.', 422, [$numField => 'Enter a number.']);
        }
    }
    $qty = in_str('quantity') !== '' ? (float)in_str('quantity') : 1.0;
    $cost = in_str('unit_cost') !== '' ? (float)in_str('unit_cost') : null;
    $markup = in_str('markup_percent') !== '' ? (float)in_str('markup_percent') : null;
    $price = in_str('unit_price') !== '' ? (float)in_str('unit_price') : null;
    if ($price === null && $cost !== null && $markup !== null) {
        $price = $cost * (1 + $markup / 100);
    }
    return [
        'item_no' => in_str('item_no') ?: null,
        'description' => mb_substr($desc, 0, 500),
        'specification' => in_str('specification') ?: null,
        'quantity' => $qty,
        'unit' => in_str('unit') ?: null,
        'unit_cost' => $cost,
        'markup_percent' => $markup,
        'unit_price' => $price ?? 0.0,
        'is_optional' => in_int('is_optional') ? 1 : 0,
        'sort_order' => in_int('sort_order') ?: 0,
    ];
}

function add_boq(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $b = boq_input();
    db_query(
        'INSERT INTO tender_boq_items (tender_id, item_no, description, specification, quantity, unit, unit_cost, markup_percent, unit_price, is_optional, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [
            $tenderId, $b['item_no'], $b['description'], $b['specification'], $b['quantity'], $b['unit'],
            $b['unit_cost'], $b['markup_percent'], $b['unit_price'], $b['is_optional'], $b['sort_order'],
        ]
    );
    audit('tender.boq.add', 'tender', $tenderId, ['description' => $b['description']]);
    json_ok(['item_id' => db_insert_id()]);
}

function update_boq(string $id): void
{
    $itemId = (int)$id;
    $item = db_row('SELECT * FROM tender_boq_items WHERE id = ?', [$itemId]);
    if (!$item) {
        json_err('That line does not exist.', 404);
    }
    $b = boq_input();
    // Internal cost and margin are need to know. An editor without pricing
    // access never sees the cost fields, so their submission must not overwrite
    // them: the stored cost and markup are preserved, and the client price is
    // taken as given rather than derived from a cost they cannot see.
    if (!tenders_can_price()) {
        $b['unit_cost'] = $item['unit_cost'] !== null ? (float)$item['unit_cost'] : null;
        $b['markup_percent'] = $item['markup_percent'] !== null ? (float)$item['markup_percent'] : null;
        if (in_str('unit_price') !== '') {
            $b['unit_price'] = (float)in_str('unit_price');
        } else {
            $b['unit_price'] = (float)$item['unit_price'];
        }
    }
    db_query(
        'UPDATE tender_boq_items SET item_no = ?, description = ?, specification = ?, quantity = ?, unit = ?, unit_cost = ?, markup_percent = ?, unit_price = ?, is_optional = ? WHERE id = ?',
        [
            $b['item_no'], $b['description'], $b['specification'], $b['quantity'], $b['unit'],
            $b['unit_cost'], $b['markup_percent'], $b['unit_price'], $b['is_optional'], $itemId,
        ]
    );
    audit('tender.boq.update', 'tender', (int)$item['tender_id'], ['item_id' => $itemId]);
    json_ok();
}

function delete_boq(string $id): void
{
    $itemId = (int)$id;
    $item = db_row('SELECT tender_id FROM tender_boq_items WHERE id = ?', [$itemId]);
    if (!$item) {
        json_err('That line does not exist.', 404);
    }
    db_query('DELETE FROM tender_boq_items WHERE id = ?', [$itemId]);
    audit('tender.boq.delete', 'tender', (int)$item['tender_id'], ['item_id' => $itemId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Securities
// ---------------------------------------------------------------------------

function security_input(): array
{
    $type = in_array(in_str('security_type'), tender_security_types(), true) ? in_str('security_type') : 'Bid Security';
    $form = in_array(in_str('form'), tender_security_forms(), true) ? in_str('form') : null;
    $status = in_array(in_str('status'), tender_security_statuses(), true) ? in_str('status') : 'Active';
    $amount = in_str('amount');
    if ($amount !== '' && !is_numeric($amount)) {
        json_err('Enter a number for the amount.', 422, ['amount' => 'Enter a number.']);
    }
    foreach (['issue_date', 'expiry_date'] as $dateField) {
        $v = in_str($dateField);
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            json_err('Enter a valid date.', 422, [$dateField => 'Invalid date.']);
        }
    }
    return [
        'security_type' => $type,
        'amount' => $amount !== '' ? (float)$amount : null,
        'currency' => strtoupper(in_str('currency')) ?: null,
        'form' => $form,
        'issuing_institution' => in_str('issuing_institution') ?: null,
        'issue_date' => in_str('issue_date') ?: null,
        'expiry_date' => in_str('expiry_date') ?: null,
        'status' => $status,
        'reference' => in_str('reference') ?: null,
    ];
}

function add_security(string $id): void
{
    $tenderId = (int)$id;
    tender_or_404($tenderId);
    $s = security_input();
    db_query(
        'INSERT INTO tender_securities (tender_id, security_type, amount, currency, form, issuing_institution, issue_date, expiry_date, status, reference)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            $tenderId, $s['security_type'], $s['amount'], $s['currency'], $s['form'],
            $s['issuing_institution'], $s['issue_date'], $s['expiry_date'], $s['status'], $s['reference'],
        ]
    );
    audit('tender.security.add', 'tender', $tenderId, ['type' => $s['security_type']]);
    json_ok(['security_id' => db_insert_id()]);
}

function update_security(string $id): void
{
    $secId = (int)$id;
    $sec = db_row('SELECT tender_id FROM tender_securities WHERE id = ?', [$secId]);
    if (!$sec) {
        json_err('That security does not exist.', 404);
    }
    $s = security_input();
    db_query(
        'UPDATE tender_securities SET security_type = ?, amount = ?, currency = ?, form = ?, issuing_institution = ?, issue_date = ?, expiry_date = ?, status = ?, reference = ? WHERE id = ?',
        [
            $s['security_type'], $s['amount'], $s['currency'], $s['form'], $s['issuing_institution'],
            $s['issue_date'], $s['expiry_date'], $s['status'], $s['reference'], $secId,
        ]
    );
    audit('tender.security.update', 'tender', (int)$sec['tender_id'], ['security_id' => $secId]);
    json_ok();
}

function delete_security(string $id): void
{
    $secId = (int)$id;
    $sec = db_row('SELECT tender_id FROM tender_securities WHERE id = ?', [$secId]);
    if (!$sec) {
        json_err('That security does not exist.', 404);
    }
    db_query('DELETE FROM tender_securities WHERE id = ?', [$secId]);
    audit('tender.security.delete', 'tender', (int)$sec['tender_id'], ['security_id' => $secId]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Outcome, handoff
// ---------------------------------------------------------------------------

function set_outcome(string $id): void
{
    $tenderId = (int)$id;
    $t = tender_or_404($tenderId);
    $status = in_str('status');
    if (!in_array($status, ['Won', 'Lost', 'Cancelled'], true)) {
        json_err('Choose Won, Lost or Cancelled.', 422, ['status' => 'Invalid outcome.']);
    }
    $award = in_str('award_value');
    if ($award !== '' && !is_numeric($award)) {
        json_err('Enter a number for the award value.', 422, ['award_value' => 'Enter a number.']);
    }
    db_query(
        'UPDATE tenders SET status = ?, award_value = ?, outcome_notes = ? WHERE id = ?',
        [$status, $award !== '' ? (float)$award : null, in_str('outcome_notes') ?: null, $tenderId]
    );

    $created = ['project_id' => null, 'security_id' => null];

    // A won tender can create a delivery project and a performance security.
    if ($status === 'Won' && in_int('create_project') && module_enabled('projects')) {
        // The bid owner's linked person leads the delivery project when known.
        $leadId = db_val('SELECT person_id FROM users WHERE id = ?', [(int)($t['bid_owner_id'] ?? 0)]);
        db_query(
            "INSERT INTO projects (name, description, status, lead_id) VALUES (?,?,'Active',?)",
            ['Delivery: ' . $t['title'], 'Created from won tender ' . ($t['reference_number'] ?: ('#' . $tenderId)), $leadId ?: null]
        );
        $created['project_id'] = db_insert_id();
        audit('tender.project.create', 'tender', $tenderId, ['project_id' => $created['project_id']]);
    }
    if ($status === 'Won' && in_int('create_performance_security')) {
        db_query(
            "INSERT INTO tender_securities (tender_id, security_type, amount, currency, status)
             VALUES (?, 'Performance Security', ?, ?, 'Active')",
            [$tenderId, $award !== '' ? (float)$award : null, $t['currency']]
        );
        $created['security_id'] = db_insert_id();
    }

    audit('tender.outcome', 'tender', $tenderId, ['status' => $status, 'award_value' => $award]);
    json_ok($created);
}

// ---------------------------------------------------------------------------
// Analytics
// ---------------------------------------------------------------------------

function analytics(): void
{
    $decided = db_all("SELECT status, COUNT(*) c, COALESCE(SUM(COALESCE(award_value, estimated_value)),0) v
                       FROM tenders WHERE status IN ('Won','Lost') GROUP BY status");
    $won = ['count' => 0, 'value' => 0.0];
    $lost = ['count' => 0, 'value' => 0.0];
    foreach ($decided as $r) {
        if ($r['status'] === 'Won') { $won = ['count' => (int)$r['c'], 'value' => (float)$r['v']]; }
        else { $lost = ['count' => (int)$r['c'], 'value' => (float)$r['v']]; }
    }
    $totalDecided = $won['count'] + $lost['count'];

    render('tenders/analytics', [
        'pageTitle' => 'Tender analytics',
        'breadcrumbs' => ['Sales' => null, 'Tenders' => '/tenders', 'Analytics' => null],
        'won' => $won,
        'lost' => $lost,
        'winRate' => $totalDecided > 0 ? $won['count'] / $totalDecided * 100 : 0.0,
        'byStatus' => db_all('SELECT status, COUNT(*) c FROM tenders GROUP BY status'),
        'byClientType' => db_all(
            "SELECT COALESCE(c.client_type,'Unknown') AS client_type,
                    SUM(t.status='Won') AS won, SUM(t.status='Lost') AS lost
             FROM tenders t LEFT JOIN clients c ON c.id = t.client_id
             WHERE t.status IN ('Won','Lost') GROUP BY c.client_type"
        ),
        'byCategory' => db_all(
            "SELECT COALESCE(NULLIF(category,''),'Uncategorised') AS category,
                    SUM(status='Won') AS won, SUM(status='Lost') AS lost
             FROM tenders WHERE status IN ('Won','Lost') GROUP BY category"
        ),
        'currency' => setting('currency', 'MWK'),
    ]);
}

// ---------------------------------------------------------------------------
// Compiled tender pack (printable, browser prints to PDF)
// ---------------------------------------------------------------------------

function export_pack(string $id): void
{
    $tenderId = (int)$id;
    $t = db_row(
        'SELECT t.*, c.name AS client_name FROM tenders t LEFT JOIN clients c ON c.id = t.client_id WHERE t.id = ?',
        [$tenderId]
    );
    if (!$t) {
        render_error(404, 'Not found', 'That tender does not exist.');
    }
    $boq = boq_rows($tenderId);
    render('tenders/pack', [
        'pageTitle' => 'Tender pack',
        'tender' => $t,
        'profile' => company_profile(),
        'requirements' => db_all('SELECT * FROM tender_requirements WHERE tender_id = ? ORDER BY category, sort_order, id', [$tenderId]),
        'checklist' => tender_checklist_resolved($tenderId),
        'team' => db_all('SELECT tt.*, p.first_name, p.last_name, p.job_title FROM tender_team tt JOIN people p ON p.id = tt.person_id WHERE tt.tender_id = ? ORDER BY tt.id', [$tenderId]),
        'boq' => $boq,
        'boqTotals' => boq_totals($boq, $t['vat_percent'] !== null ? (float)$t['vat_percent'] : null),
        'currency' => $t['currency'] ?: setting('currency', 'MWK'),
    ], false);
}
