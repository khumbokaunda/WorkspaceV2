<?php
// Admin: access groups. A reusable, cross-cutting grant a user may hold many
// of, on top of their department. Each carries a permission set and optional
// module visibility rules, and its membership is managed here. The
// Administrators group is protected: it keeps system administration and its
// last active member cannot be removed.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 style="font-size:20px" class="mb-1">Access Groups</h1>
        <p class="text-muted mb-0" style="font-size:13px">Cross-cutting access a person can hold alongside their department. Access is the union of every group a person belongs to.</p>
    </div>
    <button class="btn btn-primary" onclick="mxNewGroup()"><i class="fa-solid fa-plus me-2"></i>New access group</button>
</div>

<div class="mx-card">
    <div class="mx-card-body mx-flush">
        <table class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Access group</th><th>Members</th><th>Type</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($groups as $g): ?>
                <tr<?= $g['is_active'] ? '' : ' style="opacity:.55"' ?>>
                    <td data-label="Access group">
                        <strong><?= e($g['name']) ?></strong>
<?php if ($g['is_system']): ?>
                        <span class="mx-chip mx-chip-info ms-1">System</span>
<?php endif; ?>
<?php if (!empty($g['description'])): ?>
                        <span class="text-muted d-block" style="font-size:12px"><?= e($g['description']) ?></span>
<?php endif; ?>
                    </td>
                    <td data-label="Members"><span class="mx-tabular"><?= (int)$g['member_count'] ?></span></td>
                    <td data-label="Type"><span class="mx-chip mx-chip-plain">Access group</span></td>
                    <td data-label="Status">
<?php if ($g['is_active']): ?>
                        <span class="mx-chip mx-chip-success">Active</span>
<?php else: ?>
                        <span class="mx-chip mx-chip-plain">Archived</span>
<?php endif; ?>
                    </td>
                    <td data-label="">
                        <button class="btn btn-subtle btn-sm" onclick="mxEditGroup(<?= (int)$g['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
<?php if (!$g['is_system']): ?>
<?php if ($g['is_active']): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteGroup(<?= (int)$g['id'] ?>, '<?= e(addslashes($g['name'])) ?>')" aria-label="Delete or archive"><i class="fa-regular fa-trash-can"></i></button>
<?php else: ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxRestoreGroup(<?= (int)$g['id'] ?>)" aria-label="Restore"><i class="fa-solid fa-rotate-left"></i></button>
<?php endif; ?>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php if (!$groups): ?>
        <div class="mx-empty"><i class="fa-solid fa-user-lock"></i><p>No access groups yet.</p></div>
<?php endif; ?>
    </div>
</div>

<script>
var mxGroupType = 'access_group';
var mxGroupTypeLabel = 'access group';
var mxPermSections = <?= json_encode(array_map(fn($rows) => array_map(fn($p) => ['id' => (int)$p['id'], 'key' => $p['permission_key'], 'description' => $p['description']], $rows), $permissionSections)) ?>;
var mxNavModules = <?= json_encode(array_map(fn($m) => $m['label'], $navModules)) ?>;
var mxPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => trim($p['first_name'] . ' ' . $p['last_name'])], $people)) ?>;
var mxDepartments = [];
var mxAccounts = <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'username' => $a['username'], 'name' => $a['name'], 'is_active' => (int)$a['is_active']], $accounts)) ?>;
var mxCurrentGroupId = null;
var mxCurrentMembers = [];

function mxGroupForm(g, permIds, moduleVis) {
    g = g || {};
    permIds = permIds || [];
    moduleVis = moduleVis || {};
    var isSystem = g.is_system == 1;
    var headOpts = '<option value="">Not set</option>' + mxPeople.map(function (p) {
        return '<option value="' + p.id + '"' + (g.head_person_id == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>';
    }).join('');

    var permHtml = '';
    Object.keys(mxPermSections).forEach(function (section) {
        var rows = mxPermSections[section].map(function (p) {
            var checked = permIds.indexOf(p.id) !== -1 ? ' checked' : '';
            return '<label class="d-flex align-items-start gap-2 py-1" style="font-size:13px">' +
                '<input type="checkbox" class="form-check-input mx-gperm" value="' + p.id + '"' + checked + '>' +
                '<span><code style="font-size:12px">' + MX.escape(p.key) + '</code>' +
                (p.description ? '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(p.description) + '</span>' : '') +
                '</span></label>';
        }).join('');
        permHtml += '<div class="mb-2"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--mx-muted);font-weight:600;margin:8px 0 4px">' + MX.escape(section) + '</div>' + rows + '</div>';
    });

    var visHtml = Object.keys(mxNavModules).map(function (key) {
        var state = moduleVis.hasOwnProperty(key) ? (moduleVis[key] ? 'show' : 'hide') : 'default';
        return '<div class="d-flex align-items-center gap-2 py-1" style="font-size:13px">' +
            '<span class="flex-grow-1">' + MX.escape(mxNavModules[key]) + '</span>' +
            '<select class="form-select form-select-sm mx-gvis" data-key="' + MX.escape(key) + '" style="max-width:150px">' +
            '<option value="default"' + (state === 'default' ? ' selected' : '') + '>Follow permission</option>' +
            '<option value="show"' + (state === 'show' ? ' selected' : '') + '>Always show</option>' +
            '<option value="hide"' + (state === 'hide' ? ' selected' : '') + '>Always hide</option>' +
            '</select></div>';
    }).join('');

    var membersHtml = '';
    if (g.id) {
        membersHtml =
            '<h3 style="font-size:14px" class="mt-4 mb-1">Members</h3>' +
            '<p class="text-muted" style="font-size:12px">Members hold this group\'s access in addition to their department.</p>' +
            '<div class="d-flex gap-2 mb-2"><select id="mx-member-add" class="form-select form-select-sm">' +
            '<option value="">Add an account</option>' +
            mxAccounts.map(function (a) { return '<option value="' + a.id + '">' + MX.escape(a.username) + (a.name ? ' (' + MX.escape(a.name) + ')' : '') + (a.is_active ? '' : ' [disabled]') + '</option>'; }).join('') +
            '</select><button type="button" class="btn btn-primary btn-sm" onclick="mxAddMember()">Add</button></div>' +
            '<div id="mx-member-list"></div>';
    }

    return '<form id="group-form">' +
        '<input type="hidden" name="id" value="' + (g.id || 0) + '">' +
        '<input type="hidden" name="type" value="' + mxGroupType + '">' +
        (isSystem ? '<div class="alert alert-info py-2" style="font-size:12px"><i class="fa-solid fa-shield-halved me-1"></i>This is a protected system group. It cannot be deleted, keeps system administration, and its last active member cannot be removed.</div>' : '') +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="128" value="' + MX.escape(g.name || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="255" value="' + MX.escape(g.description || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Owner</label><select class="form-select" name="head_person_id">' + headOpts + '</select></div>' +
        '<div class="alert alert-warning py-2" style="font-size:12px"><i class="fa-solid fa-bolt me-1"></i>Changes take effect immediately for every member.</div>' +
        '<h3 style="font-size:14px" class="mt-3 mb-1">Permissions</h3>' +
        '<p class="text-muted" style="font-size:12px">Members gain these on top of their other groups.</p>' +
        '<div style="max-height:260px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' + permHtml + '</div>' +
        '<div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-subtle btn-sm" onclick="mxPermAll(true)">Select all</button><button type="button" class="btn btn-subtle btn-sm" onclick="mxPermAll(false)">Clear all</button></div>' +
        '<h3 style="font-size:14px" class="mt-4 mb-1">Module visibility</h3>' +
        '<p class="text-muted" style="font-size:12px">By default a module shows when a member holds one of its permissions. Force it here if needed. Hide wins over show.</p>' +
        '<div style="max-height:200px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' + visHtml + '</div>' +
        membersHtml +
        '</form>';
}

function mxPermAll(on) {
    document.querySelectorAll('.mx-gperm').forEach(function (b) { b.checked = on; });
}

function mxRenderMembers() {
    var el = document.getElementById('mx-member-list');
    if (!el) return;
    if (!mxCurrentMembers.length) {
        el.innerHTML = '<p class="text-muted" style="font-size:13px">No members yet.</p>';
        return;
    }
    el.innerHTML = mxCurrentMembers.map(function (m) {
        return '<div class="d-flex align-items-center gap-2 py-1 border-bottom" style="font-size:13px">' +
            '<span class="flex-grow-1">' + MX.escape(m.username) + (m.name ? ' <span class="text-muted">(' + MX.escape(m.name) + ')</span>' : '') + '</span>' +
            '<button type="button" class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxRemoveMember(' + m.id + ')" aria-label="Remove"><i class="fa-solid fa-user-minus"></i></button>' +
            '</div>';
    }).join('');
}

function mxAddMember() {
    var sel = document.getElementById('mx-member-add');
    var uid = parseInt(sel.value, 10);
    if (!uid || !mxCurrentGroupId) return;
    MX.api('POST', '/admin/groups/' + mxCurrentGroupId + '/members', { user_id: uid })
        .then(function () {
            var a = mxAccounts.find(function (x) { return x.id === uid; });
            if (a && !mxCurrentMembers.find(function (m) { return m.id === uid; })) {
                mxCurrentMembers.push({ id: a.id, username: a.username, name: a.name });
                mxRenderMembers();
            }
            sel.value = '';
            MX.ok('Member added.');
        })
        .catch(function (e) { MX.fail(e.message); });
}

function mxRemoveMember(uid) {
    if (!mxCurrentGroupId) return;
    MX.api('DELETE', '/admin/groups/' + mxCurrentGroupId + '/members', { user_id: uid })
        .then(function () {
            mxCurrentMembers = mxCurrentMembers.filter(function (m) { return m.id !== uid; });
            mxRenderMembers();
            MX.ok('Member removed.');
        })
        .catch(function (e) { MX.fail(e.message); });
}

function mxGroupFooter(g) {
    return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
        '<button class="btn btn-primary" onclick="mxSubmitGroup()">Save</button>';
}

function mxNewGroup() {
    mxCurrentGroupId = null;
    mxCurrentMembers = [];
    MX.drawer.open({ title: 'New access group', body: mxGroupForm(null, [], {}), footer: mxGroupFooter(null) });
}

function mxEditGroup(id) {
    fetch('/api/admin/groups/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.fail(data.error); return; }
            mxCurrentGroupId = id;
            mxCurrentMembers = (data.members || []).map(function (m) { return { id: m.id, username: m.username, name: m.name }; });
            MX.drawer.open({ title: 'Edit ' + MX.escape(data.group.name), body: mxGroupForm(data.group, data.permission_ids, data.module_visibility), footer: mxGroupFooter(data.group) });
            mxRenderMembers();
        });
}

function mxSubmitGroup() {
    var form = document.getElementById('group-form');
    if (!form.reportValidity()) return;
    var permIds = Array.prototype.map.call(document.querySelectorAll('.mx-gperm:checked'), function (b) { return parseInt(b.value, 10); });
    var moduleVis = {};
    document.querySelectorAll('.mx-gvis').forEach(function (sel) {
        if (sel.value === 'show') moduleVis[sel.dataset.key] = true;
        else if (sel.value === 'hide') moduleVis[sel.dataset.key] = false;
    });
    var payload = MX.formData(form);
    payload.permission_ids = permIds;
    payload.module_visibility = moduleVis;
    MX.api('POST', '/admin/groups', payload)
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxDeleteGroup(id, name) {
    MX.confirm('Delete ' + name + '? If it still has members it will be archived instead.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/admin/groups/' + id)
            .then(function (r) { MX.ok(r.message || 'Done.'); setTimeout(function () { location.reload(); }, 400); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mxRestoreGroup(id) {
    MX.api('POST', '/admin/groups/' + id + '/restore')
        .then(function () { MX.ok('Restored.'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { MX.fail(e.message); });
}
</script>
