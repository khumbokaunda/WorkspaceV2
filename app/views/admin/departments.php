<?php
// Admin: departments. Each department is a home for its members and carries the
// default permissions those members inherit, plus optional module visibility
// rules. Editing is live for members, so the drawer says so. Departments with
// members are archived rather than deleted.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 style="font-size:20px" class="mb-1">Departments</h1>
        <p class="text-muted mb-0" style="font-size:13px">A department is a person's home. Its permissions become the baseline every member inherits.</p>
    </div>
    <button class="btn btn-primary" onclick="mxNewGroup()"><i class="fa-solid fa-plus me-2"></i>New department</button>
</div>

<div class="mx-card">
    <div class="mx-card-body mx-flush">
        <table class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Department</th><th>Head</th><th>Members</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($groups as $g): ?>
                <tr<?= $g['is_active'] ? '' : ' style="opacity:.55"' ?>>
                    <td data-label="Department">
                        <strong><?= e($g['name']) ?></strong>
<?php if (!empty($g['description'])): ?>
                        <span class="text-muted d-block" style="font-size:12px"><?= e($g['description']) ?></span>
<?php endif; ?>
                    </td>
                    <td data-label="Head"><?= e(trim(($g['head_first'] ?? '') . ' ' . ($g['head_last'] ?? ''))) ?: '<span class="text-muted">Not set</span>' ?></td>
                    <td data-label="Members"><span class="mx-tabular"><?= (int)$g['member_count'] ?></span></td>
                    <td data-label="Permissions" id="perm-count-<?= (int)$g['id'] ?>"><span class="text-muted">View</span></td>
                    <td data-label="Status">
<?php if ($g['is_active']): ?>
                        <span class="mx-chip mx-chip-success">Active</span>
<?php else: ?>
                        <span class="mx-chip mx-chip-plain">Archived</span>
<?php endif; ?>
                    </td>
                    <td data-label="">
                        <button class="btn btn-subtle btn-sm" onclick="mxEditGroup(<?= (int)$g['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
<?php if ($g['is_active']): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteGroup(<?= (int)$g['id'] ?>, '<?= e(addslashes($g['name'])) ?>')" aria-label="Delete or archive"><i class="fa-regular fa-trash-can"></i></button>
<?php else: ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxRestoreGroup(<?= (int)$g['id'] ?>)" aria-label="Restore"><i class="fa-solid fa-rotate-left"></i></button>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php if (!$groups): ?>
        <div class="mx-empty"><i class="fa-solid fa-sitemap"></i><p>No departments yet.</p></div>
<?php endif; ?>
    </div>
</div>

<script>
var mxGroupType = 'department';
var mxGroupTypeLabel = 'department';
var mxPermSections = <?= json_encode(array_map(fn($rows) => array_map(fn($p) => ['id' => (int)$p['id'], 'key' => $p['permission_key'], 'description' => $p['description']], $rows), $permissionSections)) ?>;
var mxNavModules = <?= json_encode(array_map(fn($m) => $m['label'], $navModules)) ?>;
var mxPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => trim($p['first_name'] . ' ' . $p['last_name'])], $people)) ?>;
var mxDepartments = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name']], $departments)) ?>;

function mxGroupForm(g, permIds, moduleVis) {
    g = g || {};
    permIds = permIds || [];
    moduleVis = moduleVis || {};
    var isSystem = g.is_system == 1;
    var headOpts = '<option value="">Not set</option>' + mxPeople.map(function (p) {
        return '<option value="' + p.id + '"' + (g.head_person_id == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>';
    }).join('');
    var parentField = '';
    if (mxGroupType === 'department') {
        var parentOpts = '<option value="">None (top level)</option>' + mxDepartments.filter(function (d) { return d.id != g.id; }).map(function (d) {
            return '<option value="' + d.id + '"' + (g.parent_id == d.id ? ' selected' : '') + '>' + MX.escape(d.name) + '</option>';
        }).join('');
        parentField = '<div class="col"><label class="form-label">Parent department</label><select class="form-select" name="parent_id">' + parentOpts + '</select></div>';
    }

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

    return '<form id="group-form">' +
        '<input type="hidden" name="id" value="' + (g.id || 0) + '">' +
        '<input type="hidden" name="type" value="' + mxGroupType + '">' +
        (isSystem ? '<div class="alert alert-info py-2" style="font-size:12px"><i class="fa-solid fa-shield-halved me-1"></i>This is a protected system group. It cannot be deleted and keeps its core access.</div>' : '') +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="128" value="' + MX.escape(g.name || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="255" value="' + MX.escape(g.description || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Head</label><select class="form-select" name="head_person_id">' + headOpts + '</select></div>' + parentField + '</div>' +
        '<div class="alert alert-warning py-2" style="font-size:12px"><i class="fa-solid fa-bolt me-1"></i>Changes take effect immediately for every member.</div>' +
        '<h3 style="font-size:14px" class="mt-3 mb-1">Permissions</h3>' +
        '<p class="text-muted" style="font-size:12px">Members inherit these. A person can be granted or revoked individually on top.</p>' +
        '<div style="max-height:280px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' + permHtml + '</div>' +
        '<div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-subtle btn-sm" onclick="mxPermAll(true)">Select all</button><button type="button" class="btn btn-subtle btn-sm" onclick="mxPermAll(false)">Clear all</button></div>' +
        '<h3 style="font-size:14px" class="mt-4 mb-1">Module visibility</h3>' +
        '<p class="text-muted" style="font-size:12px">By default a module shows when a member holds one of its permissions. Force it here if needed. Hide wins over show.</p>' +
        '<div style="max-height:220px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' + visHtml + '</div>' +
        '</form>';
}

function mxPermAll(on) {
    document.querySelectorAll('.mx-gperm').forEach(function (b) { b.checked = on; });
}

function mxGroupFooter(g) {
    return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
        '<button class="btn btn-primary" onclick="mxSubmitGroup()">Save</button>';
}

function mxNewGroup() {
    MX.drawer.open({ title: 'New ' + mxGroupTypeLabel, body: mxGroupForm(null, [], {}), footer: mxGroupFooter(null) });
}

function mxEditGroup(id) {
    fetch('/api/admin/groups/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.fail(data.error); return; }
            MX.drawer.open({ title: 'Edit ' + MX.escape(data.group.name), body: mxGroupForm(data.group, data.permission_ids, data.module_visibility), footer: mxGroupFooter(data.group) });
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
