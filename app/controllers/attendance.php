<?php
// Attendance: check in and out with mode, automatic late flag from the
// configurable threshold (evaluated in the fixed application timezone),
// personal monthly heatmap, manager live board and timesheet, corrections.

declare(strict_types=1);

function attendance_person_id(): int
{
    $user = current_user();
    if (empty($user['person_id'])) {
        json_err('Your account is not linked to a person record, so attendance cannot be recorded.', 409);
    }
    return (int)$user['person_id'];
}

function index(): void
{
    $user = current_user();
    $personId = $user['person_id'] !== null ? (int)$user['person_id'] : null;
    render('attendance/index', [
        'pageTitle' => 'Attendance',
        'breadcrumbs' => ['Work' => null, 'Attendance' => null],
        'today' => $personId ? db_row('SELECT * FROM attendance WHERE person_id = ? AND work_date = CURDATE()', [$personId]) : null,
        'canSeeTeam' => user_can('attendance.view_all'),
        'canCorrect' => user_can('attendance.correct'),
        'lateThreshold' => setting('late_threshold', '08:30'),
        'personId' => $personId,
    ]);
}

function check_in(): void
{
    $personId = attendance_person_id();
    $mode = in_str('mode') === 'Remote' ? 'Remote' : 'On-site';
    $today = date('Y-m-d');

    if (db_row('SELECT id FROM attendance WHERE person_id = ? AND work_date = ?', [$personId, $today])) {
        json_err('You have already checked in today.', 409);
    }
    // The late flag compares wall-clock time in the application timezone,
    // which bootstrap pins for both PHP and the database session.
    $now = date('H:i:s');
    $threshold = setting('late_threshold', '08:30') . ':00';
    $state = $now > $threshold ? 'Late' : 'Present';

    db_query(
        'INSERT INTO attendance (person_id, work_date, check_in, mode, state) VALUES (?,?,?,?,?)',
        [$personId, $today, $now, $mode, $state]
    );
    audit('attendance.check_in', 'attendance', db_insert_id(), ['mode' => $mode, 'state' => $state, 'time' => $now]);
    device_record('check_in', (int)(current_user()['id'] ?? 0) ?: null);
    json_ok(['state' => $state, 'check_in' => substr($now, 0, 5), 'mode' => $mode]);
}

function check_out(): void
{
    $personId = attendance_person_id();
    $row = db_row('SELECT * FROM attendance WHERE person_id = ? AND work_date = CURDATE()', [$personId]);
    if (!$row || $row['check_in'] === null) {
        json_err('You have not checked in today.', 409);
    }
    if ($row['check_out'] !== null) {
        json_err('You have already checked out today.', 409);
    }
    $now = date('H:i:s');
    db_query('UPDATE attendance SET check_out = ? WHERE id = ?', [$now, (int)$row['id']]);
    audit('attendance.check_out', 'attendance', (int)$row['id'], ['time' => $now]);
    device_record('check_out', (int)(current_user()['id'] ?? 0) ?: null);
    json_ok(['check_out' => substr($now, 0, 5)]);
}

// Personal month for the heatmap: attendance states plus approved leave days.
function me_json(): void
{
    $personId = attendance_person_id();
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));

    $days = [];
    foreach (db_all(
        'SELECT work_date, check_in, check_out, mode, state FROM attendance
         WHERE person_id = ? AND work_date BETWEEN ? AND ?',
        [$personId, $first, $last]
    ) as $r) {
        $days[$r['work_date']] = [
            'state' => $r['state'],
            'in' => $r['check_in'] ? substr($r['check_in'], 0, 5) : null,
            'out' => $r['check_out'] ? substr($r['check_out'], 0, 5) : null,
            'mode' => $r['mode'],
        ];
    }
    foreach (db_all(
        "SELECT start_date, end_date FROM leave_requests
         WHERE person_id = ? AND status = 'Approved' AND start_date <= ? AND end_date >= ?",
        [$personId, $last, $first]
    ) as $lr) {
        $d = max($lr['start_date'], $first);
        $end = min($lr['end_date'], $last);
        while ($d <= $end) {
            if (!isset($days[$d])) {
                $days[$d] = ['state' => 'Leave', 'in' => null, 'out' => null, 'mode' => null];
            }
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }
    }
    json_out(['ok' => true, 'month' => $month, 'days' => $days]);
}

// Manager data: today's live board and a date-range timesheet.
function team_json(): void
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-d');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }

    $board = db_all(
        "SELECT p.id AS person_id, p.first_name, p.last_name, p.department,
                a.id AS attendance_id, a.check_in, a.check_out, a.mode, a.state,
                (SELECT COUNT(*) FROM leave_requests lr
                 WHERE lr.person_id = p.id AND lr.status = 'Approved'
                   AND lr.start_date <= CURDATE() AND lr.end_date >= CURDATE()) AS on_leave
         FROM people p
         LEFT JOIN attendance a ON a.person_id = p.id AND a.work_date = CURDATE()
         WHERE p.employment_status = 'Active'
         ORDER BY p.first_name"
    );

    $sheet = db_all(
        "SELECT a.id, a.work_date, a.check_in, a.check_out, a.mode, a.state, a.note,
                p.first_name, p.last_name, p.department
         FROM attendance a JOIN people p ON p.id = a.person_id
         WHERE a.work_date BETWEEN ? AND ?
         ORDER BY a.work_date DESC, p.first_name",
        [$from, $to]
    );
    json_out(['ok' => true, 'board' => $board, 'sheet' => $sheet, 'from' => $from, 'to' => $to]);
}

// Permissioned correction of a record, fully audited.
function correct(string $id): void
{
    $rowId = (int)$id;
    $row = db_row('SELECT * FROM attendance WHERE id = ?', [$rowId]);
    if (!$row) {
        json_err('That attendance record does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, [
        'check_in' => 'time',
        'check_out' => 'time',
        'state' => 'in:Present;Late;Absent',
        'mode' => 'in:On-site;Remote',
        'note' => 'max:255',
    ]);
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $checkIn  = in_str('check_in') !== '' ? in_str('check_in') : null;
    $checkOut = in_str('check_out') !== '' ? in_str('check_out') : null;
    if ($checkIn && $checkOut && $checkOut < $checkIn) {
        json_err('Check out cannot be before check in.', 422, ['check_out' => 'Before check in.']);
    }
    db_query(
        'UPDATE attendance SET check_in = ?, check_out = ?, state = ?, mode = ?, note = ? WHERE id = ?',
        [
            $checkIn, $checkOut,
            in_str('state') ?: $row['state'],
            in_str('mode') ?: $row['mode'],
            in_str('note') ?: null,
            $rowId,
        ]
    );
    audit('attendance.correct', 'attendance', $rowId, [
        'was' => ['in' => $row['check_in'], 'out' => $row['check_out'], 'state' => $row['state']],
        'now' => ['in' => $checkIn, 'out' => $checkOut, 'state' => in_str('state') ?: $row['state']],
    ]);
    json_ok();
}
