<?php
// Payroll and HR. A configurable engine, not hardcoded tax logic: pay is built
// from a catalogue of components a company confirms with their accountant.
// Access is tightly gated: payroll.view_all sees anyone's figures, everyone
// else sees only their own personal records and payslips. Account numbers and
// personal data are sensitive and never exposed beyond the owner and HR.
//
// This file covers personal records, the pay component catalogue and salary
// structures (part one) and, further down, payroll runs, payslips and loans.

declare(strict_types=1);

function payroll_can_view_all(): bool { return user_can('payroll.view_all'); }
function payroll_can_manage(): bool { return user_can('payroll.manage'); }
function payroll_can_approve(): bool { return user_can('payroll.approve'); }
function payroll_my_pid(): int { return (int)(current_user()['person_id'] ?? 0); }

// View a person's records: their own, or anyone's with view_all.
function records_can_view(int $personId): bool
{
    return ($personId > 0 && $personId === payroll_my_pid()) || payroll_can_view_all();
}

// Edit a person's personal records: their own (self-service), or anyone's with
// payroll.manage acting as HR.
function records_can_edit(int $personId): bool
{
    return ($personId > 0 && $personId === payroll_my_pid()) || payroll_can_manage();
}

function comp_types(): array { return ['Earning', 'Deduction']; }
function comp_methods(): array { return ['Fixed Amount', 'Percentage of Basic', 'Percentage of Gross', 'Banded']; }

// Mask an account number to its last four digits.
function mask_account(?string $acc): string
{
    $acc = (string)$acc;
    if (mb_strlen($acc) <= 4) {
        return $acc === '' ? '' : str_repeat('*', mb_strlen($acc));
    }
    return str_repeat('*', mb_strlen($acc) - 4) . mb_substr($acc, -4);
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

function index(): void
{
    $myPid = payroll_my_pid();
    render('payroll/index', [
        'pageTitle' => 'Payroll and HR',
        'breadcrumbs' => ['People' => null, 'Payroll' => null],
        'myPid' => $myPid,
        'canViewAll' => payroll_can_view_all(),
        'canManage' => payroll_can_manage(),
        'canApprove' => payroll_can_approve(),
        'myPayslips' => my_payslips($myPid),
        'statutoryMode' => setting('payroll_statutory_mode', 'simple'),
        'people' => payroll_can_view_all()
            ? db_all(
                "SELECT p.id, p.first_name, p.last_name, p.job_title, p.department,
                        (SELECT basic_salary FROM salary_structures s WHERE s.person_id = p.id AND s.is_current = 1 LIMIT 1) AS basic_salary
                 FROM people p WHERE p.employment_status = 'Active' ORDER BY p.first_name"
              )
            : [],
        'currency' => setting('currency', 'MWK'),
    ]);
}

// ---------------------------------------------------------------------------
// Personal and HR records
// ---------------------------------------------------------------------------

function records(string $id): void
{
    $personId = (int)$id;
    $person = db_row('SELECT * FROM people WHERE id = ?', [$personId]);
    if (!$person) {
        render_error(404, 'Not found', 'That person does not exist.');
    }
    if (!records_can_view($personId)) {
        render_error(403, 'Access denied', 'You may only view your own records.');
    }
    $canEdit = records_can_edit($personId);
    $canSeeFigures = $personId === payroll_my_pid() || payroll_can_view_all();

    $structure = $canSeeFigures ? db_row('SELECT * FROM salary_structures WHERE person_id = ? AND is_current = 1 LIMIT 1', [$personId]) : null;
    $structureLines = $structure
        ? db_all(
            'SELECT sl.*, pc.name, pc.comp_type, pc.calc_method FROM salary_structure_lines sl
             JOIN pay_components pc ON pc.id = sl.component_id WHERE sl.structure_id = ? ORDER BY pc.comp_type DESC, pc.sort_order, pc.name',
            [(int)$structure['id']]
          )
        : [];

    $banks = db_all('SELECT * FROM person_bank_accounts WHERE person_id = ? ORDER BY is_primary DESC, id', [$personId]);
    foreach ($banks as &$b) {
        // Only the owner sees the full number; HR sees it masked.
        $b['account_display'] = $personId === payroll_my_pid() ? $b['account_number'] : mask_account($b['account_number']);
        unset($b['account_number']);
    }

    render('payroll/records', [
        'pageTitle' => $person['first_name'] . ' ' . $person['last_name'] . ' records',
        'breadcrumbs' => ['People' => null, 'Payroll' => '/payroll', 'Records' => null],
        'person' => $person,
        'details' => db_row('SELECT * FROM person_details WHERE person_id = ?', [$personId]) ?: [],
        'beneficiaries' => db_all('SELECT * FROM person_beneficiaries WHERE person_id = ? ORDER BY id', [$personId]),
        'banks' => $banks,
        'structure' => $structure,
        'structureLines' => $structureLines,
        'components' => db_all('SELECT * FROM pay_components WHERE is_active = 1 ORDER BY comp_type DESC, sort_order, name'),
        'canEdit' => $canEdit,
        'canManage' => payroll_can_manage(),
        'canSeeFigures' => $canSeeFigures,
        'currency' => setting('currency', 'MWK'),
    ]);
}

function person_or_403(int $personId, bool $needEdit = false): void
{
    if (!db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('That person does not exist.', 404);
    }
    if ($needEdit ? !records_can_edit($personId) : !records_can_view($personId)) {
        json_err('You do not have permission for these records.', 403);
    }
}

function save_details(string $id): void
{
    $personId = (int)$id;
    person_or_403($personId, true);
    if (in_str('date_of_birth') !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str('date_of_birth'))) {
        json_err('Enter a valid date of birth.', 422, ['date_of_birth' => 'Invalid date.']);
    }
    if (in_str('personal_email') !== '' && filter_var(in_str('personal_email'), FILTER_VALIDATE_EMAIL) === false) {
        json_err('Enter a valid personal email.', 422, ['personal_email' => 'Invalid email.']);
    }
    db_query(
        'INSERT INTO person_details (person_id, date_of_birth, national_id, gender, marital_status, tax_id, home_address, personal_phone, personal_email, emergency_contact_name, emergency_contact_phone)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), national_id = VALUES(national_id), gender = VALUES(gender),
             marital_status = VALUES(marital_status), tax_id = VALUES(tax_id), home_address = VALUES(home_address),
             personal_phone = VALUES(personal_phone), personal_email = VALUES(personal_email),
             emergency_contact_name = VALUES(emergency_contact_name), emergency_contact_phone = VALUES(emergency_contact_phone)',
        [
            $personId, in_str('date_of_birth') ?: null, in_str('national_id') ?: null, in_str('gender') ?: null,
            in_str('marital_status') ?: null, in_str('tax_id') ?: null, in_str('home_address') ?: null,
            in_str('personal_phone') ?: null, in_str('personal_email') ?: null,
            in_str('emergency_contact_name') ?: null, in_str('emergency_contact_phone') ?: null,
        ]
    );
    audit('person_details.save', 'person', $personId, []);
    json_ok();
}

function add_beneficiary(string $id): void
{
    $personId = (int)$id;
    person_or_403($personId, true);
    $name = in_str('name');
    if ($name === '') {
        json_err('A beneficiary name is required.', 422, ['name' => 'Required.']);
    }
    $share = in_str('share_percent');
    if ($share !== '' && !is_numeric($share)) {
        json_err('Enter a number for the share.', 422, ['share_percent' => 'Enter a number.']);
    }
    db_query(
        'INSERT INTO person_beneficiaries (person_id, name, relationship, share_percent, contact, is_payroll_beneficiary) VALUES (?,?,?,?,?,?)',
        [$personId, mb_substr($name, 0, 160), in_str('relationship') ?: null, $share !== '' ? (float)$share : null, in_str('contact') ?: null, in_int('is_payroll_beneficiary') ? 1 : 0]
    );
    audit('beneficiary.add', 'person', $personId, ['beneficiary_id' => db_insert_id()]);
    json_ok(['beneficiary_id' => db_insert_id()]);
}

function delete_beneficiary(string $id): void
{
    $b = db_row('SELECT * FROM person_beneficiaries WHERE id = ?', [(int)$id]);
    if (!$b) {
        json_err('That beneficiary does not exist.', 404);
    }
    person_or_403((int)$b['person_id'], true);
    db_query('DELETE FROM person_beneficiaries WHERE id = ?', [(int)$id]);
    audit('beneficiary.delete', 'person', (int)$b['person_id'], ['beneficiary_id' => (int)$id]);
    json_ok();
}

function add_bank(string $id): void
{
    $personId = (int)$id;
    person_or_403($personId, true);
    $bank = in_str('bank_name');
    $acc = in_str('account_number');
    if ($bank === '' || $acc === '') {
        json_err('Bank name and account number are required.', 422, [
            'bank_name' => $bank === '' ? 'Required.' : null,
            'account_number' => $acc === '' ? 'Required.' : null,
        ]);
    }
    $primary = in_int('is_primary') ? 1 : 0;
    if ($primary) {
        db_query('UPDATE person_bank_accounts SET is_primary = 0 WHERE person_id = ?', [$personId]);
    }
    db_query(
        'INSERT INTO person_bank_accounts (person_id, bank_name, branch, account_name, account_number, is_primary) VALUES (?,?,?,?,?,?)',
        [$personId, mb_substr($bank, 0, 120), in_str('branch') ?: null, in_str('account_name') ?: null, mb_substr($acc, 0, 60), $primary]
    );
    // The account number itself is never written to the audit detail.
    audit('bank_account.add', 'person', $personId, ['bank' => $bank]);
    json_ok(['bank_id' => db_insert_id()]);
}

function delete_bank(string $id): void
{
    $b = db_row('SELECT * FROM person_bank_accounts WHERE id = ?', [(int)$id]);
    if (!$b) {
        json_err('That account does not exist.', 404);
    }
    person_or_403((int)$b['person_id'], true);
    db_query('DELETE FROM person_bank_accounts WHERE id = ?', [(int)$id]);
    audit('bank_account.delete', 'person', (int)$b['person_id'], ['bank_id' => (int)$id]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Pay component catalogue
// ---------------------------------------------------------------------------

function components(): void
{
    if (!payroll_can_manage()) {
        render_error(403, 'Access denied', 'You do not have permission to manage pay components.');
    }
    render('payroll/components', [
        'pageTitle' => 'Pay components',
        'breadcrumbs' => ['People' => null, 'Payroll' => '/payroll', 'Pay components' => null],
        'components' => db_all('SELECT * FROM pay_components ORDER BY comp_type DESC, sort_order, name'),
        'types' => comp_types(),
        'methods' => comp_methods(),
    ]);
}

function component_input(): array
{
    $name = in_str('name');
    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Required.';
    }
    $type = in_array(in_str('comp_type'), comp_types(), true) ? in_str('comp_type') : 'Earning';
    $method = in_array(in_str('calc_method'), comp_methods(), true) ? in_str('calc_method') : 'Fixed Amount';
    if (in_str('default_rate') !== '' && !is_numeric(in_str('default_rate'))) {
        $errors['default_rate'] = 'Enter a number.';
    }
    $bands = null;
    if ($method === 'Banded') {
        $raw = in_str('bands');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $errors['bands'] = 'Bands must be valid JSON.';
            } else {
                $bands = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            }
        }
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'comp_type' => $type,
        'name' => mb_substr($name, 0, 120),
        'calc_method' => $method,
        'default_rate' => in_str('default_rate') !== '' ? (float)in_str('default_rate') : null,
        'bands' => $bands,
        'is_taxable' => in_int('is_taxable') ? 1 : 0,
        'is_active' => in_int('is_active') !== null ? (in_int('is_active') ? 1 : 0) : 1,
    ];
}

function add_component(): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to manage pay components.', 403);
    }
    $c = component_input();
    if (db_val('SELECT id FROM pay_components WHERE name = ?', [$c['name']])) {
        json_err('A component with that name already exists.', 409, ['name' => 'Already exists.']);
    }
    db_query(
        'INSERT INTO pay_components (comp_type, name, calc_method, default_rate, bands, is_taxable, is_active) VALUES (?,?,?,?,?,?,?)',
        [$c['comp_type'], $c['name'], $c['calc_method'], $c['default_rate'], $c['bands'], $c['is_taxable'], $c['is_active']]
    );
    audit('pay_component.add', 'pay_component', db_insert_id(), ['name' => $c['name']]);
    json_ok(['component_id' => db_insert_id()]);
}

function update_component(string $id): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to manage pay components.', 403);
    }
    $comp = db_row('SELECT * FROM pay_components WHERE id = ?', [(int)$id]);
    if (!$comp) {
        json_err('That component does not exist.', 404);
    }
    $c = component_input();
    $clash = db_val('SELECT id FROM pay_components WHERE name = ? AND id <> ?', [$c['name'], (int)$id]);
    if ($clash) {
        json_err('A component with that name already exists.', 409, ['name' => 'Already exists.']);
    }
    db_query(
        'UPDATE pay_components SET comp_type = ?, name = ?, calc_method = ?, default_rate = ?, bands = ?, is_taxable = ?, is_active = ? WHERE id = ?',
        [$c['comp_type'], $c['name'], $c['calc_method'], $c['default_rate'], $c['bands'], $c['is_taxable'], $c['is_active'], (int)$id]
    );
    audit('pay_component.update', 'pay_component', (int)$id, ['name' => $c['name']]);
    json_ok();
}

function delete_component(string $id): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to manage pay components.', 403);
    }
    if (!db_val('SELECT id FROM pay_components WHERE id = ?', [(int)$id])) {
        json_err('That component does not exist.', 404);
    }
    db_query('DELETE FROM pay_components WHERE id = ?', [(int)$id]);
    audit('pay_component.delete', 'pay_component', (int)$id, []);
    json_ok();
}

// ---------------------------------------------------------------------------
// Salary structures
// ---------------------------------------------------------------------------

// A person's current structure with its lines resolved to component names, for
// the records page. Returned as JSON for the editor.
function structure_json(string $id): void
{
    $personId = (int)$id;
    person_or_403($personId, false);
    if ($personId !== payroll_my_pid() && !payroll_can_view_all()) {
        json_err('You do not have permission to see these figures.', 403);
    }
    $structure = db_row('SELECT * FROM salary_structures WHERE person_id = ? AND is_current = 1 LIMIT 1', [$personId]);
    $lines = [];
    if ($structure) {
        $lines = db_all(
            'SELECT sl.*, pc.name, pc.comp_type, pc.calc_method FROM salary_structure_lines sl
             JOIN pay_components pc ON pc.id = sl.component_id WHERE sl.structure_id = ? ORDER BY pc.comp_type DESC, pc.sort_order',
            [(int)$structure['id']]
        );
    }
    json_out(['ok' => true, 'structure' => $structure, 'lines' => $lines]);
}

// Create a new current structure for a person, superseding the previous one.
function save_structure(string $id): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to set salary structures.', 403);
    }
    $personId = (int)$id;
    if (!db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('That person does not exist.', 404);
    }
    $basic = in_str('basic_salary');
    if ($basic === '' || !is_numeric($basic)) {
        json_err('Enter the basic salary.', 422, ['basic_salary' => 'Enter a number.']);
    }
    $effective = in_str('effective_from');
    if ($effective === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
        json_err('Enter a valid effective date.', 422, ['effective_from' => 'Invalid date.']);
    }
    $lines = input()['lines'] ?? [];
    $lines = is_array($lines) ? $lines : [];

    db_query('UPDATE salary_structures SET is_current = 0 WHERE person_id = ?', [$personId]);
    db_query(
        'INSERT INTO salary_structures (person_id, effective_from, basic_salary, is_current, notes, created_by) VALUES (?,?,?,1,?,?)',
        [$personId, $effective, (float)$basic, in_str('notes') ?: null, (int)current_user()['id']]
    );
    $structureId = db_insert_id();
    foreach ($lines as $line) {
        $componentId = (int)($line['component_id'] ?? 0);
        if ($componentId <= 0 || !db_val('SELECT id FROM pay_components WHERE id = ?', [$componentId])) {
            continue;
        }
        $amount = isset($line['amount']) && $line['amount'] !== '' && is_numeric($line['amount']) ? (float)$line['amount'] : null;
        $rate = isset($line['rate']) && $line['rate'] !== '' && is_numeric($line['rate']) ? (float)$line['rate'] : null;
        db_query(
            'INSERT INTO salary_structure_lines (structure_id, component_id, amount, rate) VALUES (?,?,?,?)',
            [$structureId, $componentId, $amount, $rate]
        );
    }
    audit('salary_structure.save', 'person', $personId, ['structure_id' => $structureId, 'basic' => (float)$basic]);
    json_ok(['structure_id' => $structureId]);
}

// ---------------------------------------------------------------------------
// Compute engine
// ---------------------------------------------------------------------------

function run_statuses(): array { return ['Draft', 'Approved', 'Paid']; }
function loan_statuses(): array { return ['Pending', 'Active', 'Cleared', 'Rejected']; }

// Progressive banded tax on an amount. Each band is {upto, rate}; a null upto is
// the top band. Only the portion of the amount that falls in a band is taxed at
// that band's rate.
function payroll_banded_tax(array $bands, float $amount): float
{
    $tax = 0.0;
    $prev = 0.0;
    foreach ($bands as $b) {
        $upto = array_key_exists('upto', $b) && $b['upto'] !== null ? (float)$b['upto'] : INF;
        $rate = (float)($b['rate'] ?? 0);
        if ($amount > $prev) {
            $portion = min($amount, $upto) - $prev;
            if ($portion > 0) {
                $tax += $portion * $rate / 100;
            }
        }
        $prev = $upto;
        if ($amount <= $upto) {
            break;
        }
    }
    return $tax;
}

// Compute one person's payslip from their current salary structure and the
// component rules. In simple mode the banded statutory deductions are skipped,
// the lighter "record salaries and issue payslips" path. Returns null when the
// person has no current structure. Does not change any loan balance.
function compute_person_payslip(int $personId, string $mode): ?array
{
    $structure = db_row('SELECT * FROM salary_structures WHERE person_id = ? AND is_current = 1 LIMIT 1', [$personId]);
    if (!$structure) {
        return null;
    }
    $basic = (float)$structure['basic_salary'];
    $lines = db_all(
        'SELECT sl.amount, sl.rate, pc.comp_type, pc.name, pc.calc_method, pc.default_rate, pc.bands, pc.is_taxable
         FROM salary_structure_lines sl JOIN pay_components pc ON pc.id = sl.component_id
         WHERE sl.structure_id = ? ORDER BY pc.sort_order, pc.id',
        [(int)$structure['id']]
    );

    $earnings = [];
    $grossBase = $basic;
    $pctGross = [];
    foreach ($lines as $l) {
        if ($l['comp_type'] !== 'Earning') {
            continue;
        }
        $rate = $l['rate'] !== null ? (float)$l['rate'] : ($l['default_rate'] !== null ? (float)$l['default_rate'] : 0.0);
        if ($l['calc_method'] === 'Fixed Amount') {
            $val = $l['amount'] !== null ? (float)$l['amount'] : ($l['default_rate'] !== null ? (float)$l['default_rate'] : 0.0);
            $earnings[] = ['name' => $l['name'], 'amount' => $val, 'taxable' => (int)$l['is_taxable']];
            $grossBase += $val;
        } elseif ($l['calc_method'] === 'Percentage of Basic') {
            $val = $basic * $rate / 100;
            $earnings[] = ['name' => $l['name'], 'amount' => $val, 'taxable' => (int)$l['is_taxable']];
            $grossBase += $val;
        } elseif ($l['calc_method'] === 'Percentage of Gross') {
            $pctGross[] = ['name' => $l['name'], 'rate' => $rate, 'taxable' => (int)$l['is_taxable']];
        }
    }
    // Percentage-of-gross earnings apply to the base gross computed above.
    foreach ($pctGross as $pe) {
        $earnings[] = ['name' => $pe['name'], 'amount' => $grossBase * $pe['rate'] / 100, 'taxable' => $pe['taxable']];
    }

    $gross = $basic;
    $taxableGross = $basic;
    foreach ($earnings as $e) {
        $gross += $e['amount'];
        if ($e['taxable']) {
            $taxableGross += $e['amount'];
        }
    }

    $deductions = [];
    foreach ($lines as $l) {
        if ($l['comp_type'] !== 'Deduction') {
            continue;
        }
        $rate = $l['rate'] !== null ? (float)$l['rate'] : ($l['default_rate'] !== null ? (float)$l['default_rate'] : 0.0);
        $val = 0.0;
        if ($l['calc_method'] === 'Fixed Amount') {
            $val = $l['amount'] !== null ? (float)$l['amount'] : ($l['default_rate'] !== null ? (float)$l['default_rate'] : 0.0);
        } elseif ($l['calc_method'] === 'Percentage of Basic') {
            $val = $basic * $rate / 100;
        } elseif ($l['calc_method'] === 'Percentage of Gross') {
            $val = $gross * $rate / 100;
        } elseif ($l['calc_method'] === 'Banded') {
            if ($mode !== 'full') {
                continue; // statutory tax only in full mode
            }
            $bands = $l['bands'] ? json_decode($l['bands'], true) : [];
            $val = is_array($bands) ? payroll_banded_tax($bands, $taxableGross) : 0.0;
        }
        $deductions[] = ['name' => $l['name'], 'amount' => round($val, 2)];
    }

    // Active loans add a recurring deduction until cleared.
    foreach (db_all("SELECT id, installment, outstanding_balance, reason FROM staff_loans WHERE person_id = ? AND status = 'Active' AND outstanding_balance > 0", [$personId]) as $loan) {
        $ded = min((float)$loan['installment'], (float)$loan['outstanding_balance']);
        if ($ded > 0) {
            $deductions[] = ['name' => 'Loan Repayment' . ($loan['reason'] ? ' (' . $loan['reason'] . ')' : ''), 'amount' => round($ded, 2), 'loan_id' => (int)$loan['id']];
        }
    }

    foreach ($earnings as &$e) {
        $e['amount'] = round($e['amount'], 2);
    }
    unset($e);
    $totalDed = 0.0;
    foreach ($deductions as $d) {
        $totalDed += $d['amount'];
    }
    return [
        'basic' => round($basic, 2),
        'gross' => round($gross, 2),
        'total_deductions' => round($totalDed, 2),
        'net' => round($gross - $totalDed, 2),
        'breakdown' => ['earnings' => $earnings, 'deductions' => $deductions, 'taxable_gross' => round($taxableGross, 2)],
    ];
}

// ---------------------------------------------------------------------------
// Payroll runs
// ---------------------------------------------------------------------------

function runs(): void
{
    if (!payroll_can_manage() && !payroll_can_approve()) {
        render_error(403, 'Access denied', 'You do not have permission to see payroll runs.');
    }
    render('payroll/runs', [
        'pageTitle' => 'Payroll runs',
        'breadcrumbs' => ['People' => null, 'Payroll' => '/payroll', 'Runs' => null],
        'runs' => db_all(
            "SELECT r.*, (SELECT COUNT(*) FROM payslips ps WHERE ps.run_id = r.id) AS payslip_count,
                    (SELECT COALESCE(SUM(net_pay),0) FROM payslips ps WHERE ps.run_id = r.id) AS net_total
             FROM payroll_runs r ORDER BY r.period DESC, r.id DESC"
        ),
        'canManage' => payroll_can_manage(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function run(string $id): void
{
    if (!payroll_can_manage() && !payroll_can_approve()) {
        render_error(403, 'Access denied', 'You do not have permission to see this run.');
    }
    $runId = (int)$id;
    $r = db_row('SELECT * FROM payroll_runs WHERE id = ?', [$runId]);
    if (!$r) {
        render_error(404, 'Not found', 'That run does not exist.');
    }
    render('payroll/run', [
        'pageTitle' => 'Payroll ' . $r['period'],
        'breadcrumbs' => ['People' => null, 'Payroll' => '/payroll', 'Runs' => '/payroll/runs', $r['period'] => null],
        'run' => $r,
        'payslips' => db_all(
            "SELECT ps.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', ps.person_id)) AS person_name
             FROM payslips ps JOIN people p ON p.id = ps.person_id WHERE ps.run_id = ? ORDER BY p.first_name",
            [$runId]
        ),
        'canManage' => payroll_can_manage(),
        'canApprove' => payroll_can_approve(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function create_run(): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to create payroll runs.', 403);
    }
    $period = in_str('period');
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        json_err('Enter the period as YYYY-MM.', 422, ['period' => 'Use YYYY-MM.']);
    }
    if (db_val('SELECT id FROM payroll_runs WHERE period = ?', [$period])) {
        json_err('A run already exists for that period.', 409, ['period' => 'Already exists.']);
    }
    db_query(
        'INSERT INTO payroll_runs (period, label, status, created_by) VALUES (?,?,?,?)',
        [$period, in_str('label') ?: null, 'Draft', (int)current_user()['id']]
    );
    $runId = db_insert_id();
    audit('payroll_run.create', 'payroll_run', $runId, ['period' => $period]);
    json_ok(['run_id' => $runId]);
}

// Compute (or recompute) the draft register for every active employee with a
// current salary structure.
function compute_run(string $id): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to compute payroll.', 403);
    }
    $runId = (int)$id;
    $r = db_row('SELECT * FROM payroll_runs WHERE id = ?', [$runId]);
    if (!$r) {
        json_err('That run does not exist.', 404);
    }
    if ($r['status'] !== 'Draft') {
        json_err('Only a draft run can be computed.', 409);
    }
    $mode = setting('payroll_statutory_mode', 'simple');
    db_query('DELETE FROM payslips WHERE run_id = ?', [$runId]);
    $count = 0;
    foreach (db_all("SELECT id FROM people WHERE employment_status = 'Active'") as $p) {
        $slip = compute_person_payslip((int)$p['id'], $mode);
        if ($slip === null) {
            continue;
        }
        db_query(
            'INSERT INTO payslips (run_id, person_id, basic, gross, total_deductions, net_pay, breakdown) VALUES (?,?,?,?,?,?,?)',
            [$runId, (int)$p['id'], $slip['basic'], $slip['gross'], $slip['total_deductions'], $slip['net'], json_encode($slip['breakdown'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
        $count++;
    }
    audit('payroll_run.compute', 'payroll_run', $runId, ['payslips' => $count, 'mode' => $mode]);
    json_ok(['payslips' => $count]);
}

function approve_run(string $id): void
{
    if (!payroll_can_approve()) {
        json_err('You do not have permission to approve payroll.', 403);
    }
    $runId = (int)$id;
    $r = db_row('SELECT * FROM payroll_runs WHERE id = ?', [$runId]);
    if (!$r) {
        json_err('That run does not exist.', 404);
    }
    if ($r['status'] !== 'Draft') {
        json_err('Only a draft run can be approved.', 409);
    }
    if (!db_val('SELECT COUNT(*) FROM payslips WHERE run_id = ?', [$runId])) {
        json_err('Compute the register before approving.', 422);
    }
    db_query("UPDATE payroll_runs SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ?", [(int)current_user()['id'], $runId]);

    // Apply loan repayments once, at approval, reducing outstanding balances.
    foreach (db_all('SELECT person_id, breakdown FROM payslips WHERE run_id = ?', [$runId]) as $ps) {
        $breakdown = $ps['breakdown'] ? json_decode($ps['breakdown'], true) : [];
        foreach ($breakdown['deductions'] ?? [] as $ded) {
            if (empty($ded['loan_id'])) {
                continue;
            }
            $loan = db_row('SELECT * FROM staff_loans WHERE id = ?', [(int)$ded['loan_id']]);
            if (!$loan || $loan['status'] !== 'Active') {
                continue;
            }
            $newBalance = max(0, (float)$loan['outstanding_balance'] - (float)$ded['amount']);
            $newStatus = $newBalance <= 0 ? 'Cleared' : 'Active';
            db_query('UPDATE staff_loans SET outstanding_balance = ?, status = ? WHERE id = ?', [$newBalance, $newStatus, (int)$loan['id']]);
        }
    }
    audit('payroll_run.approve', 'payroll_run', $runId, []);
    json_ok();
}

function pay_run(string $id): void
{
    if (!payroll_can_approve()) {
        json_err('You do not have permission to mark payroll paid.', 403);
    }
    $runId = (int)$id;
    $r = db_row('SELECT * FROM payroll_runs WHERE id = ?', [$runId]);
    if (!$r) {
        json_err('That run does not exist.', 404);
    }
    if ($r['status'] !== 'Approved') {
        json_err('Only an approved run can be marked paid.', 409);
    }
    db_query("UPDATE payroll_runs SET status = 'Paid', paid_at = NOW() WHERE id = ?", [$runId]);
    // Notify each employee that their payslip is available.
    foreach (db_all('SELECT person_id FROM payslips WHERE run_id = ?', [$runId]) as $ps) {
        notify_person((int)$ps['person_id'], 'Your payslip for ' . $r['period'] . ' is available.', '/payroll', 'payroll');
    }
    audit('payroll_run.paid', 'payroll_run', $runId, []);
    json_ok();
}

function delete_run(string $id): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to delete payroll runs.', 403);
    }
    $r = db_row('SELECT * FROM payroll_runs WHERE id = ?', [(int)$id]);
    if (!$r) {
        json_err('That run does not exist.', 404);
    }
    if ($r['status'] !== 'Draft') {
        json_err('Only a draft run can be deleted.', 409);
    }
    db_query('DELETE FROM payroll_runs WHERE id = ?', [(int)$id]);
    audit('payroll_run.delete', 'payroll_run', (int)$id, ['period' => $r['period']]);
    json_ok();
}

// ---------------------------------------------------------------------------
// Payslips
// ---------------------------------------------------------------------------

// A payslip is visible to its owner only once the run is approved or paid;
// payroll.view_all sees any payslip, including a draft register.
function payslip_row_or_403(int $payslipId): array
{
    $ps = db_row(
        "SELECT ps.*, r.period, r.status AS run_status, r.label AS run_label,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', ps.person_id)) AS person_name,
                p.job_title, p.department
         FROM payslips ps JOIN payroll_runs r ON r.id = ps.run_id JOIN people p ON p.id = ps.person_id
         WHERE ps.id = ?",
        [$payslipId]
    );
    if (!$ps) {
        render_error(404, 'Not found', 'That payslip does not exist.');
    }
    $isOwn = (int)$ps['person_id'] === payroll_my_pid();
    $allowed = payroll_can_view_all() || ($isOwn && in_array($ps['run_status'], ['Approved', 'Paid'], true));
    if (!$allowed) {
        render_error(403, 'Access denied', 'This payslip is not available to you yet.');
    }
    return $ps;
}

function payslip(string $id): void
{
    $ps = payslip_row_or_403((int)$id);
    render('payroll/payslip', [
        'pageTitle' => 'Payslip ' . $ps['period'],
        'payslip' => $ps,
        'breakdown' => $ps['breakdown'] ? json_decode($ps['breakdown'], true) : ['earnings' => [], 'deductions' => []],
        'profile' => company_profile(),
        'currency' => setting('currency', 'MWK'),
    ], false);
}

function email_payslip(string $id): void
{
    if (!payroll_can_view_all()) {
        json_err('You do not have permission to send payslips.', 403);
    }
    $ps = db_row('SELECT ps.*, r.period, r.status AS run_status FROM payslips ps JOIN payroll_runs r ON r.id = ps.run_id WHERE ps.id = ?', [(int)$id]);
    if (!$ps) {
        json_err('That payslip does not exist.', 404);
    }
    if (!in_array($ps['run_status'], ['Approved', 'Paid'], true)) {
        json_err('Approve the run before distributing payslips.', 409);
    }
    $curr = setting('currency', 'MWK');
    $body = '<p>Your payslip for ' . e($ps['period']) . ' is ready.</p>'
        . '<p>Gross ' . e($curr) . ' ' . number_format((float)$ps['gross'], 2)
        . ', deductions ' . e($curr) . ' ' . number_format((float)$ps['total_deductions'], 2)
        . ', net pay ' . e($curr) . ' ' . number_format((float)$ps['net_pay'], 2) . '.</p>';
    mail_person((int)$ps['person_id'], 'Payslip for ' . $ps['period'], $body);
    audit('payslip.email', 'payslip', (int)$id, []);
    json_ok();
}

// ---------------------------------------------------------------------------
// Staff loans and advances
// ---------------------------------------------------------------------------

function loans(): void
{
    if (!payroll_can_manage()) {
        render_error(403, 'Access denied', 'You do not have permission to manage loans.');
    }
    render('payroll/loans', [
        'pageTitle' => 'Staff loans',
        'breadcrumbs' => ['People' => null, 'Payroll' => '/payroll', 'Loans' => null],
        'loans' => db_all(
            "SELECT l.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', l.person_id)) AS person_name
             FROM staff_loans l JOIN people p ON p.id = l.person_id ORDER BY FIELD(l.status,'Pending','Active','Cleared','Rejected'), l.id DESC"
        ),
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'canApprove' => payroll_can_approve(),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function create_loan(): void
{
    if (!payroll_can_manage()) {
        json_err('You do not have permission to create loans.', 403);
    }
    $personId = in_int('person_id');
    if (!$personId || !db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('Choose a valid person.', 422, ['person_id' => 'Invalid person.']);
    }
    foreach (['principal', 'installment'] as $n) {
        if (in_str($n) === '' || !is_numeric(in_str($n)) || (float)in_str($n) <= 0) {
            json_err('Enter a positive number.', 422, [$n => 'Enter a positive number.']);
        }
    }
    db_query(
        'INSERT INTO staff_loans (person_id, principal, installment, outstanding_balance, reason, status, created_by) VALUES (?,?,?,?,?,?,?)',
        [$personId, (float)in_str('principal'), (float)in_str('installment'), (float)in_str('principal'), in_str('reason') ?: null, 'Pending', (int)current_user()['id']]
    );
    $loanId = db_insert_id();
    audit('staff_loan.create', 'staff_loan', $loanId, ['person_id' => $personId, 'principal' => (float)in_str('principal')]);
    json_ok(['loan_id' => $loanId]);
}

function decide_loan(string $id): void
{
    if (!payroll_can_approve()) {
        json_err('You do not have permission to approve loans.', 403);
    }
    $loan = db_row('SELECT * FROM staff_loans WHERE id = ?', [(int)$id]);
    if (!$loan) {
        json_err('That loan does not exist.', 404);
    }
    if ($loan['status'] !== 'Pending') {
        json_err('That loan has already been decided.', 409);
    }
    $decision = in_str('decision');
    if (!in_array($decision, ['Active', 'Rejected'], true)) {
        json_err('Choose approve or reject.', 422);
    }
    db_query(
        'UPDATE staff_loans SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?',
        [$decision, (int)current_user()['id'], (int)$id]
    );
    if ($decision === 'Active') {
        notify_person((int)$loan['person_id'], 'Your staff loan was approved and will be deducted from payroll.', '/payroll', 'payroll');
    }
    audit('staff_loan.decide', 'staff_loan', (int)$id, ['decision' => $decision]);
    json_ok();
}

// Payslips for the current user, for the payroll home page. Only from approved
// or paid runs.
function my_payslips(int $personId): array
{
    if ($personId <= 0) {
        return [];
    }
    return db_all(
        "SELECT ps.id, ps.gross, ps.total_deductions, ps.net_pay, r.period, r.status
         FROM payslips ps JOIN payroll_runs r ON r.id = ps.run_id
         WHERE ps.person_id = ? AND r.status IN ('Approved','Paid') ORDER BY r.period DESC",
        [$personId]
    );
}
