<?php
// Admin: user accounts and access. Create accounts, link to people, assign
// roles, reset passwords (re-arming the forced-change flag), enable or
// disable accounts.

declare(strict_types=1);

function index(): void
{
    render('admin/users', [
        'pageTitle' => 'Users and Access',
        'breadcrumbs' => ['Admin' => null, 'Users and Access' => null],
        'users' => db_all(
            'SELECT u.*, r.display_name AS role_name, r.role_key,
                    p.first_name, p.last_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN people p ON p.id = u.person_id
             ORDER BY u.username'
        ),
        'roles' => db_all('SELECT id, display_name FROM roles ORDER BY id'),
        'people' => db_all(
            "SELECT p.id, p.first_name, p.last_name FROM people p
             WHERE p.employment_status <> 'Terminated'
             ORDER BY p.first_name"
        ),
        'canEditAccess' => user_can('admin.roles'),
    ]);
}

function admin_users_rules(): array
{
    return [
        'username' => 'required|max:60|min:3',
        'email' => 'required|email|max:190',
        'role_id' => 'required|int',
    ];
}

function create(): void
{
    $in = input();
    $errors = validate($in, admin_users_rules());
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    if (db_val('SELECT id FROM users WHERE username = ? OR email = ?', [in_str('username'), in_str('email')])) {
        json_err('That username or email is already taken.', 422, ['username' => 'Already taken.']);
    }
    $roleId = in_int('role_id');
    if (!db_val('SELECT id FROM roles WHERE id = ?', [$roleId])) {
        json_err('Choose a valid role.', 422, ['role_id' => 'Invalid role.']);
    }
    $personId = in_int('person_id');
    if ($personId) {
        if (!db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
            json_err('That person does not exist.', 422, ['person_id' => 'Invalid person.']);
        }
        if (db_val('SELECT id FROM users WHERE person_id = ?', [$personId])) {
            json_err('That person already has an account.', 422, ['person_id' => 'Already linked.']);
        }
    }
    $tempPassword = bin2hex(random_bytes(6)) . '!A';
    db_query(
        'INSERT INTO users (username, email, password_hash, person_id, role_id, must_change_password)
         VALUES (?,?,?,?,?,1)',
        [in_str('username'), in_str('email'), password_hash($tempPassword, PASSWORD_BCRYPT), $personId ?: null, $roleId]
    );
    $userId = db_insert_id();
    audit('user.create', 'user', $userId, ['username' => in_str('username'), 'role_id' => $roleId]);
    json_ok(['user_id' => $userId, 'temp_password' => $tempPassword]);
}

function update(string $id): void
{
    $userId = (int)$id;
    $user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        json_err('That account does not exist.', 404);
    }
    $me = current_user();
    $in = input();

    // Role change.
    if (array_key_exists('role_id', $in)) {
        $roleId = in_int('role_id');
        if (!$roleId || !db_val('SELECT id FROM roles WHERE id = ?', [$roleId])) {
            json_err('Choose a valid role.', 422, ['role_id' => 'Invalid role.']);
        }
        if ($userId === (int)$me['id'] && $roleId !== (int)$user['role_id']) {
            json_err('You cannot change your own role.', 403);
        }
        db_query('UPDATE users SET role_id = ? WHERE id = ?', [$roleId, $userId]);
        audit('user.role_change', 'user', $userId, ['from' => (int)$user['role_id'], 'to' => $roleId]);
    }

    // Person link.
    if (array_key_exists('person_id', $in)) {
        $personId = in_int('person_id');
        if ($personId) {
            $holder = db_val('SELECT id FROM users WHERE person_id = ? AND id <> ?', [$personId, $userId]);
            if ($holder) {
                json_err('That person is already linked to another account.', 422, ['person_id' => 'Already linked.']);
            }
        }
        db_query('UPDATE users SET person_id = ? WHERE id = ?', [$personId ?: null, $userId]);
        audit('user.person_link', 'user', $userId, ['person_id' => $personId]);
    }

    // Enable or disable.
    if (array_key_exists('is_active', $in)) {
        $active = (int)(bool)$in['is_active'];
        if ($userId === (int)$me['id'] && $active === 0) {
            json_err('You cannot disable your own account.', 403);
        }
        db_query('UPDATE users SET is_active = ? WHERE id = ?', [$active, $userId]);
        audit($active ? 'user.enable' : 'user.disable', 'user', $userId, ['username' => $user['username']]);
    }
    json_ok();
}

// Reset: a fresh temporary password shown once; the forced-change flag re-arms.
function reset_password(string $id): void
{
    $userId = (int)$id;
    $user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        json_err('That account does not exist.', 404);
    }
    $tempPassword = bin2hex(random_bytes(6)) . '!A';
    db_query(
        'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?',
        [password_hash($tempPassword, PASSWORD_BCRYPT), $userId]
    );
    audit('user.password_reset', 'user', $userId, ['username' => $user['username'], 'by_admin' => true]);
    send_mail(
        $user['email'],
        $user['username'],
        'Your password was reset',
        '<p>An administrator reset the password on your account. Sign in with the temporary password they give you; you will be asked to choose a new one immediately.</p>'
    );
    json_ok(['temp_password' => $tempPassword]);
}
