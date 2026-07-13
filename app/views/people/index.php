<?php // People directory: table with toolbar, onboarding wizard in the drawer. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Directory</h1>
    <div class="d-flex gap-2">
        <a href="/people/org-chart" class="btn btn-outline-primary"><i class="fa-solid fa-sitemap me-2"></i>Org chart</a>
<?php if (user_can('people.create')): ?>
        <button class="btn btn-primary" onclick="mxOpenOnboarding()"><i class="fa-solid fa-user-plus me-2"></i>Add person</button>
<?php endif; ?>
    </div>
</div>

<div class="mx-card">
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search people" aria-label="Search people">
        <select id="mx-filter-dept" class="form-select form-select-sm" style="max-width:180px" aria-label="Filter by department">
            <option value="">All departments</option>
<?php foreach ($departments as $d): ?>
            <option value="<?= e($d['department']) ?>"><?= e($d['department']) ?></option>
<?php endforeach; ?>
        </select>
        <select id="mx-filter-status" class="form-select form-select-sm" style="max-width:150px" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option>Active</option>
            <option>On Leave</option>
            <option>Terminated</option>
        </select>
        <div class="flex-grow-1"></div>
        <button class="btn btn-subtle btn-sm" onclick="MX.exportCsv(window.mxPeopleTable, 'people')"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-people-table" class="table dt-host mx-stack align-middle" style="width:100%">
            <thead>
                <tr><th>Name</th><th>Job title</th><th>Department</th><th>Manager</th><th>Status</th><th>Started</th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <div id="mx-people-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-users"></i>
            <p>No people yet. Onboard your first team member.</p>
<?php if (user_can('people.create')): ?>
            <button class="btn btn-primary" onclick="mxOpenOnboarding()">Add person</button>
<?php endif; ?>
        </div>
    </div>
</div>

<script>
var mxCanCreateAccount = <?= user_can('admin.users') ? 'true' : 'false' ?>;
var mxDepartmentGroups = <?= json_encode(array_map(fn($d) => $d['name'], $departmentGroups)) ?>;
var mxAccountDepartments = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'name' => $d['name']], $accountDepartments)) ?>;
var mxAccountAccessGroups = <?= json_encode(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $accountAccessGroups)) ?>;
var mxStarterAssets = <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'label' => $a['asset_tag'] . ' ' . $a['name']], $starterAssets)) ?>;
var mxManagers = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['first_name'] . ' ' . $m['last_name']], $managers)) ?>;

function mxStatusChip(s) {
    var cls = s === 'Active' ? 'mx-chip-success' : (s === 'On Leave' ? 'mx-chip-info' : 'mx-chip-danger');
    return '<span class="mx-chip ' + cls + '">' + MX.escape(s) + '</span>';
}

document.addEventListener('DOMContentLoaded', function () {
    fetch('/api/people', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var rows = data.people.map(function (p) {
                return [
                    '<a href="/people/' + p.id + '"><strong>' + MX.escape(p.first_name + ' ' + p.last_name) + '</strong></a><div class="text-muted" style="font-size:12px">' + MX.escape(p.email) + '</div>',
                    MX.escape(p.job_title || ''),
                    MX.escape(p.department || ''),
                    MX.escape(p.mgr_first ? p.mgr_first + ' ' + p.mgr_last : ''),
                    mxStatusChip(p.employment_status),
                    MX.escape(p.start_date || '')
                ];
            });
            window.mxPeopleTable = MX.table('#mx-people-table', {
                data: rows,
                columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, r, c) {
                    td.setAttribute('data-label', ['Name','Job title','Department','Manager','Status','Started'][c]);
                } }]
            });
            if (!rows.length) {
                document.getElementById('mx-people-empty').style.display = '';
                document.getElementById('mx-people-table').style.display = 'none';
            }
            document.getElementById('mx-filter-dept').addEventListener('change', function () {
                window.mxPeopleTable.column(2).search(this.value ? '^' + this.value + '$' : '', true, false).draw();
            });
            document.getElementById('mx-filter-status').addEventListener('change', function () {
                window.mxPeopleTable.column(4).search(this.value).draw();
            });
            if (new URLSearchParams(location.search).get('action') === 'new' && mxCanOnboard) mxOpenOnboarding();
        });
});

var mxCanOnboard = <?= user_can('people.create') ? 'true' : 'false' ?>;

// Onboarding wizard: three short steps inside the drawer.
function mxOpenOnboarding() {
    var accountStep = '';
    if (mxCanCreateAccount) {
        accountStep =
            '<hr><h3 style="font-size:14px" class="mb-3">Login account (optional)</h3>' +
            '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="create_account" id="ob-account"> <label class="form-check-label" for="ob-account">Create a login account</label></div>' +
            '<div id="ob-account-fields" style="display:none">' +
            '<div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" maxlength="60"></div>' +
            '<div class="mb-3"><label class="form-label">Primary department</label><select class="form-select" name="primary_department"><option value="">Choose a department</option>' +
            mxAccountDepartments.map(function (d) { return '<option value="' + d.id + '">' + MX.escape(d.name) + '</option>'; }).join('') +
            '</select></div>' +
            (mxAccountAccessGroups.length ? '<div class="mb-3"><label class="form-label">Access groups <span class="text-muted" style="font-weight:400">(optional)</span></label>' +
            '<div style="max-height:140px;overflow:auto;border:1px solid var(--mx-border);border-radius:8px;padding:8px 12px">' +
            mxAccountAccessGroups.map(function (g) { return '<label class="d-flex align-items-center gap-2 py-1" style="font-size:13px"><input type="checkbox" class="form-check-input ob-access-box" value="' + g.id + '">' + MX.escape(g.name) + '</label>'; }).join('') +
            '</div></div>' : '') +
            '<p class="text-muted" style="font-size:12px">Access is the union of the department and any access groups. A temporary password is generated and shown once. The person must change it at first sign in.</p>' +
            '</div>';
    }
    var assetStep = '';
    if (mxStarterAssets.length) {
        assetStep =
            '<hr><h3 style="font-size:14px" class="mb-3">Starter asset (optional)</h3>' +
            '<div class="mb-3"><select class="form-select" name="starter_asset_id"><option value="">None</option>' +
            mxStarterAssets.map(function (a) { return '<option value="' + a.id + '">' + MX.escape(a.label) + '</option>'; }).join('') +
            '</select></div>';
    }
    var body =
        '<form id="ob-form">' +
        '<h3 style="font-size:14px" class="mb-3">Identity</h3>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">First name</label><input class="form-control" name="first_name" required maxlength="80"></div>' +
        '<div class="col"><label class="form-label">Last name</label><input class="form-control" name="last_name" required maxlength="80"></div></div>' +
        '<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required maxlength="190"></div>' +
        '<div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="40"></div>' +
        '<hr><h3 style="font-size:14px" class="mb-3">Role and department</h3>' +
        '<div class="mb-3"><label class="form-label">Job title</label><input class="form-control" name="job_title" maxlength="120"></div>' +
        '<div class="mb-3"><label class="form-label">Department</label><select class="form-select" name="department">' +
        '<option value="">Choose a department</option>' +
        mxDepartmentGroups.map(function (d) { return '<option value="' + MX.escape(d) + '">' + MX.escape(d) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="mb-3"><label class="form-label">Manager</label><select class="form-select" name="manager_id"><option value="">None</option>' +
        mxManagers.map(function (m) { return '<option value="' + m.id + '">' + MX.escape(m.name) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="mb-3"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date"></div>' +
        accountStep + assetStep + '</form>';

    MX.drawer.open({
        title: 'Add a person',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitOnboarding()">Create person</button>'
    });
    var chk = document.getElementById('ob-account');
    if (chk) chk.addEventListener('change', function () {
        document.getElementById('ob-account-fields').style.display = this.checked ? '' : 'none';
    });
}

function mxSubmitOnboarding() {
    var form = document.getElementById('ob-form');
    if (!form.reportValidity()) return;
    var payload = MX.formData(form);
    payload.access_group_ids = Array.prototype.map.call(document.querySelectorAll('.ob-access-box:checked'), function (b) { return parseInt(b.value, 10); });
    MX.api('POST', '/people', payload)
        .then(function (data) {
            MX.drawer.close();
            if (data.temp_password) {
                Swal.fire({
                    title: 'Person created',
                    html: 'Temporary password (shown once):<br><code style="font-size:16px">' + MX.escape(data.temp_password) + '</code>',
                    icon: 'success',
                    confirmButtonText: 'Done'
                }).then(function () { location.reload(); });
            } else {
                MX.ok('Person created.');
                setTimeout(function () { location.reload(); }, 700);
            }
        })
        .catch(function (err) { MX.fail(err.message); MX.showFieldErrors(form, err.fields); });
}
</script>
