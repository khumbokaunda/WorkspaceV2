<?php
// Command palette search: records across modules plus quick actions, all
// filtered by the caller's effective permissions and module visibility.

declare(strict_types=1);

function palette(): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $results = [];

    // Quick actions surface even on an empty query.
    $actions = [
        ['perm' => 'people.create',   'module' => 'people',     'label' => 'Add a person',      'icon' => 'fa-user-plus',      'url' => '/people?action=new',     'kind' => 'Action'],
        ['perm' => 'tasks.create',    'module' => 'projects',   'label' => 'Log a task',        'icon' => 'fa-plus',           'url' => '/projects?action=new-task', 'kind' => 'Action'],
        ['perm' => 'leave.request',   'module' => 'leave',      'label' => 'Request leave',     'icon' => 'fa-umbrella-beach', 'url' => '/leave?action=new',      'kind' => 'Action'],
        ['perm' => 'assets.assign',   'module' => 'assets',     'label' => 'Assign an asset',   'icon' => 'fa-laptop',         'url' => '/assets',                'kind' => 'Action'],
        ['perm' => 'attendance.record', 'module' => 'attendance', 'label' => 'Check in or out', 'icon' => 'fa-clock',          'url' => '/attendance',            'kind' => 'Action'],
    ];
    foreach ($actions as $a) {
        if (user_can($a['perm']) && module_visible($a['module'])
            && ($q === '' || stripos($a['label'], $q) !== false)) {
            $results[] = ['label' => $a['label'], 'icon' => $a['icon'], 'url' => $a['url'], 'kind' => $a['kind']];
        }
    }

    if ($q !== '') {
        if (user_can('people.view') && module_visible('people')) {
            foreach (db_all(
                "SELECT id, first_name, last_name, job_title FROM people
                 WHERE employment_status <> 'Terminated'
                   AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ? OR job_title LIKE ?)
                 LIMIT 5", [$like, $like, $like]
            ) as $p) {
                $results[] = [
                    'label' => $p['first_name'] . ' ' . $p['last_name'] . ($p['job_title'] ? ' (' . $p['job_title'] . ')' : ''),
                    'icon' => 'fa-user', 'url' => '/people/' . $p['id'], 'kind' => 'Person',
                ];
            }
        }
        if (user_can('projects.view') && module_visible('projects')) {
            foreach (db_all('SELECT id, title FROM tasks WHERE title LIKE ? LIMIT 5', [$like]) as $t) {
                $results[] = ['label' => $t['title'], 'icon' => 'fa-list-check', 'url' => '/projects?task=' . $t['id'], 'kind' => 'Task'];
            }
            foreach (db_all('SELECT id, name FROM projects WHERE name LIKE ? LIMIT 5', [$like]) as $p) {
                $results[] = ['label' => $p['name'], 'icon' => 'fa-diagram-project', 'url' => '/projects/' . $p['id'], 'kind' => 'Project'];
            }
        }
        if (user_can('assets.view') && module_visible('assets')) {
            foreach (db_all(
                'SELECT id, asset_tag, name FROM assets WHERE asset_tag LIKE ? OR name LIKE ? OR serial_number LIKE ? LIMIT 5',
                [$like, $like, $like]
            ) as $a) {
                $results[] = ['label' => $a['name'] . ' [' . $a['asset_tag'] . ']', 'icon' => 'fa-laptop', 'url' => '/assets?asset=' . $a['id'], 'kind' => 'Asset'];
            }
        }
        if (user_can('certifications.view') && module_visible('certifications')) {
            foreach (db_all(
                "SELECT c.id, c.name, c.code, p.first_name, p.last_name FROM certifications c
                 JOIN people p ON p.id = c.person_id
                 WHERE c.name LIKE ? OR c.code LIKE ? LIMIT 5", [$like, $like]
            ) as $c) {
                $results[] = [
                    'label' => $c['code'] . ' ' . $c['name'] . ', ' . $c['first_name'] . ' ' . $c['last_name'],
                    'icon' => 'fa-certificate', 'url' => '/certifications?cert=' . $c['id'], 'kind' => 'Certification',
                ];
            }
        }
    }

    json_out(['ok' => true, 'results' => array_slice($results, 0, 18)]);
}
