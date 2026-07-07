<?php
// Admin: roles, permissions, module visibility, and per-user overrides.
// One resolution model everywhere: role default, per-user row on top.

declare(strict_types=1);

function index(): void
{
    $roles = db_all('SELECT * FROM roles ORDER BY id');
    $permissions = db_all('SELECT * FROM permissions ORDER BY permission_key');

    $rolePerms = [];
    foreach (db_all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
        $rolePerms[(int)$rp['role_id']][(int)$rp['permission_id']] = true;
    }
    $roleVis = [];
    foreach (db_all("SELECT scope_id, module_key, is_visible FROM module_visibility WHERE scope = 'role'") as $mv) {
        $roleVis[(int)$mv['scope_id']][$mv['module_key']] = (bool)$mv['is_visible'];
    }

    render('admin/roles', [
        'pageTitle' => 'Roles and Permissions',
        'breadcrumbs' => ['Admin' => null, 'Roles and Permissions' => null],
        'roles' => $roles,
        'permissions' => $permissions,
        'rolePerms' => $rolePerms,
        'roleVis' => $roleVis,
        'moduleCatalog' => module_catalog(),
        'users' => db_all(
            'SELECT u.id, u.username, r.display_name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 ORDER BY u.username'
        ),
    ]);
}

// Save one role's permission set (full replacement for that role).
function save_permissions(): void
{
    $in = input();
    $roleId = in_int('role_id');
    if (!$roleId || !db_val('SELECT id FROM roles WHERE id = ?', [$roleId])) {
        json_err('Choose a valid role.', 422);
    }
    $wanted = array_map('intval', is_array($in['permission_ids'] ?? null) ? $in['permission_ids'] : []);
    $valid = array_map(fn($p) => (int)$p['id'], db_all('SELECT id FROM permissions'));
    $wanted = array_values(array_intersect($wanted, $valid));

    // Guard: an administrator cannot lock the admin area away from the admin role.
    $role = db_row('SELECT * FROM roles WHERE id = ?', [$roleId]);
    if ($role['role_key'] === 'admin') {
        $adminPermIds = array_map(fn($p) => (int)$p['id'], db_all(
            "SELECT id FROM permissions WHERE permission_key IN ('admin.users','admin.roles')"
        ));
        $wanted = array_values(array_unique(array_merge($wanted, $adminPermIds)));
    }

    db_query('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
    foreach ($wanted as $pid) {
        db_query('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$roleId, $pid]);
    }
    audit('role.permissions', 'role', $roleId, ['count' => count($wanted)]);
    json_ok();
}

// Save the role-scoped module visibility matrix (all roles at once).
function save_visibility(): void
{
    $in = input();
    $matrix = is_array($in['matrix'] ?? null) ? $in['matrix'] : [];
    $validRoles = array_map(fn($r) => (int)$r['id'], db_all('SELECT id FROM roles'));
    $validModules = array_keys(module_catalog());

    foreach ($matrix as $roleId => $modules) {
        $roleId = (int)$roleId;
        if (!in_array($roleId, $validRoles, true) || !is_array($modules)) {
            continue;
        }
        foreach ($modules as $moduleKey => $visible) {
            if (!in_array($moduleKey, $validModules, true)) {
                continue;
            }
            db_query(
                "INSERT INTO module_visibility (scope, scope_id, module_key, is_visible)
                 VALUES ('role', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)",
                [$roleId, $moduleKey, (int)(bool)$visible]
            );
        }
    }
    audit('role.visibility', 'role', null, ['roles' => count($matrix)]);
    json_ok();
}

// Everything needed to render one user's override editor: role defaults,
// current overrides, and effective results.
function user_access_json(string $id): void
{
    $userId = (int)$id;
    $user = db_row(
        'SELECT u.id, u.username, u.role_id, r.display_name AS role_name
         FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
        [$userId]
    );
    if (!$user) {
        json_err('That account does not exist.', 404);
    }

    $rolePermIds = [];
    foreach (db_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [(int)$user['role_id']]) as $rp) {
        $rolePermIds[(int)$rp['permission_id']] = true;
    }
    $overrides = [];
    foreach (db_all('SELECT permission_id, effect FROM user_permission_overrides WHERE user_id = ?', [$userId]) as $o) {
        $overrides[(int)$o['permission_id']] = $o['effect'];
    }
    $perms = [];
    foreach (db_all('SELECT id, permission_key, description FROM permissions ORDER BY permission_key') as $p) {
        $pid = (int)$p['id'];
        $fromRole = isset($rolePermIds[$pid]);
        $override = $overrides[$pid] ?? null;
        $perms[] = [
            'id' => $pid,
            'key' => $p['permission_key'],
            'description' => $p['description'],
            'from_role' => $fromRole,
            'override' => $override,
            'effective' => $override === 'grant' ? true : ($override === 'revoke' ? false : $fromRole),
        ];
    }

    $roleVis = [];
    foreach (db_all(
        "SELECT module_key, is_visible FROM module_visibility WHERE scope = 'role' AND scope_id = ?",
        [(int)$user['role_id']]
    ) as $mv) {
        $roleVis[$mv['module_key']] = (bool)$mv['is_visible'];
    }
    $userVis = [];
    foreach (db_all(
        "SELECT module_key, is_visible FROM module_visibility WHERE scope = 'user' AND scope_id = ?",
        [$userId]
    ) as $mv) {
        $userVis[$mv['module_key']] = (bool)$mv['is_visible'];
    }
    $modules = [];
    foreach (module_catalog() as $key => $meta) {
        $roleDefault = $roleVis[$key] ?? true;
        $override = array_key_exists($key, $userVis) ? $userVis[$key] : null;
        $modules[] = [
            'key' => $key,
            'label' => $meta['label'],
            'is_widget' => empty($meta['nav']),
            'role_default' => $roleDefault,
            'override' => $override,
            'effective' => $override ?? $roleDefault,
        ];
    }
    json_out(['ok' => true, 'user' => $user, 'permissions' => $perms, 'modules' => $modules]);
}

// Save a user's permission overrides and module visibility overrides.
// Payload: permission_overrides: {permId: 'grant'|'revoke'|null},
//          module_overrides: {moduleKey: true|false|null}. Null means
//          remove the override and fall back to the role default.
function save_user_overrides(string $id): void
{
    $userId = (int)$id;
    $user = db_row('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        json_err('That account does not exist.', 404);
    }
    $me = current_user();
    if ($userId === (int)$me['id']) {
        json_err('You cannot edit your own overrides.', 403);
    }
    $in = input();

    $permOverrides = is_array($in['permission_overrides'] ?? null) ? $in['permission_overrides'] : [];
    $validPerms = array_map(fn($p) => (int)$p['id'], db_all('SELECT id FROM permissions'));
    foreach ($permOverrides as $pid => $effect) {
        $pid = (int)$pid;
        if (!in_array($pid, $validPerms, true)) {
            continue;
        }
        if ($effect === 'grant' || $effect === 'revoke') {
            db_query(
                'INSERT INTO user_permission_overrides (user_id, permission_id, effect) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE effect = VALUES(effect)',
                [$userId, $pid, $effect]
            );
        } else {
            db_query('DELETE FROM user_permission_overrides WHERE user_id = ? AND permission_id = ?', [$userId, $pid]);
        }
    }

    $moduleOverrides = is_array($in['module_overrides'] ?? null) ? $in['module_overrides'] : [];
    $validModules = array_keys(module_catalog());
    foreach ($moduleOverrides as $key => $visible) {
        if (!in_array($key, $validModules, true)) {
            continue;
        }
        if ($visible === null || $visible === '') {
            db_query(
                "DELETE FROM module_visibility WHERE scope = 'user' AND scope_id = ? AND module_key = ?",
                [$userId, $key]
            );
        } else {
            db_query(
                "INSERT INTO module_visibility (scope, scope_id, module_key, is_visible)
                 VALUES ('user', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)",
                [$userId, $key, (int)(bool)$visible]
            );
        }
    }
    audit('user.overrides', 'user', $userId, [
        'permissions' => count($permOverrides),
        'modules' => count($moduleOverrides),
    ]);
    json_ok();
}
