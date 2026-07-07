<?php
// Admin: three panels on one page. Role permission matrix, role module
// visibility matrix, and the per-user override editor showing inherited
// versus overridden with reset to default.
$navModules = array_filter($moduleCatalog, fn($m) => !empty($m['nav']));
$widgetModules = array_filter($moduleCatalog, fn($m) => empty($m['nav']));
?>
<h1 style="font-size:20px" class="mb-4">Roles and Permissions</h1>

<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-perms" type="button" role="tab">Role permissions</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-vis" type="button" role="tab">Module visibility</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-user" type="button" role="tab" id="tab-user">Per-person overrides</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pane-perms" role="tabpanel">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <span class="text-muted" style="font-size:13px">Tick the permissions each role carries by default. Per-person overrides sit on top.</span>
                <div class="flex-grow-1"></div>
                <button class="btn btn-primary btn-sm" onclick="mxSavePerms()">Save permissions</button>
            </div>
            <div class="mx-card-body mx-matrix-wrap">
                <table class="mx-matrix">
                    <thead>
                        <tr>
                            <th style="text-align:left;font-family:var(--mx-font)">Permission</th>
<?php foreach ($roles as $r): ?>
                            <th><?= e($r['display_name']) ?></th>
<?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($permissions as $p): ?>
                        <tr>
                            <th scope="row">
                                <code style="font-size:12px"><?= e($p['permission_key']) ?></code>
                                <span class="text-muted d-block" style="font-size:11px;font-weight:400"><?= e($p['description']) ?></span>
                            </th>
<?php foreach ($roles as $r): $has = isset($rolePerms[(int)$r['id']][(int)$p['id']]); ?>
                            <td>
                                <input type="checkbox" class="form-check-input mx-perm-box" data-role="<?= (int)$r['id'] ?>" data-perm="<?= (int)$p['id'] ?>" <?= $has ? 'checked' : '' ?> aria-label="<?= e($r['display_name'] . ' ' . $p['permission_key']) ?>">
                            </td>
<?php endforeach; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-vis" role="tabpanel">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <span class="text-muted" style="font-size:13px">Which navigation areas and dashboard widgets each role sees. Hiding also blocks direct URL access.</span>
                <div class="flex-grow-1"></div>
                <button class="btn btn-primary btn-sm" onclick="mxSaveVis()">Save visibility</button>
            </div>
            <div class="mx-card-body mx-matrix-wrap">
                <table class="mx-matrix">
                    <thead>
                        <tr>
                            <th style="text-align:left;font-family:var(--mx-font)">Module</th>
<?php foreach ($roles as $r): ?>
                            <th><?= e($r['display_name']) ?></th>
<?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach (['Navigation' => $navModules, 'Dashboard widgets' => $widgetModules] as $groupLabel => $group): ?>
                        <tr><th colspan="<?= count($roles) + 1 ?>" style="background:var(--mx-bg);font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:var(--mx-muted)"><?= e($groupLabel) ?></th></tr>
<?php foreach ($group as $key => $meta): ?>
                        <tr>
                            <th scope="row"><?= e($meta['label']) ?> <code style="font-size:11px" class="text-muted"><?= e($key) ?></code></th>
<?php foreach ($roles as $r): $vis = $roleVis[(int)$r['id']][$key] ?? true; ?>
                            <td>
                                <input type="checkbox" class="form-check-input mx-vis-box" data-role="<?= (int)$r['id'] ?>" data-module="<?= e($key) ?>" <?= $vis ? 'checked' : '' ?> aria-label="<?= e($r['display_name'] . ' sees ' . $meta['label']) ?>">
                            </td>
<?php endforeach; ?>
                        </tr>
<?php endforeach; ?>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-user" role="tabpanel">
        <div class="mx-card">
            <div class="mx-table-toolbar">
                <select id="ov-user" class="form-select form-select-sm" style="max-width:260px" aria-label="Choose an account">
                    <option value="">Choose an account</option>
<?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= e($u['username'] . ' (' . $u['role_name'] . ')') ?></option>
<?php endforeach; ?>
                </select>
                <div class="flex-grow-1"></div>
                <button class="btn btn-primary btn-sm" id="ov-save" style="display:none" onclick="mxSaveOverrides()">Save overrides</button>
            </div>
            <div class="mx-card-body" id="ov-body">
                <div class="mx-empty py-4"><i class="fa-solid fa-sliders"></i><p class="mb-0">Pick an account to see what it inherits from its role and what is overridden for it personally.</p></div>
            </div>
        </div>
    </div>
</div>

<script>
var mxOvUserId = null;
var mxOvPermState = {};
var mxOvModState = {};

function mxSavePerms() {
    var byRole = {};
    document.querySelectorAll('.mx-perm-box').forEach(function (box) {
        var rid = box.dataset.role;
        (byRole[rid] = byRole[rid] || []);
        if (box.checked) byRole[rid].push(parseInt(box.dataset.perm, 10));
    });
    var calls = Object.keys(byRole).map(function (rid) {
        return MX.api('POST', '/admin/roles/permissions', { role_id: rid, permission_ids: byRole[rid] });
    });
    Promise.all(calls)
        .then(function () { MX.ok('Role permissions saved.'); })
        .catch(function (e) { MX.fail(e.message); });
}

function mxSaveVis() {
    var matrix = {};
    document.querySelectorAll('.mx-vis-box').forEach(function (box) {
        var rid = box.dataset.role;
        (matrix[rid] = matrix[rid] || {})[box.dataset.module] = box.checked;
    });
    MX.api('POST', '/admin/roles/visibility', { matrix: matrix })
        .then(function () { MX.ok('Visibility saved.'); })
        .catch(function (e) { MX.fail(e.message); });
}

function mxOvStateChip(state) {
    if (state === 'inherited') return '<span class="mx-chip mx-chip-plain">inherited</span>';
    if (state === 'grant' || state === true) return '<span class="mx-chip mx-chip-success">granted</span>';
    return '<span class="mx-chip mx-chip-danger">revoked</span>';
}

function mxLoadOverrides(userId) {
    mxOvUserId = userId;
    mxOvPermState = {};
    mxOvModState = {};
    if (!userId) {
        document.getElementById('ov-body').innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-sliders"></i><p class="mb-0">Pick an account.</p></div>';
        document.getElementById('ov-save').style.display = 'none';
        return;
    }
    fetch('/api/admin/users/' + userId + '/access', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { MX.fail(data.error); return; }
            var html = '<p class="text-muted" style="font-size:13px">Role: <strong>' + MX.escape(data.user.role_name) + '</strong>. ' +
                'Cycle each row between inherited, grant and revoke. Inherited follows the role default.</p>' +
                '<h3 style="font-size:14px" class="mb-2">Permissions</h3><div class="mb-4">';
            data.permissions.forEach(function (p) {
                var state = p.override === null ? 'inherited' : p.override;
                mxOvPermState[p.id] = state;
                html += '<div class="d-flex align-items-center gap-2 py-1 border-bottom" style="font-size:13px">' +
                    '<code style="font-size:12px;min-width:210px">' + MX.escape(p.key) + '</code>' +
                    '<span class="text-muted flex-grow-1" style="font-size:12px">role default: ' + (p.from_role ? 'yes' : 'no') + '</span>' +
                    '<button type="button" class="btn btn-subtle btn-sm ov-perm" data-id="' + p.id + '">' + mxOvStateChip(state) + '</button>' +
                    '<span class="mx-chip ' + (p.effective ? 'mx-chip-success' : 'mx-chip-plain') + '" style="min-width:88px;justify-content:center">' + (p.effective ? 'effective yes' : 'effective no') + '</span>' +
                    '</div>';
            });
            html += '</div><h3 style="font-size:14px" class="mb-2">Modules and widgets</h3><div>';
            data.modules.forEach(function (m) {
                var state = m.override === null ? 'inherited' : (m.override ? 'grant' : 'revoke');
                mxOvModState[m.key] = state;
                html += '<div class="d-flex align-items-center gap-2 py-1 border-bottom" style="font-size:13px">' +
                    '<span style="min-width:210px">' + MX.escape(m.label) + (m.is_widget ? ' <span class="text-muted" style="font-size:11px">(widget)</span>' : '') + '</span>' +
                    '<span class="text-muted flex-grow-1" style="font-size:12px">role default: ' + (m.role_default ? 'visible' : 'hidden') + '</span>' +
                    '<button type="button" class="btn btn-subtle btn-sm ov-mod" data-key="' + MX.escape(m.key) + '">' + mxOvStateChip(state) + '</button>' +
                    '<span class="mx-chip ' + (m.effective ? 'mx-chip-success' : 'mx-chip-plain') + '" style="min-width:88px;justify-content:center">' + (m.effective ? 'visible' : 'hidden') + '</span>' +
                    '</div>';
            });
            html += '</div><div class="mt-3"><button type="button" class="btn btn-outline-primary btn-sm" onclick="mxResetOverrides()">Reset all to role defaults</button></div>';
            document.getElementById('ov-body').innerHTML = html;
            document.getElementById('ov-save').style.display = '';

            document.querySelectorAll('.ov-perm').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.dataset.id;
                    var next = { inherited: 'grant', grant: 'revoke', revoke: 'inherited' }[mxOvPermState[id]];
                    mxOvPermState[id] = next;
                    btn.innerHTML = mxOvStateChip(next);
                });
            });
            document.querySelectorAll('.ov-mod').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var key = btn.dataset.key;
                    var next = { inherited: 'grant', grant: 'revoke', revoke: 'inherited' }[mxOvModState[key]];
                    mxOvModState[key] = next;
                    btn.innerHTML = mxOvStateChip(next);
                });
            });
        });
}

function mxResetOverrides() {
    Object.keys(mxOvPermState).forEach(function (k) { mxOvPermState[k] = 'inherited'; });
    Object.keys(mxOvModState).forEach(function (k) { mxOvModState[k] = 'inherited'; });
    mxSaveOverrides();
}

function mxSaveOverrides() {
    if (!mxOvUserId) return;
    var permPayload = {};
    Object.keys(mxOvPermState).forEach(function (id) {
        permPayload[id] = mxOvPermState[id] === 'inherited' ? null : mxOvPermState[id];
    });
    var modPayload = {};
    Object.keys(mxOvModState).forEach(function (key) {
        modPayload[key] = mxOvModState[key] === 'inherited' ? null : mxOvModState[key] === 'grant';
    });
    MX.api('POST', '/admin/users/' + mxOvUserId + '/overrides', {
        permission_overrides: permPayload,
        module_overrides: modPayload
    })
        .then(function () { MX.ok('Overrides saved.'); mxLoadOverrides(mxOvUserId); })
        .catch(function (e) { MX.fail(e.message); });
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ov-user').addEventListener('change', function () { mxLoadOverrides(this.value); });
    var preselect = new URLSearchParams(location.search).get('user');
    if (preselect) {
        document.getElementById('ov-user').value = preselect;
        new bootstrap.Tab(document.getElementById('tab-user')).show();
        mxLoadOverrides(preselect);
    }
});
</script>
