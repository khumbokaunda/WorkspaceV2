<?php // Admin: accounts table with department, access groups, status and actions. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Users and Access</h1>
    <button class="btn btn-primary" onclick="mxNewUser()"><i class="fa-solid fa-user-plus me-2"></i>Create account</button>
</div>

<div class="mx-card">
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search accounts" aria-label="Search accounts">
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxUserTable, 'users')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-user-table" class="table dt-host mx-stack align-middle" style="width:100%">
            <thead><tr><th>Username</th><th>Email</th><th>Person</th><th>Department</th><th>Two-factor</th><th>Last login</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($users as $u): ?>
                <tr>
                    <td data-label="Username"><strong><?= e($u['username']) ?></strong><?= (int)$u['must_change_password'] === 1 ? ' <span class="mx-chip mx-chip-warning">pw change due</span>' : '' ?></td>
                    <td data-label="Email"><?= e($u['email']) ?></td>
                    <td data-label="Person"><?= e($u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : '') ?></td>
                    <td data-label="Department"><?= e($u['primary_department'] ?: '') ?: '<span class="text-muted">None</span>' ?></td>
                    <td data-label="Two-factor"><?php $has2fa = ($u['totp_secret'] ?? null) !== null && $u['totp_secret'] !== ''; ?><span class="mx-chip <?= $has2fa ? 'mx-chip-success' : 'mx-chip-plain' ?>"><?= $has2fa ? 'Enrolled' : 'Off' ?></span></td>
                    <td data-label="Last login" class="mx-tabular"><?= e($u['last_login_at'] ?: 'Never') ?></td>
                    <td data-label="Status"><span class="mx-chip <?= (int)$u['is_active'] === 1 ? 'mx-chip-success' : 'mx-chip-danger' ?>"><?= (int)$u['is_active'] === 1 ? 'Active' : 'Disabled' ?></span></td>
                    <td data-label="">
                        <button class="btn btn-subtle btn-sm" onclick="mxEditUser(<?= (int)$u['id'] ?>)" aria-label="Edit account"><i class="fa-solid fa-pen"></i></button>
<?php if ($canEditAccess): ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxOverrides(<?= (int)$u['id'] ?>)" aria-label="Access overrides"><i class="fa-solid fa-sliders"></i></button>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var mxUsers = <?= json_encode(array_map(fn($u) => [
    'id' => (int)$u['id'], 'username' => $u['username'], 'email' => $u['email'],
    'person_id' => $u['person_id'] !== null ? (int)$u['person_id'] : null,
    'employment_type' => $u['employment_type'] ?? null,
    'is_active' => (int)$u['is_active'],
], $users)) ?>;
var mxDepartments = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name']], $departments)) ?>;
var mxAccessGroups = <?= json_encode(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $accessGroups)) ?>;
var mxEmploymentTypes = <?= json_encode(array_values($employmentTypes)) ?>;
var mxPeopleOpts = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxUserGroups = <?= json_encode((object)$userGroups) ?>;

document.addEventListener('DOMContentLoaded', function () {
    window.mxUserTable = MX.table('#mx-user-table', {});
});

function mxDeptSelect(selected) {
    return '<select class="form-select" name="primary_department" required><option value="">Choose a department</option>' +
        mxDepartments.map(function (d) { return '<option value="' + d.id + '"' + (selected === d.id ? ' selected' : '') + '>' + MX.escape(d.name) + '</option>'; }).join('') +
        '</select>';
}
function mxAccessChecks(selectedIds) {
    selectedIds = selectedIds || [];
    if (!mxAccessGroups.length) return '<p class="text-muted" style="font-size:12px">No access groups defined yet.</p>';
    return '<div style="max-height:160px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' +
        mxAccessGroups.map(function (g) {
            var on = selectedIds.indexOf(g.id) !== -1 ? ' checked' : '';
            return '<label class="d-flex align-items-center gap-2 py-1" style="font-size:13px"><input type="checkbox" class="form-check-input mx-access-box" value="' + g.id + '"' + on + '>' + MX.escape(g.name) + '</label>';
        }).join('') + '</div>';
}
function mxEmpTypeSelect(selected) {
    return '<select class="form-select" name="employment_type"><option value="">Not set</option>' +
        mxEmploymentTypes.map(function (t) { return '<option' + (selected === t ? ' selected' : '') + '>' + MX.escape(t) + '</option>'; }).join('') +
        '</select>';
}

function mxNewUser() {
    var body = '<form id="user-form">' +
        '<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required minlength="3" maxlength="60"></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190"></div>' +
        '<div class="mb-3"><label class="form-label">Link to person</label><select class="form-select" name="person_id"><option value="">None</option>' +
        mxPeopleOpts.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Primary department</label>' + mxDeptSelect(null) + '</div>' +
        '<div class="col"><label class="form-label">Employment type</label>' + mxEmpTypeSelect(null) + '</div></div>' +
        '<div class="mb-3"><label class="form-label">Access groups <span class="text-muted" style="font-weight:400">(optional)</span></label>' + mxAccessChecks([]) + '</div>' +
        '<p class="text-muted" style="font-size:12px">Access is the union of the department and any access groups. A temporary password is shown once; the user must change it at first sign in.</p>' +
        '</form>';
    MX.drawer.open({
        title: 'Create account',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitUser()">Create</button>'
    });
}

function mxCollectAccessIds() {
    return Array.prototype.map.call(document.querySelectorAll('.mx-access-box:checked'), function (b) { return parseInt(b.value, 10); });
}

function mxSubmitUser() {
    var form = document.getElementById('user-form');
    if (!form.reportValidity()) return;
    var payload = MX.formData(form);
    payload.access_group_ids = mxCollectAccessIds();
    MX.api('POST', '/admin/users', payload)
        .then(function (d) {
            MX.drawer.close();
            Swal.fire({
                title: 'Account created',
                html: 'Temporary password (shown once):<br><code style="font-size:16px">' + MX.escape(d.temp_password) + '</code>',
                icon: 'success'
            }).then(function () { location.reload(); });
        })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

function mxEditUser(id) {
    var u = mxUsers.find(function (x) { return x.id === id; });
    if (!u) return;
    var membership = mxUserGroups[id] || { primary: null, access: [] };
    var body = '<form id="user-edit-form">' +
        '<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required minlength="3" maxlength="60" value="' + MX.escape(u.username) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190" value="' + MX.escape(u.email) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Linked person</label><select class="form-select" name="person_id"><option value="">None</option>' +
        mxPeopleOpts.map(function (p) { return '<option value="' + p.id + '"' + (u.person_id === p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Primary department</label>' + mxDeptSelect(membership.primary) + '</div>' +
        '<div class="col"><label class="form-label">Employment type</label>' + mxEmpTypeSelect(u.employment_type) + '</div></div>' +
        '<div class="mb-3"><label class="form-label">Access groups</label>' + mxAccessChecks(membership.access) + '</div>' +
        '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="ue-active"' + (u.is_active ? ' checked' : '') + '><label class="form-check-label" for="ue-active">Account enabled</label></div>' +
        '<p class="text-muted" style="font-size:12px">Fine-grained grants and revokes for this person sit on top of the group access, under Access overrides.</p>' +
        '</form>';
    MX.drawer.open({
        title: 'Edit account',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="mxResetPw(' + id + ')">Reset password</button>' +
                '<button class="btn btn-primary" onclick="mxSaveUser(' + id + ')">Save</button>'
    });
}
function mxSaveUser(id) {
    var form = document.getElementById('user-edit-form');
    if (!form.reportValidity()) return;
    var payload = MX.formData(form);
    payload.access_group_ids = mxCollectAccessIds();
    payload.is_active = document.getElementById('ue-active').checked ? 1 : 0;
    MX.api('PATCH', '/admin/users/' + id, payload)
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxResetPw(id) {
    MX.confirm('Reset this password?', 'A new temporary password is generated and the forced change re-arms.', 'Reset').then(function (go) {
        if (!go) return;
        MX.api('POST', '/admin/users/' + id + '/reset-password', {})
            .then(function (d) {
                Swal.fire({
                    title: 'Password reset',
                    html: 'Temporary password (shown once):<br><code style="font-size:16px">' + MX.escape(d.temp_password) + '</code>',
                    icon: 'success'
                }).then(function () { location.reload(); });
            })
            .catch(function (e) { MX.fail(e.message); });
    });
}
function mxOverrides(id) {
    window.location.href = '/admin/roles?user=' + id;
}
</script>
