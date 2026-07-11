<?php
// Admin: user accounts and access. Create accounts, link to people, place them
// in a primary department and any number of access groups, set the descriptive
// employment type, reset passwords and enable or disable accounts. Access flows
// from group membership, not a role column, so the last active administrator is
// protected on both membership edits and deactivation.

declare(strict_types=1);

// The Administrators group id, the standing that must never reach zero active
// members.
function admin_group_id(): int
{
    return (int)db_val("SELECT id FROM `groups` WHERE group_key = 'administrators'");
}

// How many active accounts belong to the Administrators group, optionally
// ignoring one account (the one being edited).
function active_admin_count(?int $exceptUserId = null): int
{
    $gid = admin_group_id();
    if (!$gid) {
        return 0;
    }
    if ($exceptUserId !== null) {
        return (int)db_val(
            'SELECT COUNT(*) FROM user_groups ug JOIN users u ON u.id = ug.user_id
             WHERE ug.group_id = ? AND u.is_active = 1 AND u.id <> ?',
            [$gid, $exceptUserId]
        );
    }
    return (int)db_val(
        'SELECT COUNT(*) FROM user_groups ug JOIN users u ON u.id = ug.user_id
         WHERE ug.group_id = ? AND u.is_active = 1',
        [$gid]
    );
}

function employment_types(): array
{
    return ['Full-time', 'Part-time', 'Contract', 'Intern', 'Consultant'];
}

function index(): void
{
    render('admin/users', [
        'pageTitle' => 'Users and Access',
        'breadcrumbs' => ['Admin' => null, 'Users and Access' => null],
        'users' => db_all(
            "SELECT u.*, p.first_name, p.last_name, p.employment_type,
                    (SELECT g.name FROM user_groups ug JOIN `groups` g ON g.id = ug.group_id
                       WHERE ug.user_id = u.id AND ug.is_primary = 1 LIMIT 1) AS primary_department
             FROM users u
             LEFT JOIN people p ON p.id = u.person_id
             ORDER BY u.username"
        ),
        'departments' => db_all("SELECT id, name FROM `groups` WHERE type = 'department' AND is_active = 1 ORDER BY name"),
        'accessGroups' => db_all("SELECT id, name FROM `groups` WHERE type = 'access_group' AND is_active = 1 ORDER BY sort_order, name"),
        'employmentTypes' => employment_types(),
        'people' => db_all(
            "SELECT p.id, p.first_name, p.last_name FROM people p
             WHERE p.employment_status <> 'Terminated'
             ORDER BY p.first_name"
        ),
        'userGroups' => user_group_membership_map(),
        'canEditAccess' => user_can('admin.roles'),
    ]);
}

// A map of user id to the group ids they belong to, for pre-selecting the form.
function user_group_membership_map(): array
{
    $map = [];
    foreach (db_all('SELECT user_id, group_id, is_primary FROM user_groups') as $ug) {
        $uid = (int)$ug['user_id'];
        $map[$uid] ??= ['primary' => null, 'access' => []];
        if ((int)$ug['is_primary'] === 1) {
            $map[$uid]['primary'] = (int)$ug['group_id'];
        } else {
            $map[$uid]['access'][] = (int)$ug['group_id'];
        }
    }
    return $map;
}

// Validate that a group id is a department, and access ids are access groups.
function valid_department(int $id): bool
{
    return (bool)db_val("SELECT id FROM `groups` WHERE id = ? AND type = 'department' AND is_active = 1", [$id]);
}
function clean_access_group_ids(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $valid = array_map(
        fn($r) => (int)$r['id'],
        db_all("SELECT id FROM `groups` WHERE type = 'access_group' AND id IN ($place)", $ids)
    );
    return array_values(array_intersect($ids, $valid));
}

function create(): void
{
    $in = input();
    $errors = validate($in, [
        'username' => 'required|max:60|min:3',
        'email' => 'required|email|max:190',
        'primary_department' => 'required|int',
    ]);
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    if (db_val('SELECT id FROM users WHERE username = ? OR email = ?', [in_str('username'), in_str('email')])) {
        json_err('That username or email is already taken.', 422, ['username' => 'Already taken.']);
    }
    $deptId = (int)in_int('primary_department');
    if (!valid_department($deptId)) {
        json_err('Choose a valid primary department.', 422, ['primary_department' => 'Invalid department.']);
    }
    $accessIds = clean_access_group_ids(is_array($in['access_group_ids'] ?? null) ? $in['access_group_ids'] : []);

    $personId = in_int('person_id');
    if ($personId) {
        if (!db_val('SELECT id FROM people WHERE id = ?', [$personId])) {
            json_err('That person does not exist.', 422, ['person_id' => 'Invalid person.']);
        }
        if (db_val('SELECT id FROM users WHERE person_id = ?', [$personId])) {
            json_err('That person already has an account.', 422, ['person_id' => 'Already linked.']);
        }
    }
    $employmentType = in_str('employment_type');
    if ($employmentType !== '' && !in_array($employmentType, employment_types(), true)) {
        json_err('Choose a valid employment type.', 422, ['employment_type' => 'Invalid.']);
    }

    $tempPassword = bin2hex(random_bytes(6)) . '!A';
    db_query(
        'INSERT INTO users (username, email, password_hash, person_id, role_id, must_change_password)
         VALUES (?,?,?,?,NULL,1)',
        [in_str('username'), in_str('email'), password_hash($tempPassword, PASSWORD_BCRYPT), $personId ?: null]
    );
    $userId = db_insert_id();

    db_query('INSERT INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,1)', [$userId, $deptId]);
    foreach ($accessIds as $gid) {
        db_query('INSERT IGNORE INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,0)', [$userId, $gid]);
    }
    if ($personId && $employmentType !== '') {
        db_query('UPDATE people SET employment_type = ? WHERE id = ?', [$employmentType, $personId]);
    }

    audit('user.create', 'user', $userId, [
        'username' => in_str('username'),
        'department' => $deptId,
        'access_groups' => $accessIds,
    ]);
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
    $adminGid = admin_group_id();
    $wasAdminMember = (bool)db_val('SELECT 1 FROM user_groups WHERE user_id = ? AND group_id = ?', [$userId, $adminGid]);

    // Username and email. Both are unique across accounts; the person record
    // keeps its own email separately, so changing one here does not touch it.
    if (array_key_exists('username', $in)) {
        $username = in_str('username');
        if (mb_strlen($username) < 3 || mb_strlen($username) > 60) {
            json_err('The username must be between 3 and 60 characters.', 422, ['username' => 'Between 3 and 60 characters.']);
        }
        if (db_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $userId])) {
            json_err('That username is already taken.', 422, ['username' => 'Already taken.']);
        }
        if ($username !== $user['username']) {
            db_query('UPDATE users SET username = ? WHERE id = ?', [$username, $userId]);
            audit('user.username_change', 'user', $userId, ['from' => $user['username'], 'to' => $username]);
        }
    }
    if (array_key_exists('email', $in)) {
        $email = in_str('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            json_err('Enter a valid email address.', 422, ['email' => 'Enter a valid email address.']);
        }
        if (db_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId])) {
            json_err('That email address belongs to another account.', 422, ['email' => 'Already in use.']);
        }
        if ($email !== $user['email']) {
            db_query('UPDATE users SET email = ? WHERE id = ?', [$email, $userId]);
            audit('user.email_change', 'user', $userId, ['from' => $user['email'], 'to' => $email]);
        }
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
        $user['person_id'] = $personId ?: null;
    }

    // Employment type, a descriptive attribute stored on the linked person.
    if (array_key_exists('employment_type', $in) && $user['person_id']) {
        $employmentType = in_str('employment_type');
        if ($employmentType !== '' && !in_array($employmentType, employment_types(), true)) {
            json_err('Choose a valid employment type.', 422, ['employment_type' => 'Invalid.']);
        }
        if ($employmentType !== '') {
            db_query('UPDATE people SET employment_type = ? WHERE id = ?', [$employmentType, (int)$user['person_id']]);
        }
    }

    // Group membership: the primary department and the set of access groups.
    // Protect the last administrator before removing this account from the
    // Administrators group.
    if (array_key_exists('primary_department', $in) || array_key_exists('access_group_ids', $in)) {
        $deptId = in_int('primary_department');
        if ($deptId !== null) {
            if (!valid_department($deptId)) {
                json_err('Choose a valid primary department.', 422, ['primary_department' => 'Invalid department.']);
            }
        } else {
            // Keep the existing primary if the caller did not send one.
            $deptId = (int)db_val('SELECT group_id FROM user_groups WHERE user_id = ? AND is_primary = 1', [$userId]);
            if (!$deptId) {
                json_err('A primary department is required.', 422, ['primary_department' => 'Required.']);
            }
        }
        $accessIds = array_key_exists('access_group_ids', $in)
            ? clean_access_group_ids(is_array($in['access_group_ids']) ? $in['access_group_ids'] : [])
            : array_map(
                fn($r) => (int)$r['group_id'],
                db_all('SELECT group_id FROM user_groups WHERE user_id = ? AND is_primary = 0', [$userId])
            );

        $willBeAdmin = in_array($adminGid, $accessIds, true);
        if ($wasAdminMember && !$willBeAdmin && (int)$user['is_active'] === 1 && active_admin_count($userId) === 0) {
            json_err('This is the last active administrator. Grant Administrators to another active account first.', 422, ['access_group_ids' => 'Last administrator.']);
        }

        // Rewrite membership: one primary department, plus the chosen access
        // groups. Access groups are never primary.
        db_query('DELETE FROM user_groups WHERE user_id = ?', [$userId]);
        db_query('INSERT INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,1)', [$userId, $deptId]);
        foreach ($accessIds as $gid) {
            db_query('INSERT IGNORE INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,0)', [$userId, $gid]);
        }
        audit('user.groups', 'user', $userId, ['department' => $deptId, 'access_groups' => $accessIds]);
    }

    // Enable or disable, guarding the last administrator and your own account.
    if (array_key_exists('is_active', $in)) {
        $active = (int)(bool)$in['is_active'];
        if ($userId === (int)$me['id'] && $active === 0) {
            json_err('You cannot disable your own account.', 403);
        }
        if ($active === 0 && $wasAdminMember && active_admin_count($userId) === 0) {
            json_err('This is the last active administrator and cannot be disabled.', 422, ['is_active' => 'Last administrator.']);
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
