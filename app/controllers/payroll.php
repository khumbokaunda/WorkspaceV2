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
