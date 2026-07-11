<?php
// People directory, profiles, onboarding and termination.

declare(strict_types=1);

function people_rules(): array
{
    return [
        'first_name' => 'required|max:80',
        'last_name'  => 'required|max:80',
        'email'      => 'required|email|max:190',
        'phone'      => 'max:40',
        'job_title'  => 'max:120',
        'department' => 'max:120',
        'start_date' => 'date',
    ];
}

function index(): void
{
    render('people/index', [
        'pageTitle' => 'Directory',
        'breadcrumbs' => ['People' => null, 'Directory' => null],
        'departments' => db_all("SELECT DISTINCT department FROM people WHERE department IS NOT NULL AND department <> '' ORDER BY department"),
        'managers' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name"),
        'accountDepartments' => user_can('admin.users') ? db_all("SELECT id, name FROM `groups` WHERE type = 'department' AND is_active = 1 ORDER BY name") : [],
        'accountAccessGroups' => user_can('admin.users') ? db_all("SELECT id, name FROM `groups` WHERE type = 'access_group' AND is_active = 1 ORDER BY sort_order, name") : [],
        'starterAssets' => user_can('assets.assign')
            ? db_all("SELECT id, asset_tag, name FROM assets WHERE status = 'Available' ORDER BY asset_tag")
            : [],
    ]);
}

function list_json(): void
{
    $people = db_all(
        "SELECT p.id, p.first_name, p.last_name, p.email, p.phone, p.job_title, p.department,
                p.employment_status, p.start_date,
                m.first_name AS mgr_first, m.last_name AS mgr_last
         FROM people p LEFT JOIN people m ON m.id = p.manager_id
         ORDER BY p.first_name, p.last_name"
    );
    json_out(['ok' => true, 'people' => $people]);
}

function show(string $id): void
{
    $personId = (int)$id;
    $person = db_row(
        'SELECT p.*, m.first_name AS mgr_first, m.last_name AS mgr_last, m.id AS mgr_id
         FROM people p LEFT JOIN people m ON m.id = p.manager_id WHERE p.id = ?',
        [$personId]
    );
    if (!$person) {
        render_error(404, 'Not found', 'That person does not exist.');
    }

    $certs = db_all('SELECT * FROM certifications WHERE person_id = ? ORDER BY expires_on IS NULL, expires_on', [$personId]);
    foreach ($certs as &$c) {
        $c['effective_status'] = cert_effective_status($c);
    }

    $me = current_user();
    $canSeeDocs = user_can('documents.view_all')
        || (user_can('documents.view') && (int)($me['person_id'] ?? 0) === $personId);

    render('people/show', [
        'pageTitle' => $person['first_name'] . ' ' . $person['last_name'],
        'breadcrumbs' => ['People' => null, 'Directory' => '/people', $person['first_name'] . ' ' . $person['last_name'] => null],
        'person' => $person,
        'certs' => $certs,
        'canSeeDocs' => $canSeeDocs,
        'documents' => $canSeeDocs
            ? db_all(
                'SELECT d.*, u.username AS uploader FROM documents d
                 JOIN users u ON u.id = d.uploaded_by
                 WHERE d.person_id = ? ORDER BY d.uploaded_at DESC', [$personId]
            )
            : [],
        'assets' => db_all(
            'SELECT a.id, a.asset_tag, a.name, a.category, aa.assigned_at
             FROM asset_assignments aa JOIN assets a ON a.id = aa.asset_id
             WHERE aa.person_id = ? AND aa.returned_at IS NULL ORDER BY aa.assigned_at DESC', [$personId]
        ),
        'activity' => db_all(
            "SELECT action, detail, created_at FROM audit_log
             WHERE entity = 'person' AND entity_id = ? ORDER BY created_at DESC LIMIT 12", [$personId]
        ),
        'managers' => db_all(
            "SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' AND id <> ? ORDER BY first_name",
            [$personId]
        ),
    ]);
}

function detail_json(string $id): void
{
    $person = db_row('SELECT * FROM people WHERE id = ?', [(int)$id]);
    if (!$person) {
        json_err('That person does not exist.', 404);
    }
    json_out(['ok' => true, 'person' => $person]);
}

// Onboarding: person record, optional login account (with a generated
// temporary password and forced first-login change), optional starter asset.
function create(): void
{
    $in = input();
    $errors = validate($in, people_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    if (db_val('SELECT id FROM people WHERE email = ?', [in_str('email')])) {
        json_err('That email address is already in the directory.', 422, ['email' => 'Already in use.']);
    }

    // Validate the optional account before touching the database, so a
    // rejected account never leaves an orphan person behind.
    $wantsAccount = !empty($in['create_account']) && user_can('admin.users');
    $accountDeptId = null;
    $accountAccessIds = [];
    if ($wantsAccount) {
        $accountDeptId = in_int('primary_department');
        if (in_str('username') === '' || !$accountDeptId) {
            json_err('A username and primary department are required to create the account.', 422, ['username' => 'Required for an account.']);
        }
        if (!db_val("SELECT id FROM `groups` WHERE id = ? AND type = 'department' AND is_active = 1", [$accountDeptId])) {
            json_err('Choose a valid primary department.', 422, ['primary_department' => 'Invalid department.']);
        }
        if (db_val('SELECT id FROM users WHERE username = ? OR email = ?', [in_str('username'), in_str('email')])) {
            json_err('That username or email already has an account.', 422, ['username' => 'Already taken.']);
        }
        $rawAccess = is_array($in['access_group_ids'] ?? null) ? array_map('intval', $in['access_group_ids']) : [];
        if ($rawAccess) {
            $place = implode(',', array_fill(0, count($rawAccess), '?'));
            $validAccess = array_map(
                fn($r) => (int)$r['id'],
                db_all("SELECT id FROM `groups` WHERE type = 'access_group' AND id IN ($place)", $rawAccess)
            );
            $accountAccessIds = array_values(array_intersect($rawAccess, $validAccess));
        }
    }

    $managerId = in_int('manager_id');
    db_query(
        'INSERT INTO people (first_name, last_name, email, phone, job_title, department, manager_id, start_date)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            in_str('first_name'), in_str('last_name'), in_str('email'),
            in_str('phone') ?: null, in_str('job_title') ?: null, in_str('department') ?: null,
            $managerId ?: null, in_str('start_date') ?: null,
        ]
    );
    $personId = db_insert_id();
    audit('person.create', 'person', $personId, ['name' => in_str('first_name') . ' ' . in_str('last_name')]);

    $tempPassword = null;
    if ($wantsAccount) {
        $tempPassword = bin2hex(random_bytes(6)) . '!A';
        db_query(
            'INSERT INTO users (username, email, password_hash, person_id, role_id, must_change_password)
             VALUES (?,?,?,?,NULL,1)',
            [in_str('username'), in_str('email'), password_hash($tempPassword, PASSWORD_BCRYPT), $personId]
        );
        $newUserId = db_insert_id();
        db_query('INSERT INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,1)', [$newUserId, $accountDeptId]);
        foreach ($accountAccessIds as $gid) {
            db_query('INSERT IGNORE INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,0)', [$newUserId, $gid]);
        }
        audit('user.create', 'user', $newUserId, [
            'username' => in_str('username'),
            'via' => 'onboarding',
            'department' => $accountDeptId,
            'access_groups' => $accountAccessIds,
        ]);
    }

    if (!empty($in['starter_asset_id']) && user_can('assets.assign')) {
        $assetId = (int)$in['starter_asset_id'];
        $asset = db_row("SELECT * FROM assets WHERE id = ? AND status = 'Available'", [$assetId]);
        if ($asset) {
            db_query(
                'INSERT INTO asset_assignments (asset_id, person_id, assigned_by) VALUES (?,?,?)',
                [$assetId, $personId, (int)current_user()['id']]
            );
            db_query("UPDATE assets SET status = 'Assigned' WHERE id = ?", [$assetId]);
            audit('asset.assign', 'asset', $assetId, ['person_id' => $personId, 'via' => 'onboarding']);
        }
    }

    json_ok(['person_id' => $personId, 'temp_password' => $tempPassword]);
}

function update(string $id): void
{
    $personId = (int)$id;
    $person = db_row('SELECT * FROM people WHERE id = ?', [$personId]);
    if (!$person) {
        json_err('That person does not exist.', 404);
    }
    $in = input();
    $errors = validate($in, people_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $existing = db_val('SELECT id FROM people WHERE email = ? AND id <> ?', [in_str('email'), $personId]);
    if ($existing) {
        json_err('That email address belongs to someone else.', 422, ['email' => 'Already in use.']);
    }
    $managerId = in_int('manager_id');
    if ($managerId === $personId) {
        json_err('A person cannot be their own manager.', 422, ['manager_id' => 'Choose someone else.']);
    }

    $changed = [];
    foreach (['first_name', 'last_name', 'email', 'phone', 'job_title', 'department', 'start_date'] as $f) {
        if (array_key_exists($f, $in) && (string)$person[$f] !== in_str($f)) {
            $changed[$f] = ['from' => $person[$f], 'to' => in_str($f)];
        }
    }
    db_query(
        'UPDATE people SET first_name = ?, last_name = ?, email = ?, phone = ?, job_title = ?, department = ?, manager_id = ?, start_date = ?
         WHERE id = ?',
        [
            in_str('first_name'), in_str('last_name'), in_str('email'),
            in_str('phone') ?: null, in_str('job_title') ?: null, in_str('department') ?: null,
            $managerId ?: null, in_str('start_date') ?: null, $personId,
        ]
    );
    audit('person.update', 'person', $personId, $changed);
    json_ok();
}

// Termination preserves history: it flips employment_status, deactivates any
// linked account, and returns outstanding assets. Nothing is deleted.
function terminate(string $id): void
{
    $personId = (int)$id;
    $person = db_row('SELECT * FROM people WHERE id = ?', [$personId]);
    if (!$person) {
        json_err('That person does not exist.', 404);
    }
    if ($person['employment_status'] === 'Terminated') {
        json_err('This person is already terminated.', 409);
    }

    db_query("UPDATE people SET employment_status = 'Terminated' WHERE id = ?", [$personId]);
    db_query('UPDATE users SET is_active = 0 WHERE person_id = ?', [$personId]);
    db_query(
        'UPDATE asset_assignments SET returned_at = NOW() WHERE person_id = ? AND returned_at IS NULL',
        [$personId]
    );
    db_query(
        "UPDATE assets SET status = 'Available'
         WHERE status = 'Assigned' AND id IN (
             SELECT asset_id FROM (
                 SELECT aa.asset_id FROM asset_assignments aa
                 WHERE aa.person_id = ?
                   AND NOT EXISTS (
                       SELECT 1 FROM asset_assignments live
                       WHERE live.asset_id = aa.asset_id AND live.returned_at IS NULL)
             ) x)",
        [$personId]
    );
    audit('person.terminate', 'person', $personId, ['name' => $person['first_name'] . ' ' . $person['last_name']]);
    json_ok();
}

function org_chart(): void
{
    $people = db_all(
        "SELECT id, first_name, last_name, job_title, department, manager_id
         FROM people WHERE employment_status <> 'Terminated' ORDER BY first_name"
    );
    $byManager = [];
    foreach ($people as $p) {
        $byManager[$p['manager_id'] ?? 0][] = $p;
    }
    render('people/org_chart', [
        'pageTitle' => 'Org chart',
        'breadcrumbs' => ['People' => null, 'Directory' => '/people', 'Org chart' => null],
        'byManager' => $byManager,
    ]);
}
