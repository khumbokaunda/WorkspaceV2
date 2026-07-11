<?php
// Timesheets and billable hours. Staff log hours against projects and tasks;
// managers see everyone's and a utilisation summary. A person logs and edits
// only their own entries.

declare(strict_types=1);

function ts_can_view_all(): bool { return user_can('timesheets.view_all'); }
function ts_my_pid(): int { return (int)(current_user()['person_id'] ?? 0); }

// The Monday-to-Sunday week containing a date, as [start, end] Y-m-d strings.
function ts_week_bounds(string $date): array
{
    $d = new DateTimeImmutable($date);
    $dow = (int)$d->format('N'); // 1 Mon .. 7 Sun
    $start = $d->modify('-' . ($dow - 1) . ' days');
    $end = $start->modify('+6 days');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function index(): void
{
    $pid = ts_my_pid();
    $ref = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['week'] ?? '')) ? $_GET['week'] : date('Y-m-d');
    [$weekStart, $weekEnd] = ts_week_bounds($ref);

    $utilisation = [];
    if (ts_can_view_all()) {
        $utilisation = db_all(
            "SELECT p.id, p.first_name, p.last_name,
                    COALESCE(SUM(t.hours),0) AS total_hours,
                    COALESCE(SUM(CASE WHEN t.is_billable = 1 THEN t.hours ELSE 0 END),0) AS billable_hours
             FROM people p
             LEFT JOIN timesheet_entries t ON t.person_id = p.id AND t.work_date BETWEEN ? AND ?
             WHERE p.employment_status = 'Active'
             GROUP BY p.id ORDER BY total_hours DESC, p.first_name",
            [$weekStart, $weekEnd]
        );
    }

    render('timesheets/index', [
        'pageTitle' => 'Timesheets',
        'breadcrumbs' => ['Work' => null, 'Timesheets' => null],
        'canLog' => user_can('timesheets.log'),
        'canViewAll' => ts_can_view_all(),
        'myPid' => $pid,
        'weekStart' => $weekStart,
        'weekEnd' => $weekEnd,
        'projects' => db_all("SELECT id, name FROM projects WHERE status = 'Active' ORDER BY name"),
        'utilisation' => $utilisation,
    ]);
}

function list_json(): void
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01');
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-t');
    // A person sees their own entries; view_all may request anyone's.
    $personId = ts_my_pid();
    if (ts_can_view_all() && isset($_GET['person_id']) && ctype_digit((string)$_GET['person_id'])) {
        $personId = (int)$_GET['person_id'];
    }
    $rows = db_all(
        'SELECT t.*, pr.name AS project_name, tk.title AS task_title
         FROM timesheet_entries t
         LEFT JOIN projects pr ON pr.id = t.project_id
         LEFT JOIN tasks tk ON tk.id = t.task_id
         WHERE t.person_id = ? AND t.work_date BETWEEN ? AND ?
         ORDER BY t.work_date DESC, t.id DESC',
        [$personId, $from, $to]
    );
    json_out(['ok' => true, 'entries' => $rows]);
}

function entry_input(): array
{
    $errors = [];
    $date = in_str('work_date');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors['work_date'] = 'Invalid date.';
    }
    $hours = in_str('hours');
    if ($hours === '' || !is_numeric($hours) || (float)$hours <= 0 || (float)$hours > 24) {
        $errors['hours'] = 'Enter hours between 0 and 24.';
    }
    $projectId = in_int('project_id');
    if ($projectId && !db_val('SELECT id FROM projects WHERE id = ?', [$projectId])) {
        $errors['project_id'] = 'Unknown project.';
    }
    $taskId = in_int('task_id');
    if ($taskId && !db_val('SELECT id FROM tasks WHERE id = ?', [$taskId])) {
        $errors['task_id'] = 'Unknown task.';
    }
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    return [
        'work_date' => $date,
        'hours' => (float)$hours,
        'project_id' => $projectId ?: null,
        'task_id' => $taskId ?: null,
        'is_billable' => in_int('is_billable') ? 1 : 0,
        'description' => in_str('description') ?: null,
    ];
}

function create_entry(): void
{
    $pid = ts_my_pid();
    if ($pid <= 0) {
        json_err('Your account is not linked to a person record.', 422);
    }
    $e = entry_input();
    db_query(
        'INSERT INTO timesheet_entries (person_id, project_id, task_id, work_date, hours, is_billable, description) VALUES (?,?,?,?,?,?,?)',
        [$pid, $e['project_id'], $e['task_id'], $e['work_date'], $e['hours'], $e['is_billable'], $e['description']]
    );
    $id = db_insert_id();
    audit('timesheet.create', 'timesheet', $id, ['hours' => $e['hours']]);
    json_ok(['entry_id' => $id]);
}

function entry_or_403(int $id): array
{
    $row = db_row('SELECT * FROM timesheet_entries WHERE id = ?', [$id]);
    if (!$row) {
        json_err('That entry does not exist.', 404);
    }
    // Only the owner may change an entry.
    if ((int)$row['person_id'] !== ts_my_pid()) {
        json_err('You may only change your own timesheet entries.', 403);
    }
    return $row;
}

function update_entry(string $id): void
{
    entry_or_403((int)$id);
    $e = entry_input();
    db_query(
        'UPDATE timesheet_entries SET project_id = ?, task_id = ?, work_date = ?, hours = ?, is_billable = ?, description = ? WHERE id = ?',
        [$e['project_id'], $e['task_id'], $e['work_date'], $e['hours'], $e['is_billable'], $e['description'], (int)$id]
    );
    audit('timesheet.update', 'timesheet', (int)$id, []);
    json_ok();
}

function delete_entry(string $id): void
{
    entry_or_403((int)$id);
    db_query('DELETE FROM timesheet_entries WHERE id = ?', [(int)$id]);
    audit('timesheet.delete', 'timesheet', (int)$id, []);
    json_ok();
}

// Tasks for a project, for the log form's task selector.
function project_tasks_json(string $id): void
{
    json_out(['ok' => true, 'tasks' => db_all("SELECT id, title FROM tasks WHERE project_id = ? AND status <> 'Done' ORDER BY title", [(int)$id])]);
}
