<?php
// Performance and appraisals. Periodic review cycles with goals and ratings.
// A reviewer with appraisals.manage conducts appraisals; each employee sees and
// acknowledges their own.

declare(strict_types=1);

function appraisals_can_manage(): bool { return user_can('appraisals.manage'); }
function appraisals_my_pid(): int { return (int)(current_user()['person_id'] ?? 0); }

function index(): void
{
    $pid = appraisals_my_pid();
    render('appraisals/index', [
        'pageTitle' => 'Appraisals',
        'breadcrumbs' => ['People' => null, 'Appraisals' => null],
        'canManage' => appraisals_can_manage(),
        'cycles' => db_all('SELECT c.*, (SELECT COUNT(*) FROM appraisals a WHERE a.cycle_id = c.id) AS appraisal_count FROM review_cycles c ORDER BY c.start_date DESC, c.id DESC'),
        'myAppraisals' => db_all(
            'SELECT a.*, c.name AS cycle_name FROM appraisals a JOIN review_cycles c ON c.id = a.cycle_id WHERE a.person_id = ? ORDER BY a.created_at DESC',
            [$pid]
        ),
        'allAppraisals' => appraisals_can_manage()
            ? db_all(
                "SELECT a.*, c.name AS cycle_name, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', a.person_id)) AS person_name
                 FROM appraisals a JOIN review_cycles c ON c.id = a.cycle_id JOIN people p ON p.id = a.person_id
                 ORDER BY a.created_at DESC",
                []
              )
            : [],
        'people' => appraisals_can_manage() ? db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name") : [],
    ]);
}

function save_cycle(): void
{
    if (!appraisals_can_manage()) {
        json_err('You do not have permission to manage cycles.', 403);
    }
    $name = in_str('name');
    if ($name === '') {
        json_err('A cycle name is required.', 422, ['name' => 'Required.']);
    }
    $status = in_array(in_str('status'), ['Open', 'Closed'], true) ? in_str('status') : 'Open';
    $id = in_int('id');
    if ($id && db_val('SELECT id FROM review_cycles WHERE id = ?', [$id])) {
        db_query('UPDATE review_cycles SET name = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?',
            [mb_substr($name, 0, 160), in_str('start_date') ?: null, in_str('end_date') ?: null, $status, $id]);
        audit('review_cycle.update', 'review_cycle', $id, []);
        json_ok(['cycle_id' => $id]);
    }
    db_query('INSERT INTO review_cycles (name, start_date, end_date, status) VALUES (?,?,?,?)',
        [mb_substr($name, 0, 160), in_str('start_date') ?: null, in_str('end_date') ?: null, $status]);
    $newId = db_insert_id();
    audit('review_cycle.create', 'review_cycle', $newId, ['name' => $name]);
    json_ok(['cycle_id' => $newId]);
}

function create_appraisal(): void
{
    if (!appraisals_can_manage()) {
        json_err('You do not have permission to create appraisals.', 403);
    }
    $cycleId = in_int('cycle_id');
    $personId = in_int('person_id');
    if (!$cycleId || !db_val('SELECT id FROM review_cycles WHERE id = ?', [$cycleId])) {
        json_err('Choose a valid cycle.', 422, ['cycle_id' => 'Invalid cycle.']);
    }
    if (!$personId || !db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
        json_err('Choose a valid person.', 422, ['person_id' => 'Invalid person.']);
    }
    if (db_val('SELECT id FROM appraisals WHERE cycle_id = ? AND person_id = ?', [$cycleId, $personId])) {
        json_err('That person already has an appraisal in this cycle.', 409);
    }
    db_query('INSERT INTO appraisals (cycle_id, person_id, reviewer_id, status) VALUES (?,?,?,?)',
        [$cycleId, $personId, (int)current_user()['id'], 'Draft']);
    $id = db_insert_id();
    audit('appraisal.create', 'appraisal', $id, ['cycle_id' => $cycleId, 'person_id' => $personId]);
    json_ok(['appraisal_id' => $id]);
}

function appraisal_or_403(int $id, bool $needManage = false): array
{
    $a = db_row('SELECT * FROM appraisals WHERE id = ?', [$id]);
    if (!$a) {
        json_err('That appraisal does not exist.', 404);
    }
    $isOwn = (int)$a['person_id'] === appraisals_my_pid();
    if ($needManage) {
        if (!appraisals_can_manage()) {
            json_err('You do not have permission to change this appraisal.', 403);
        }
    } elseif (!appraisals_can_manage() && !$isOwn) {
        json_err('You may only view your own appraisals.', 403);
    }
    return $a;
}

function show(string $id): void
{
    $appraisalId = (int)$id;
    $a = db_row(
        "SELECT a.*, c.name AS cycle_name, c.status AS cycle_status,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))),''), CONCAT('#', a.person_id)) AS person_name,
                p.job_title,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(rp.first_name,''),' ',COALESCE(rp.last_name,''))),''), ru.username) AS reviewer_name
         FROM appraisals a JOIN review_cycles c ON c.id = a.cycle_id JOIN people p ON p.id = a.person_id
         LEFT JOIN users ru ON ru.id = a.reviewer_id LEFT JOIN people rp ON rp.id = ru.person_id
         WHERE a.id = ?",
        [$appraisalId]
    );
    if (!$a) {
        render_error(404, 'Not found', 'That appraisal does not exist.');
    }
    if (!appraisals_can_manage() && (int)$a['person_id'] !== appraisals_my_pid()) {
        render_error(403, 'Access denied', 'You may only view your own appraisals.');
    }
    render('appraisals/show', [
        'pageTitle' => $a['person_name'] . ' appraisal',
        'breadcrumbs' => ['People' => null, 'Appraisals' => '/appraisals', 'Appraisal' => null],
        'appraisal' => $a,
        'goals' => db_all('SELECT * FROM appraisal_goals WHERE appraisal_id = ? ORDER BY sort_order, id', [$appraisalId]),
        'canManage' => appraisals_can_manage(),
        'isOwn' => (int)$a['person_id'] === appraisals_my_pid(),
    ]);
}

function update_appraisal(string $id): void
{
    appraisal_or_403((int)$id, true);
    $rating = in_str('overall_rating');
    if ($rating !== '' && (!is_numeric($rating) || (float)$rating < 0 || (float)$rating > 5)) {
        json_err('Overall rating must be between 0 and 5.', 422, ['overall_rating' => '0 to 5.']);
    }
    db_query('UPDATE appraisals SET overall_rating = ?, summary = ? WHERE id = ?',
        [$rating !== '' ? (float)$rating : null, in_str('summary') ?: null, (int)$id]);
    audit('appraisal.update', 'appraisal', (int)$id, []);
    json_ok();
}

function submit_appraisal(string $id): void
{
    $a = appraisal_or_403((int)$id, true);
    if ($a['status'] !== 'Draft') {
        json_err('Only a draft appraisal can be submitted.', 409);
    }
    db_query("UPDATE appraisals SET status = 'Submitted' WHERE id = ?", [(int)$id]);
    notify_person((int)$a['person_id'], 'Your performance appraisal is ready to review.', '/appraisals/' . (int)$id, 'appraisals');
    audit('appraisal.submit', 'appraisal', (int)$id, []);
    json_ok();
}

function acknowledge_appraisal(string $id): void
{
    $a = db_row('SELECT * FROM appraisals WHERE id = ?', [(int)$id]);
    if (!$a) {
        json_err('That appraisal does not exist.', 404);
    }
    if ((int)$a['person_id'] !== appraisals_my_pid()) {
        json_err('You may only acknowledge your own appraisal.', 403);
    }
    if ($a['status'] !== 'Submitted') {
        json_err('This appraisal is not awaiting your acknowledgement.', 409);
    }
    db_query("UPDATE appraisals SET status = 'Acknowledged', acknowledged_at = NOW() WHERE id = ?", [(int)$id]);
    audit('appraisal.acknowledge', 'appraisal', (int)$id, []);
    json_ok();
}

function add_goal(string $id): void
{
    appraisal_or_403((int)$id, true);
    $goal = in_str('goal');
    if ($goal === '') {
        json_err('A goal is required.', 422, ['goal' => 'Required.']);
    }
    $rating = in_str('rating');
    if ($rating !== '' && (!is_numeric($rating) || (float)$rating < 0 || (float)$rating > 5)) {
        json_err('Rating must be between 0 and 5.', 422, ['rating' => '0 to 5.']);
    }
    db_query('INSERT INTO appraisal_goals (appraisal_id, goal, rating, comments) VALUES (?,?,?,?)',
        [(int)$id, mb_substr($goal, 0, 500), $rating !== '' ? (float)$rating : null, in_str('comments') ?: null]);
    audit('appraisal_goal.add', 'appraisal', (int)$id, []);
    json_ok(['goal_id' => db_insert_id()]);
}

function delete_goal(string $id): void
{
    $goal = db_row('SELECT * FROM appraisal_goals WHERE id = ?', [(int)$id]);
    if (!$goal) {
        json_err('That goal does not exist.', 404);
    }
    appraisal_or_403((int)$goal['appraisal_id'], true);
    db_query('DELETE FROM appraisal_goals WHERE id = ?', [(int)$id]);
    audit('appraisal_goal.delete', 'appraisal', (int)$goal['appraisal_id'], []);
    json_ok();
}
