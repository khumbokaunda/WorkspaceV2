<?php
// Admin: departments and access groups. Both are rows in the groups table, the
// same underlying thing (a group that grants permissions and module
// visibility). A department is a person's single home (one primary per user);
// an access group is a reusable, cross-cutting grant a user may hold many of.
// Access resolves as the union of every group a user belongs to, then
// per-person overrides with an explicit revoke winning. Editing a group takes
// effect live for its members, so every change is audited.

declare(strict_types=1);

// Permissions grouped by their leading segment (the module they belong to), so
// the editor reads as a tidy per-module checklist rather than a flat list. The
// system.admin permission is surfaced on its own so it is never buried.
function grp_permission_sections(): array
{
    $labels = module_section_labels();
    $restricted = restricted_permissions();
    $sections = [];
    foreach (db_all('SELECT id, permission_key, description FROM permissions ORDER BY permission_key') as $p) {
        if (in_array($p['permission_key'], $restricted, true)) {
            continue; // restricted permissions are never delegated through the editor
        }
        $prefix = explode('.', $p['permission_key'], 2)[0];
        $label = $labels[$prefix] ?? ucfirst(str_replace('_', ' ', $prefix));
        $sections[$label][] = $p;
    }
    ksort($sections);
    return $sections;
}

// A friendly label for each permission prefix, drawn from the module catalog
// where possible so wording stays consistent with the navigation.
function module_section_labels(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [
        'system'   => 'System administration',
        'admin'    => 'Administration',
        'dashboard'=> 'Dashboard',
        'people'   => 'Directory',
        'tasks'    => 'Projects and Tasks',
        'pettycash'=> 'Petty cash',
    ];
    foreach (module_catalog() as $meta) {
        if (empty($meta['permission'])) {
            continue;
        }
        $prefix = explode('.', $meta['permission'], 2)[0];
        if (!isset($map[$prefix]) && !empty($meta['label'])) {
            $map[$prefix] = $meta['label'];
        }
    }
    return $map;
}

// Shared list renderer for a group type.
function grp_render_list(string $type, string $view, string $pageTitle): void
{
    $groups = db_all(
        'SELECT g.*, p.first_name AS head_first, p.last_name AS head_last,
                (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS member_count
         FROM `groups` g
         LEFT JOIN people p ON p.id = g.head_person_id
         WHERE g.type = ?
         ORDER BY g.is_active DESC, g.sort_order, g.name',
        [$type]
    );
    render($view, [
        'pageTitle' => $pageTitle,
        'breadcrumbs' => ['Admin' => null, $pageTitle => null],
        'type' => $type,
        'groups' => $groups,
        'permissionSections' => grp_permission_sections(),
        'navModules' => array_filter(module_catalog(), fn($m) => !empty($m['nav'])),
        'people' => db_all("SELECT id, first_name, last_name FROM people WHERE employment_status = 'Active' ORDER BY first_name, last_name"),
        'departments' => db_all("SELECT id, name FROM `groups` WHERE type = 'department' AND is_active = 1 ORDER BY name"),
        'accounts' => db_all(
            "SELECT u.id, u.username, u.is_active,
                    TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS name
             FROM users u LEFT JOIN people p ON p.id = u.person_id
             ORDER BY u.username"
        ),
    ]);
}

function departments(): void
{
    grp_render_list('department', 'admin/departments', 'Departments');
}

function access_groups(): void
{
    grp_render_list('access_group', 'admin/access_groups', 'Access Groups');
}

// One group's full detail: its permission ids, its module visibility rows, its
// members, and enough metadata to render the editor.
function group_json(string $id): void
{
    $gid = (int)$id;
    $group = db_row('SELECT * FROM `groups` WHERE id = ?', [$gid]);
    if (!$group) {
        json_err('That group does not exist.', 404);
    }
    $permIds = array_map(
        fn($r) => (int)$r['permission_id'],
        db_all('SELECT permission_id FROM group_permissions WHERE group_id = ?', [$gid])
    );
    $moduleVis = [];
    foreach (db_all('SELECT module_key, is_visible FROM group_module_visibility WHERE group_id = ?', [$gid]) as $r) {
        $moduleVis[$r['module_key']] = (bool)$r['is_visible'];
    }
    $members = db_all(
        'SELECT u.id, u.username, ug.is_primary,
                TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS name
         FROM user_groups ug
         JOIN users u ON u.id = ug.user_id
         LEFT JOIN people p ON p.id = u.person_id
         WHERE ug.group_id = ?
         ORDER BY u.username',
        [$gid]
    );
    json_out([
        'ok' => true,
        'group' => $group,
        'permission_ids' => $permIds,
        'module_visibility' => $moduleVis,
        'members' => $members,
    ]);
}

// Create or update a department or access group, replacing its permission set
// and module visibility rows. Live for members, so audited.
function save_group(): void
{
    $in = input();
    $gid = in_int('id') ?? 0;
    $type = in_str('type');
    if (!in_array($type, ['department', 'access_group'], true)) {
        json_err('Choose a valid group type.', 422, ['type' => 'Invalid.']);
    }
    $name = in_str('name');
    if ($name === '') {
        json_err('A name is required.', 422, ['name' => 'Required.']);
    }
    $description = in_str('description') ?: null;
    $headId = in_int('head_person_id') ?: null;
    $parentId = in_int('parent_id') ?: null;

    $existing = $gid ? db_row('SELECT * FROM `groups` WHERE id = ?', [$gid]) : null;
    if ($gid && !$existing) {
        json_err('That group does not exist.', 404);
    }
    // A system group keeps its type; departments only nest under departments.
    if ($existing && $existing['is_system']) {
        $type = $existing['type'];
    }
    if ($type !== 'department') {
        $parentId = null;
    }
    if ($parentId && $existing && $parentId === $gid) {
        $parentId = null; // a group cannot be its own parent
    }
    if ($headId && !db_val('SELECT id FROM people WHERE id = ?', [$headId])) {
        $headId = null;
    }
    if ($parentId && !db_val("SELECT id FROM `groups` WHERE id = ? AND type = 'department'", [$parentId])) {
        $parentId = null;
    }

    if ($existing) {
        db_query(
            'UPDATE `groups` SET name = ?, description = ?, head_person_id = ?, parent_id = ? WHERE id = ?',
            [$name, $description, $headId, $parentId, $gid]
        );
    } else {
        $key = grp_unique_key($name);
        db_query(
            'INSERT INTO `groups` (group_key, name, type, parent_id, head_person_id, description, sort_order)
             VALUES (?,?,?,?,?,?,?)',
            [$key, $name, $type, $parentId, $headId, $description, $type === 'department' ? 10 : 50]
        );
        $gid = db_insert_id();
    }

    // Permissions: full replacement, keyed by permission id.
    $wanted = array_map('intval', is_array($in['permission_ids'] ?? null) ? $in['permission_ids'] : []);
    $valid = array_map(fn($p) => (int)$p['id'], db_all('SELECT id FROM permissions'));
    $wanted = array_values(array_intersect($wanted, $valid));
    // Restricted permissions never appear in the editor, so preserve any the
    // group already held rather than silently stripping them on save.
    $restrictedIds = array_map(
        fn($p) => (int)$p['id'],
        db_all(
            'SELECT gp.permission_id AS id FROM group_permissions gp
             JOIN permissions p ON p.id = gp.permission_id
             WHERE gp.group_id = ? AND p.permission_key IN (' . implode(',', array_fill(0, count(restricted_permissions()), '?')) . ')',
            array_merge([$gid], restricted_permissions())
        )
    );
    foreach ($restrictedIds as $rid) {
        if (!in_array($rid, $wanted, true)) {
            $wanted[] = $rid;
        }
    }
    // The Administrators group always keeps the system identity permissions, so
    // it can never be stripped of the access that protects it.
    if (($existing['group_key'] ?? '') === 'administrators') {
        foreach (restricted_permissions() as $key) {
            $pid = (int)db_val('SELECT id FROM permissions WHERE permission_key = ?', [$key]);
            if ($pid && !in_array($pid, $wanted, true)) {
                $wanted[] = $pid;
            }
        }
    }
    db_query('DELETE FROM group_permissions WHERE group_id = ?', [$gid]);
    foreach ($wanted as $pid) {
        db_query('INSERT IGNORE INTO group_permissions (group_id, permission_id) VALUES (?,?)', [$gid, $pid]);
    }

    // Module visibility: only forced rows are stored, absence means the default
    // (permission-derived) applies. Payload: {moduleKey: true|false}.
    $vis = is_array($in['module_visibility'] ?? null) ? $in['module_visibility'] : [];
    $validModules = array_keys(module_catalog());
    db_query('DELETE FROM group_module_visibility WHERE group_id = ?', [$gid]);
    foreach ($vis as $key => $visible) {
        if (!in_array($key, $validModules, true) || $visible === null || $visible === '') {
            continue;
        }
        db_query(
            'INSERT IGNORE INTO group_module_visibility (group_id, module_key, is_visible) VALUES (?,?,?)',
            [$gid, $key, (int)(bool)$visible]
        );
    }

    audit('group.save', 'group', $gid, [
        'type' => $type,
        'name' => $name,
        'permissions' => count($wanted),
        'module_rules' => count($vis),
    ]);
    json_ok(['id' => $gid]);
}

// A stable, unique key from a name, suffixed if a collision occurs.
function grp_unique_key(string $name): string
{
    $base = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
    $base = trim($base, '_') ?: 'group';
    $key = $base;
    $n = 2;
    while (db_val('SELECT id FROM `groups` WHERE group_key = ?', [$key])) {
        $key = $base . '_' . $n++;
    }
    return $key;
}

// Delete or archive a group. A system group is never removed. A group with
// members is archived (is_active = 0) rather than deleted, so its members keep a
// valid reference; an empty group is deleted outright.
function delete_group(string $id): void
{
    $gid = (int)$id;
    $group = db_row('SELECT * FROM `groups` WHERE id = ?', [$gid]);
    if (!$group) {
        json_err('That group does not exist.', 404);
    }
    if ($group['is_system']) {
        json_err('This is a protected system group and cannot be removed.', 422);
    }
    $members = (int)db_val('SELECT COUNT(*) FROM user_groups WHERE group_id = ?', [$gid]);
    if ($members > 0) {
        db_query('UPDATE `groups` SET is_active = 0 WHERE id = ?', [$gid]);
        audit('group.archive', 'group', $gid, ['name' => $group['name'], 'members' => $members]);
        json_ok(['archived' => true, 'message' => 'The group still has members, so it was archived rather than deleted.']);
    }
    db_query('DELETE FROM `groups` WHERE id = ?', [$gid]);
    audit('group.delete', 'group', $gid, ['name' => $group['name'], 'type' => $group['type']]);
    json_ok(['archived' => false]);
}

// Add an account to an access group. Membership is managed here only for access
// groups; a department is a person's single primary home, set on the account
// itself so it is never left blank.
function add_member(string $id): void
{
    $gid = (int)$id;
    $group = db_row("SELECT * FROM `groups` WHERE id = ?", [$gid]);
    if (!$group) {
        json_err('That group does not exist.', 404);
    }
    if ($group['type'] !== 'access_group') {
        json_err('Department membership is set on the account, in its primary department.', 422);
    }
    $userId = in_int('user_id');
    if (!$userId || !db_val('SELECT id FROM users WHERE id = ?', [$userId])) {
        json_err('Choose a valid account.', 422, ['user_id' => 'Invalid account.']);
    }
    db_query('INSERT IGNORE INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,0)', [$userId, $gid]);
    audit('group.member_add', 'group', $gid, ['user_id' => $userId, 'group' => $group['name']]);
    json_ok();
}

// Remove an account from an access group, protecting the last administrator.
function remove_member(string $id): void
{
    $gid = (int)$id;
    $group = db_row("SELECT * FROM `groups` WHERE id = ?", [$gid]);
    if (!$group) {
        json_err('That group does not exist.', 404);
    }
    if ($group['type'] !== 'access_group') {
        json_err('A person always keeps a primary department. Move them to another department on their account instead.', 422);
    }
    $userId = in_int('user_id');
    if (!$userId) {
        json_err('Choose a valid account.', 422, ['user_id' => 'Invalid account.']);
    }
    if ($group['group_key'] === 'administrators') {
        $active = (bool)db_val('SELECT is_active FROM users WHERE id = ?', [$userId]);
        if ($active && active_admin_count($userId) === 0) {
            json_err('This is the last active administrator. Grant Administrators to another active account first.', 422, ['user_id' => 'Last administrator.']);
        }
    }
    db_query('DELETE FROM user_groups WHERE user_id = ? AND group_id = ? AND is_primary = 0', [$userId, $gid]);
    audit('group.member_remove', 'group', $gid, ['user_id' => $userId, 'group' => $group['name']]);
    json_ok();
}

// Reactivate an archived group.
function restore_group(string $id): void
{
    $gid = (int)$id;
    $group = db_row('SELECT * FROM `groups` WHERE id = ?', [$gid]);
    if (!$group) {
        json_err('That group does not exist.', 404);
    }
    db_query('UPDATE `groups` SET is_active = 1 WHERE id = ?', [$gid]);
    audit('group.restore', 'group', $gid, ['name' => $group['name']]);
    json_ok();
}
