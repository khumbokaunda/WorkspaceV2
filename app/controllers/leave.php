<?php
// Leave: requests with live balance and team availability, an approvals
// queue with one-click decisions, balance bookkeeping, and a shared
// calendar of approved absences.

declare(strict_types=1);

function leave_person_id(): int
{
    $user = current_user();
    if (empty($user['person_id'])) {
        json_err('Your account is not linked to a person record, so leave cannot be requested.', 409);
    }
    return (int)$user['person_id'];
}

function index(): void
{
    $user = current_user();
    $personId = $user['person_id'] !== null ? (int)$user['person_id'] : null;
    $year = (int)date('Y');

    $balances = [];
    if ($personId) {
        foreach (db_all('SELECT id, name FROM leave_types ORDER BY id') as $lt) {
            $b = ensure_leave_balance($personId, (int)$lt['id'], $year);
            $balances[] = [
                'type_id' => (int)$lt['id'],
                'name' => $lt['name'],
                'allocated' => (float)$b['allocated'],
                'used' => (float)$b['used'],
                'remaining' => (float)$b['allocated'] - (float)$b['used'],
            ];
        }
    }

    render('leave/index', [
        'pageTitle' => 'Leave',
        'breadcrumbs' => ['Work' => null, 'Leave' => null],
        'balances' => $balances,
        'leaveTypes' => db_all('SELECT id, name, is_paid FROM leave_types ORDER BY id'),
        'canApprove' => user_can('leave.approve'),
        'canSeeAll' => user_can('leave.view_all'),
        'personId' => $personId,
        'pending' => user_can('leave.approve')
            ? db_all(
                "SELECT lr.*, lt.name AS type_name, p.first_name, p.last_name
                 FROM leave_requests lr
                 JOIN leave_types lt ON lt.id = lr.leave_type_id
                 JOIN people p ON p.id = lr.person_id
                 WHERE lr.status = 'Pending' ORDER BY lr.created_at ASC"
            )
            : [],
    ]);
}

function request_leave(): void
{
    $personId = leave_person_id();
    $in = input();
    $errors = validate($in, [
        'leave_type_id' => 'required|int',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'reason' => 'max:500',
    ]);
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $typeId = (int)$in['leave_type_id'];
    if (!db_val('SELECT id FROM leave_types WHERE id = ?', [$typeId])) {
        json_err('Choose a valid leave type.', 422, ['leave_type_id' => 'Invalid type.']);
    }
    $start = in_str('start_date');
    $end   = in_str('end_date');
    if ($start < date('Y-m-d')) {
        json_err('Leave cannot start in the past.', 422, ['start_date' => 'Pick today or later.']);
    }
    $days = working_days($start, $end);
    if ($days <= 0) {
        json_err('The requested range contains no working days.', 422, ['end_date' => 'No working days in range.']);
    }
    if ($days > 90) {
        json_err('That range is unreasonably long. Split it or contact an administrator.', 422, ['end_date' => 'Too long.']);
    }
    $overlap = db_val(
        "SELECT COUNT(*) FROM leave_requests
         WHERE person_id = ? AND status IN ('Pending','Approved')
           AND start_date <= ? AND end_date >= ?",
        [$personId, $end, $start]
    );
    if ((int)$overlap > 0) {
        json_err('You already have leave overlapping those dates.', 409);
    }
    $balance = ensure_leave_balance($personId, $typeId, (int)substr($start, 0, 4));
    $remaining = (float)$balance['allocated'] - (float)$balance['used'];
    $isPaid = (int)db_val('SELECT is_paid FROM leave_types WHERE id = ?', [$typeId]);
    if ($isPaid && $days > $remaining) {
        json_err('That request needs ' . $days . ' days but only ' . $remaining . ' remain.', 422, ['end_date' => 'Exceeds remaining balance.']);
    }

    db_query(
        'INSERT INTO leave_requests (person_id, leave_type_id, start_date, end_date, working_days, reason)
         VALUES (?,?,?,?,?,?)',
        [$personId, $typeId, $start, $end, $days, in_str('reason') ?: null]
    );
    $requestId = db_insert_id();
    audit('leave.request', 'leave_request', $requestId, ['start' => $start, 'end' => $end, 'days' => $days]);

    $who = user_display_name();
    notify_role('manager', $who . ' requested leave (' . $start . ' to ' . $end . ').', '/leave', 'leave');
    notify_role('admin', $who . ' requested leave (' . $start . ' to ' . $end . ').', '/leave', 'leave');
    json_ok(['request_id' => $requestId, 'working_days' => $days]);
}

function leave_load_pending(int $id): array
{
    $req = db_row(
        'SELECT lr.*, lt.name AS type_name, p.first_name, p.last_name, p.id AS pid
         FROM leave_requests lr
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         JOIN people p ON p.id = lr.person_id
         WHERE lr.id = ?',
        [$id]
    );
    if (!$req) {
        json_err('That leave request does not exist.', 404);
    }
    if ($req['status'] !== 'Pending') {
        json_err('This request has already been reviewed.', 409);
    }
    return $req;
}

function approve(string $id): void
{
    $req = leave_load_pending((int)$id);
    $me = current_user();
    if ((int)($me['person_id'] ?? 0) === (int)$req['pid']) {
        json_err('You cannot review your own leave request.', 403);
    }
    $note = in_str('review_note');

    db_query(
        "UPDATE leave_requests SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?",
        [(int)$me['id'], $note ?: null, (int)$req['id']]
    );
    $balance = ensure_leave_balance((int)$req['pid'], (int)$req['leave_type_id'], (int)substr($req['start_date'], 0, 4));
    db_query('UPDATE leave_balances SET used = used + ? WHERE id = ?', [(float)$req['working_days'], (int)$balance['id']]);

    audit('leave.approve', 'leave_request', (int)$req['id'], ['days' => $req['working_days'], 'note' => $note]);
    notify_person((int)$req['pid'], 'Your ' . $req['type_name'] . ' leave (' . $req['start_date'] . ' to ' . $req['end_date'] . ') was approved.', '/leave', 'leave');
    mail_person(
        (int)$req['pid'],
        'Leave approved',
        '<p>Your ' . e($req['type_name']) . ' leave from ' . e($req['start_date']) . ' to ' . e($req['end_date'])
        . ' has been approved.' . ($note ? ' Note: ' . e($note) : '') . '</p>'
    );
    json_ok();
}

function reject(string $id): void
{
    $req = leave_load_pending((int)$id);
    $me = current_user();
    if ((int)($me['person_id'] ?? 0) === (int)$req['pid']) {
        json_err('You cannot review your own leave request.', 403);
    }
    $note = in_str('review_note');

    db_query(
        "UPDATE leave_requests SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?",
        [(int)$me['id'], $note ?: null, (int)$req['id']]
    );
    audit('leave.reject', 'leave_request', (int)$req['id'], ['note' => $note]);
    notify_person((int)$req['pid'], 'Your ' . $req['type_name'] . ' leave (' . $req['start_date'] . ' to ' . $req['end_date'] . ') was rejected.', '/leave', 'leave');
    mail_person(
        (int)$req['pid'],
        'Leave request rejected',
        '<p>Your ' . e($req['type_name']) . ' leave from ' . e($req['start_date']) . ' to ' . e($req['end_date'])
        . ' was rejected.' . ($note ? ' Note: ' . e($note) : '') . '</p>'
    );
    json_ok();
}

// A person cancels their own pending request, or an approved one that has
// not started yet; the balance is restored in the approved case.
function cancel(string $id): void
{
    $personId = leave_person_id();
    $req = db_row('SELECT * FROM leave_requests WHERE id = ? AND person_id = ?', [(int)$id, $personId]);
    if (!$req) {
        json_err('That leave request does not exist.', 404);
    }
    if (!in_array($req['status'], ['Pending', 'Approved'], true)) {
        json_err('Only pending or approved requests can be cancelled.', 409);
    }
    if ($req['status'] === 'Approved' && $req['start_date'] <= date('Y-m-d')) {
        json_err('Leave that has already started cannot be cancelled here. Ask an administrator.', 409);
    }
    db_query("UPDATE leave_requests SET status = 'Cancelled' WHERE id = ?", [(int)$req['id']]);
    if ($req['status'] === 'Approved') {
        $balance = ensure_leave_balance($personId, (int)$req['leave_type_id'], (int)substr($req['start_date'], 0, 4));
        db_query('UPDATE leave_balances SET used = GREATEST(0, used - ?) WHERE id = ?', [(float)$req['working_days'], (int)$balance['id']]);
    }
    audit('leave.cancel', 'leave_request', (int)$req['id'], ['was' => $req['status']]);
    json_ok();
}

function balance_json(): void
{
    $personId = leave_person_id();
    $typeId = (int)($_GET['type_id'] ?? 0);
    $year = (int)($_GET['year'] ?? date('Y'));
    if (!$typeId || !db_val('SELECT id FROM leave_types WHERE id = ?', [$typeId])) {
        json_err('Choose a valid leave type.', 422);
    }
    $b = ensure_leave_balance($personId, $typeId, $year);
    json_out(['ok' => true, 'allocated' => (float)$b['allocated'], 'used' => (float)$b['used'],
        'remaining' => (float)$b['allocated'] - (float)$b['used']]);
}

// Absences for the shared team calendar: approved for everyone, plus the
// caller's own pending requests so they can see them in context.
function calendar_json(): void
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-t');
    $me = current_user();
    $items = db_all(
        "SELECT lr.id, lr.start_date, lr.end_date, lr.status, lt.name AS type_name,
                p.id AS person_id, p.first_name, p.last_name
         FROM leave_requests lr
         JOIN leave_types lt ON lt.id = lr.leave_type_id
         JOIN people p ON p.id = lr.person_id
         WHERE lr.start_date <= ? AND lr.end_date >= ?
           AND (lr.status = 'Approved' OR (lr.status = 'Pending' AND lr.person_id = ?))
         ORDER BY lr.start_date",
        [$to, $from, (int)($me['person_id'] ?? 0)]
    );
    json_out(['ok' => true, 'items' => $items, 'from' => $from, 'to' => $to]);
}

function list_json(): void
{
    $me = current_user();
    $all = user_can('leave.view_all') && ($_GET['scope'] ?? '') === 'all';
    if ($all) {
        $rows = db_all(
            'SELECT lr.*, lt.name AS type_name, p.first_name, p.last_name,
                    ru.username AS reviewer
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN people p ON p.id = lr.person_id
             LEFT JOIN users ru ON ru.id = lr.reviewed_by
             ORDER BY lr.created_at DESC LIMIT 500'
        );
    } else {
        $rows = db_all(
            'SELECT lr.*, lt.name AS type_name, p.first_name, p.last_name,
                    ru.username AS reviewer
             FROM leave_requests lr
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             JOIN people p ON p.id = lr.person_id
             LEFT JOIN users ru ON ru.id = lr.reviewed_by
             WHERE lr.person_id = ?
             ORDER BY lr.created_at DESC LIMIT 200',
            [(int)($me['person_id'] ?? 0)]
        );
    }
    json_out(['ok' => true, 'requests' => $rows]);
}
