<?php
// Tasks: board, list and calendar share these endpoints. Assignment
// notifies the assignee; completion notifies the creator specifically.

declare(strict_types=1);

const TASK_STATUSES = ['To Do', 'In Progress', 'Blocked', 'Done'];

function tasks_rules(): array
{
    return [
        'title' => 'required|max:200',
        'description' => 'max:5000',
        'priority' => 'in:Low;Medium;High;Critical',
        'status' => 'in:To Do;In Progress;Blocked;Done',
        'due_date' => 'date',
    ];
}

function list_json(): void
{
    $projectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
    $sql = 'SELECT t.*, p.first_name AS asg_first, p.last_name AS asg_last,
                   pr.name AS project_name, u.username AS creator
            FROM tasks t
            LEFT JOIN people p ON p.id = t.assignee_id
            LEFT JOIN projects pr ON pr.id = t.project_id
            JOIN users u ON u.id = t.created_by';
    $params = [];
    if ($projectId !== null) {
        $sql .= ' WHERE t.project_id = ?';
        $params[] = $projectId;
    }
    $sql .= ' ORDER BY t.position, t.id';
    json_out(['ok' => true, 'tasks' => db_all($sql, $params), 'today' => date('Y-m-d')]);
}

function detail_json(string $id): void
{
    $task = db_row(
        'SELECT t.*, p.first_name AS asg_first, p.last_name AS asg_last,
                pr.name AS project_name, u.username AS creator
         FROM tasks t
         LEFT JOIN people p ON p.id = t.assignee_id
         LEFT JOIN projects pr ON pr.id = t.project_id
         JOIN users u ON u.id = t.created_by
         WHERE t.id = ?',
        [(int)$id]
    );
    if (!$task) {
        json_err('That task does not exist.', 404);
    }
    $comments = db_all(
        'SELECT c.body, c.created_at, u.username AS author
         FROM task_comments c JOIN users u ON u.id = c.author_id
         WHERE c.task_id = ? ORDER BY c.created_at',
        [(int)$id]
    );
    json_out(['ok' => true, 'task' => $task, 'comments' => $comments]);
}

function tasks_notify_assignee(int $personId, string $title, int $taskId): void
{
    notify_person($personId, 'You were assigned the task "' . $title . '".', '/projects?task=' . $taskId, 'projects');
    mail_person($personId, 'Task assigned to you', '<p>You have been assigned the task "' . e($title) . '".</p>');
}

function create(): void
{
    $in = input();
    $errors = validate($in, tasks_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $projectId = in_int('project_id');
    if ($projectId && !db_val('SELECT id FROM projects WHERE id = ?', [$projectId])) {
        json_err('That project does not exist.', 422, ['project_id' => 'Invalid project.']);
    }
    $status = in_str('status') ?: 'To Do';
    $position = (int)db_val('SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE status = ?', [$status]);
    $assigneeId = in_int('assignee_id');

    db_query(
        'INSERT INTO tasks (project_id, title, description, assignee_id, created_by, priority, status, due_date, position)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $projectId ?: null, in_str('title'), in_str('description') ?: null,
            $assigneeId ?: null, (int)current_user()['id'],
            in_str('priority') ?: 'Medium', $status, in_str('due_date') ?: null, $position,
        ]
    );
    $taskId = db_insert_id();
    audit('task.create', 'task', $taskId, ['title' => in_str('title')]);
    if ($assigneeId && $assigneeId !== (int)(current_user()['person_id'] ?? 0)) {
        tasks_notify_assignee($assigneeId, in_str('title'), $taskId);
    }
    json_ok(['task_id' => $taskId]);
}

function update(string $id): void
{
    $taskId = (int)$id;
    $task = db_row('SELECT * FROM tasks WHERE id = ?', [$taskId]);
    if (!$task) {
        json_err('That task does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, tasks_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $assigneeId = in_int('assignee_id');
    db_query(
        'UPDATE tasks SET project_id = ?, title = ?, description = ?, assignee_id = ?, priority = ?, due_date = ? WHERE id = ?',
        [
            in_int('project_id') ?: null, in_str('title'), in_str('description') ?: null,
            $assigneeId ?: null, in_str('priority') ?: $task['priority'],
            in_str('due_date') ?: null, $taskId,
        ]
    );
    audit('task.update', 'task', $taskId, ['title' => in_str('title')]);
    if ($assigneeId && $assigneeId !== (int)($task['assignee_id'] ?? 0)
        && $assigneeId !== (int)(current_user()['person_id'] ?? 0)) {
        tasks_notify_assignee($assigneeId, in_str('title'), $taskId);
    }
    json_ok();
}

// Status moves come from the board (with a position) and from the drawer.
// Moving to Done stamps completed_at and notifies the creator specifically.
function set_status(string $id): void
{
    $taskId = (int)$id;
    $task = db_row('SELECT * FROM tasks WHERE id = ?', [$taskId]);
    if (!$task) {
        json_err('That task does not exist.', 404);
    }
    $status = in_str('status');
    if (!in_array($status, TASK_STATUSES, true)) {
        json_err('Invalid status.', 422, ['status' => 'Invalid status.']);
    }
    $position = in_int('position');
    if ($position === null) {
        $position = (int)db_val('SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE status = ?', [$status]);
    }

    $becameDone = $status === 'Done' && $task['status'] !== 'Done';
    db_query(
        'UPDATE tasks SET status = ?, position = ?, completed_at = CASE WHEN ? THEN NOW() WHEN ? THEN NULL ELSE completed_at END WHERE id = ?',
        [$status, $position, $becameDone ? 1 : 0, ($status !== 'Done' && $task['status'] === 'Done') ? 1 : 0, $taskId]
    );
    audit('task.status', 'task', $taskId, ['from' => $task['status'], 'to' => $status]);

    if ($becameDone && (int)$task['created_by'] !== (int)current_user()['id']) {
        notify((int)$task['created_by'], 'The task "' . $task['title'] . '" you created is done.', '/projects?task=' . $taskId, 'projects');
    }
    json_ok();
}

function add_comment(string $id): void
{
    $taskId = (int)$id;
    $task = db_row('SELECT * FROM tasks WHERE id = ?', [$taskId]);
    if (!$task) {
        json_err('That task does not exist.', 404);
    }
    $body = in_str('body');
    if ($body === '' || mb_strlen($body) > 2000) {
        json_err('Write a comment of at most 2000 characters.', 422, ['body' => 'Required, at most 2000 characters.']);
    }
    db_query(
        'INSERT INTO task_comments (task_id, author_id, body) VALUES (?,?,?)',
        [$taskId, (int)current_user()['id'], $body]
    );
    audit('task.comment', 'task', $taskId);

    // Tell the assignee and the creator, except the commenter themselves.
    $me = current_user();
    if ($task['assignee_id'] && (int)$task['assignee_id'] !== (int)($me['person_id'] ?? 0)) {
        notify_person((int)$task['assignee_id'], user_display_name() . ' commented on "' . $task['title'] . '".', '/projects?task=' . $taskId, 'projects');
    }
    if ((int)$task['created_by'] !== (int)$me['id']) {
        notify((int)$task['created_by'], user_display_name() . ' commented on "' . $task['title'] . '".', '/projects?task=' . $taskId, 'projects');
    }
    json_ok();
}
