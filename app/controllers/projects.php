<?php
// Projects: containers for tasks with status and a lead.

declare(strict_types=1);

function index(): void
{
    $projects = db_all(
        "SELECT pr.*, p.first_name AS lead_first, p.last_name AS lead_last,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pr.id) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pr.id AND t.status = 'Done') AS done_count
         FROM projects pr LEFT JOIN people p ON p.id = pr.lead_id
         ORDER BY FIELD(pr.status, 'Active', 'On Hold', 'Completed', 'Archived'), pr.name"
    );
    render('projects/index', [
        'pageTitle' => 'Projects and Tasks',
        'breadcrumbs' => ['Work' => null, 'Projects and Tasks' => null],
        'projects' => $projects,
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'canManage' => user_can('projects.manage'),
        'canCreateTask' => user_can('tasks.create'),
        'canComment' => user_can('tasks.comment'),
    ]);
}

function show(string $id): void
{
    $project = db_row(
        'SELECT pr.*, p.first_name AS lead_first, p.last_name AS lead_last
         FROM projects pr LEFT JOIN people p ON p.id = pr.lead_id WHERE pr.id = ?',
        [(int)$id]
    );
    if (!$project) {
        render_error(404, 'Not found', 'That project does not exist.');
    }
    render('projects/show', [
        'pageTitle' => $project['name'],
        'breadcrumbs' => ['Work' => null, 'Projects and Tasks' => '/projects', $project['name'] => null],
        'project' => $project,
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'canManage' => user_can('projects.manage'),
        'canCreateTask' => user_can('tasks.create'),
        'canComment' => user_can('tasks.comment'),
    ]);
}

function projects_rules(): array
{
    return [
        'name' => 'required|max:160',
        'description' => 'max:2000',
        'status' => 'in:Active;On Hold;Completed;Archived',
    ];
}

function create(): void
{
    $in = input();
    $errors = validate($in, projects_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    db_query(
        'INSERT INTO projects (name, description, status, lead_id) VALUES (?,?,?,?)',
        [in_str('name'), in_str('description') ?: null, in_str('status') ?: 'Active', in_int('lead_id') ?: null]
    );
    $projectId = db_insert_id();
    audit('project.create', 'project', $projectId, ['name' => in_str('name')]);
    json_ok(['project_id' => $projectId]);
}

function update(string $id): void
{
    $projectId = (int)$id;
    $project = db_row('SELECT * FROM projects WHERE id = ?', [$projectId]);
    if (!$project) {
        json_err('That project does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, projects_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    db_query(
        'UPDATE projects SET name = ?, description = ?, status = ?, lead_id = ? WHERE id = ?',
        [in_str('name'), in_str('description') ?: null, in_str('status') ?: $project['status'], in_int('lead_id') ?: null, $projectId]
    );
    audit('project.update', 'project', $projectId, ['name' => in_str('name'), 'status' => in_str('status')]);
    json_ok();
}
