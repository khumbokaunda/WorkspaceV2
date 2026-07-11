<?php
// Fleet and vehicle management. Vehicles with assignment and expiry-tracked
// service, insurance and license dates, plus a service log. Expiring items
// surface on the dashboard the way certifications do.

declare(strict_types=1);

function fleet_statuses(): array { return ['Active', 'In Service', 'Retired']; }
function fleet_log_types(): array { return ['Service', 'Repair', 'Fuel', 'Incident', 'Other']; }
function fleet_can_manage(): bool { return user_can('fleet.manage'); }

function index(): void
{
    render('fleet/index', [
        'pageTitle' => 'Fleet',
        'breadcrumbs' => ['Assets' => null, 'Fleet' => null],
        'canManage' => fleet_can_manage(),
        'statuses' => fleet_statuses(),
        'logTypes' => fleet_log_types(),
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'currency' => setting('currency', 'MWK'),
    ]);
}

function list_json(): void
{
    $rows = db_all(
        "SELECT v.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), '') AS driver
         FROM vehicles v LEFT JOIN people p ON p.id = v.assigned_to ORDER BY v.registration"
    );
    foreach ($rows as &$v) {
        foreach (['service_due' => 'service_state', 'insurance_expiry' => 'insurance_state', 'license_expiry' => 'license_state'] as $field => $state) {
            $v[$state] = expiry_status($v[$field], 30);
        }
    }
    json_out(['ok' => true, 'vehicles' => $rows]);
}

function detail_json(string $id): void
{
    $v = db_row('SELECT * FROM vehicles WHERE id = ?', [(int)$id]);
    if (!$v) {
        json_err('That vehicle does not exist.', 404);
    }
    json_out(['ok' => true, 'vehicle' => $v, 'logs' => db_all('SELECT * FROM vehicle_logs WHERE vehicle_id = ? ORDER BY log_date DESC, id DESC', [(int)$id])]);
}

function vehicle_input(): array
{
    $reg = in_str('registration');
    $errors = [];
    if ($reg === '') {
        $errors['registration'] = 'Required.';
    }
    $status = in_array(in_str('status'), fleet_statuses(), true) ? in_str('status') : 'Active';
    foreach (['service_due', 'insurance_expiry', 'license_expiry'] as $df) {
        if (in_str($df) !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str($df))) {
            $errors[$df] = 'Invalid date.';
        }
    }
    $assignee = in_int('assigned_to');
    if ($assignee && !db_val('SELECT id FROM people WHERE id = ?', [$assignee])) {
        $errors['assigned_to'] = 'Unknown person.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'registration' => mb_substr($reg, 0, 40),
        'make' => in_str('make') ?: null,
        'model' => in_str('model') ?: null,
        'year' => in_int('year') ?: null,
        'assigned_to' => $assignee ?: null,
        'status' => $status,
        'service_due' => in_str('service_due') ?: null,
        'insurance_expiry' => in_str('insurance_expiry') ?: null,
        'license_expiry' => in_str('license_expiry') ?: null,
        'notes' => in_str('notes') ?: null,
    ];
}

function create(): void
{
    if (!fleet_can_manage()) {
        json_err('You do not have permission to add vehicles.', 403);
    }
    $v = vehicle_input();
    db_query(
        'INSERT INTO vehicles (registration, make, model, year, assigned_to, status, service_due, insurance_expiry, license_expiry, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$v['registration'], $v['make'], $v['model'], $v['year'], $v['assigned_to'], $v['status'], $v['service_due'], $v['insurance_expiry'], $v['license_expiry'], $v['notes']]
    );
    $id = db_insert_id();
    audit('vehicle.create', 'vehicle', $id, ['registration' => $v['registration']]);
    json_ok(['vehicle_id' => $id]);
}

function update(string $id): void
{
    if (!fleet_can_manage()) {
        json_err('You do not have permission to edit vehicles.', 403);
    }
    if (!db_val('SELECT id FROM vehicles WHERE id = ?', [(int)$id])) {
        json_err('That vehicle does not exist.', 404);
    }
    $v = vehicle_input();
    db_query(
        'UPDATE vehicles SET registration = ?, make = ?, model = ?, year = ?, assigned_to = ?, status = ?, service_due = ?, insurance_expiry = ?, license_expiry = ?, notes = ? WHERE id = ?',
        [$v['registration'], $v['make'], $v['model'], $v['year'], $v['assigned_to'], $v['status'], $v['service_due'], $v['insurance_expiry'], $v['license_expiry'], $v['notes'], (int)$id]
    );
    audit('vehicle.update', 'vehicle', (int)$id, ['registration' => $v['registration']]);
    json_ok();
}

function destroy(string $id): void
{
    if (!fleet_can_manage()) {
        json_err('You do not have permission to delete vehicles.', 403);
    }
    $v = db_row('SELECT * FROM vehicles WHERE id = ?', [(int)$id]);
    if (!$v) {
        json_err('That vehicle does not exist.', 404);
    }
    db_query('DELETE FROM vehicles WHERE id = ?', [(int)$id]);
    audit('vehicle.delete', 'vehicle', (int)$id, ['registration' => $v['registration']]);
    json_ok();
}

function add_log(string $id): void
{
    if (!fleet_can_manage()) {
        json_err('You do not have permission to add service logs.', 403);
    }
    if (!db_val('SELECT id FROM vehicles WHERE id = ?', [(int)$id])) {
        json_err('That vehicle does not exist.', 404);
    }
    $type = in_array(in_str('log_type'), fleet_log_types(), true) ? in_str('log_type') : 'Service';
    $date = in_str('log_date');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_err('Enter a valid date.', 422, ['log_date' => 'Invalid date.']);
    }
    if (in_str('cost') !== '' && !is_numeric(in_str('cost'))) {
        json_err('Enter a number for cost.', 422, ['cost' => 'Enter a number.']);
    }
    db_query(
        'INSERT INTO vehicle_logs (vehicle_id, log_type, log_date, odometer, cost, description, created_by) VALUES (?,?,?,?,?,?,?)',
        [(int)$id, $type, $date, in_int('odometer') ?: null, in_str('cost') !== '' ? (float)in_str('cost') : null, in_str('description') ?: null, (int)current_user()['id']]
    );
    audit('vehicle_log.add', 'vehicle', (int)$id, ['type' => $type]);
    json_ok(['log_id' => db_insert_id()]);
}
