<?php // Admin: accounts table with role, person link, status and actions. ?>
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
        <table id="mx-user-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><th>Username</th><th>Email</th><th>Person</th><th>Role</th><th>Two-factor</th><th>Last login</th><th>Status</th><th></th></tr></thead>
            <tbody>
<?php foreach ($users as $u): ?>
                <tr>
                    <td data-label="Username"><strong><?= e($u['username']) ?></strong><?= (int)$u['must_change_password'] === 1 ? ' <span class="mx-chip mx-chip-warning">pw change due</span>' : '' ?></td>
                    <td data-label="Email"><?= e($u['email']) ?></td>
                    <td data-label="Person"><?= e($u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : '') ?></td>
                    <td data-label="Role"><?= e($u['role_name']) ?></td>
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
    'role_id' => (int)$u['role_id'], 'is_active' => (int)$u['is_active'],
], $users)) ?>;
var mxRoles = <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['display_name']], $roles)) ?>;
var mxPeopleOpts = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;

document.addEventListener('DOMContentLoaded', function () {
    window.mxUserTable = MX.table('#mx-user-table', {});
});

function mxNewUser() {
    var body = '<form id="user-form">' +
        '<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required minlength="3" maxlength="60"></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190"></div>' +
        '<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role_id">' +
        mxRoles.map(function (r) { return '<option value="' + r.id + '">' + MX.escape(r.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Link to person</label><select class="form-select" name="person_id"><option value="">None</option>' +
        mxPeopleOpts.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('') + '</select></div>' +
        '<p class="text-muted" style="font-size:12px">A temporary password is generated and shown once. The user must change it at first sign in.</p>' +
        '</form>';
    MX.drawer.open({
        title: 'Create account',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitUser()">Create</button>'
    });
}
function mxSubmitUser() {
    var form = document.getElementById('user-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/admin/users', MX.formData(form))
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
    var body = '<form id="user-edit-form">' +
        '<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required minlength="3" maxlength="60" value="' + MX.escape(u.username) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190" value="' + MX.escape(u.email) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role_id">' +
        mxRoles.map(function (r) { return '<option value="' + r.id + '"' + (u.role_id === r.id ? ' selected' : '') + '>' + MX.escape(r.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Linked person</label><select class="form-select" name="person_id"><option value="">None</option>' +
        mxPeopleOpts.map(function (p) { return '<option value="' + p.id + '"' + (u.person_id === p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') + '</select></div>' +
        '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="ue-active"' + (u.is_active ? ' checked' : '') + '><label class="form-check-label" for="ue-active">Account enabled</label></div>' +
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
    MX.api('PATCH', '/admin/users/' + id, MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); });
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
