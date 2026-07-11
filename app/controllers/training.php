<?php
// Training and development. Courses and certifications in progress, linked to
// the certifications module by code. A person records and sees their own;
// training.manage records and sees anyone's.

declare(strict_types=1);

function training_statuses(): array { return ['Planned', 'In Progress', 'Completed', 'Cancelled']; }
function training_can_manage(): bool { return user_can('training.manage'); }
function training_my_pid(): int { return (int)(current_user()['person_id'] ?? 0); }

function index(): void
{
    $pid = training_my_pid();
    render('training/index', [
        'pageTitle' => 'Training',
        'breadcrumbs' => ['People' => null, 'Training' => null],
        'canManage' => training_can_manage(),
        'myPid' => $pid,
        'statuses' => training_statuses(),
        'people' => training_can_manage() ? db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name") : [],
        'currency' => setting('currency', 'MWK'),
    ]);
}

function list_json(): void
{
    if (training_can_manage()) {
        $rows = db_all(
            "SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', t.person_id)) AS person_name
             FROM training_records t JOIN people p ON p.id = t.person_id ORDER BY FIELD(t.status,'In Progress','Planned','Completed','Cancelled'), t.target_date IS NULL, t.target_date"
        );
    } else {
        $rows = db_all(
            "SELECT t.*, '' AS person_name FROM training_records t WHERE t.person_id = ? ORDER BY FIELD(t.status,'In Progress','Planned','Completed','Cancelled'), t.target_date",
            [training_my_pid()]
        );
    }
    json_out(['ok' => true, 'records' => $rows, 'can_manage' => training_can_manage()]);
}

function training_input(): array
{
    $errors = [];
    $course = in_str('course_name');
    if ($course === '') {
        $errors['course_name'] = 'Required.';
    }
    $status = in_array(in_str('status'), training_statuses(), true) ? in_str('status') : 'Planned';
    foreach (['start_date', 'target_date', 'completed_date'] as $df) {
        if (in_str($df) !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', in_str($df))) {
            $errors[$df] = 'Invalid date.';
        }
    }
    if (in_str('cost') !== '' && !is_numeric(in_str('cost'))) {
        $errors['cost'] = 'Enter a number.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'course_name' => mb_substr($course, 0, 200),
        'provider' => in_str('provider') ?: null,
        'status' => $status,
        'start_date' => in_str('start_date') ?: null,
        'target_date' => in_str('target_date') ?: null,
        'completed_date' => in_str('completed_date') ?: null,
        'linked_cert_code' => strtoupper(in_str('linked_cert_code')) ?: null,
        'cost' => in_str('cost') !== '' ? (float)in_str('cost') : null,
        'notes' => in_str('notes') ?: null,
    ];
}

// The target person: for a manager the posted person_id, otherwise the caller.
function training_target_person(): int
{
    if (training_can_manage()) {
        $pid = in_int('person_id');
        if ($pid && db_val('SELECT id FROM people WHERE id = ?', [$pid])) {
            return $pid;
        }
    }
    return training_my_pid();
}

function create(): void
{
    $personId = training_target_person();
    if ($personId <= 0) {
        json_err('Choose a valid person.', 422, ['person_id' => 'Invalid person.']);
    }
    $t = training_input();
    db_query(
        'INSERT INTO training_records (person_id, course_name, provider, status, start_date, target_date, completed_date, linked_cert_code, cost, notes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$personId, $t['course_name'], $t['provider'], $t['status'], $t['start_date'], $t['target_date'], $t['completed_date'], $t['linked_cert_code'], $t['cost'], $t['notes'], (int)current_user()['id']]
    );
    $id = db_insert_id();
    audit('training.create', 'training', $id, ['course' => $t['course_name'], 'person_id' => $personId]);
    json_ok(['record_id' => $id]);
}

function record_or_403(int $id): array
{
    $row = db_row('SELECT * FROM training_records WHERE id = ?', [$id]);
    if (!$row) {
        json_err('That record does not exist.', 404);
    }
    if (!training_can_manage() && (int)$row['person_id'] !== training_my_pid()) {
        json_err('You may only change your own training records.', 403);
    }
    return $row;
}

function update(string $id): void
{
    record_or_403((int)$id);
    $t = training_input();
    db_query(
        'UPDATE training_records SET course_name = ?, provider = ?, status = ?, start_date = ?, target_date = ?, completed_date = ?, linked_cert_code = ?, cost = ?, notes = ? WHERE id = ?',
        [$t['course_name'], $t['provider'], $t['status'], $t['start_date'], $t['target_date'], $t['completed_date'], $t['linked_cert_code'], $t['cost'], $t['notes'], (int)$id]
    );
    audit('training.update', 'training', (int)$id, []);
    json_ok();
}

function destroy(string $id): void
{
    record_or_403((int)$id);
    db_query('DELETE FROM training_records WHERE id = ?', [(int)$id]);
    audit('training.delete', 'training', (int)$id, []);
    json_ok();
}
