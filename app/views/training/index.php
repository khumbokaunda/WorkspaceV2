<?php
// Training and development: courses and certifications in progress.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Training</h1>
    <button class="btn btn-primary" onclick="mxNewTraining()"><i class="fa-solid fa-plus me-2"></i>Add training</button>
</div>

<div class="mx-card">
    <div class="mx-card-header"><h2><?= $canManage ? 'All training' : 'My training' ?></h2></div>
    <div class="mx-table-toolbar">
        <input type="search" class="form-control form-control-sm mx-table-search" placeholder="Search course, provider" aria-label="Search training">
        <select id="tr-status" class="form-select form-select-sm" style="max-width:170px" aria-label="Filter by status">
            <option value="">All statuses</option>
<?php foreach ($statuses as $s): ?>
            <option><?= e($s) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="mx-card-body mx-flush">
        <table id="mx-training-table" class="table mx-stack align-middle" style="width:100%">
            <thead><tr><?php if ($canManage): ?><th>Person</th><?php endif; ?><th>Course</th><th>Provider</th><th>Toward</th><th>Target</th><th>Status</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="mx-training-empty" class="mx-empty" style="display:none">
            <i class="fa-solid fa-graduation-cap"></i><p>No training recorded yet.</p>
            <button class="btn btn-primary" onclick="mxNewTraining()">Add the first one</button>
        </div>
    </div>
</div>

<script>
var mxTrainCanManage = <?= $canManage ? 'true' : 'false' ?>;
var mxTrainStatuses = <?= json_encode(array_values($statuses)) ?>;
var mxTrainPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;
var mxTrainMyPid = <?= (int)$myPid ?>;
var mxTrainRecords = [];

function mxTrainChip(s) {
    var map = { 'Planned': 'mx-chip-info', 'In Progress': 'mx-chip-warning', 'Completed': 'mx-chip-success', 'Cancelled': 'mx-chip-plain' };
    return '<span class="mx-chip ' + (map[s] || 'mx-chip-plain') + '">' + MX.escape(s) + '</span>';
}
function mxLoadTraining() {
    fetch('/api/training', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (data) { mxTrainRecords = data.records || []; mxRenderTraining(); });
}
function mxRenderTraining() {
    var labels = [];
    if (mxTrainCanManage) labels.push('Person');
    labels = labels.concat(['Course', 'Provider', 'Toward', 'Target', 'Status', '']);
    var rows = mxTrainRecords.map(function (t) {
        var cells = [];
        if (mxTrainCanManage) cells.push(MX.escape(t.person_name || ''));
        cells.push(MX.escape(t.course_name));
        cells.push(MX.escape(t.provider || ''));
        cells.push(t.linked_cert_code ? '<code>' + MX.escape(t.linked_cert_code) + '</code>' : '');
        cells.push('<span class="mx-tabular">' + MX.escape(t.target_date || '') + '</span>');
        cells.push(mxTrainChip(t.status));
        cells.push('<button class="btn btn-subtle btn-sm" onclick="mxEditTraining(' + t.id + ')" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
            '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteTraining(' + t.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>');
        return cells;
    });
    if (window.mxTrainingTable) { window.mxTrainingTable.clear(); window.mxTrainingTable.rows.add(rows).draw(); }
    else {
        window.mxTrainingTable = MX.table('#mx-training-table', { data: rows, columnDefs: [{ targets: '_all', createdCell: function (td, cd, rd, ri, ci) { td.setAttribute('data-label', labels[ci]); } }] });
        document.getElementById('tr-status').addEventListener('change', function () { window.mxTrainingTable.column(mxTrainCanManage ? 5 : 4).search(this.value).draw(); });
    }
    document.getElementById('mx-training-empty').style.display = rows.length ? 'none' : '';
    document.getElementById('mx-training-table').style.display = rows.length ? '' : 'none';
}
function mxTrainingForm(t) {
    t = t || {};
    var personField = '';
    if (mxTrainCanManage) {
        personField = '<div class="mb-3"><label class="form-label">Person</label><select class="form-select" name="person_id">' +
            mxTrainPeople.map(function (p) { return '<option value="' + p.id + '"' + ((t.person_id || mxTrainMyPid) == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') + '</select></div>';
    }
    var statusOpts = mxTrainStatuses.map(function (s) { return '<option' + ((t.status || 'Planned') === s ? ' selected' : '') + '>' + MX.escape(s) + '</option>'; }).join('');
    return '<form id="training-form">' + personField +
        '<div class="mb-3"><label class="form-label">Course or qualification</label><input class="form-control" name="course_name" required maxlength="200" value="' + MX.escape(t.course_name || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Provider</label><input class="form-control" name="provider" maxlength="160" value="' + MX.escape(t.provider || '') + '"></div>' +
        '<div class="col"><label class="form-label">Status</label><select class="form-select" name="status">' + statusOpts + '</select></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date" value="' + (t.start_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Target</label><input type="date" class="form-control" name="target_date" value="' + (t.target_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Completed</label><input type="date" class="form-control" name="completed_date" value="' + (t.completed_date || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Working toward (cert code)</label><input class="form-control mx-mono" name="linked_cert_code" maxlength="60" placeholder="CCNP" value="' + MX.escape(t.linked_cert_code || '') + '"></div>' +
        '<div class="col"><label class="form-label">Cost</label><input type="number" step="0.01" min="0" class="form-control" name="cost" value="' + (t.cost != null ? t.cost : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(t.notes || '') + '"></div>' +
        '</form>';
}
function mxNewTraining() { MX.drawer.open({ title: 'Add training', body: mxTrainingForm(null), footer: mxTrainFoot('mxSubmitTraining(null)') }); }
function mxEditTraining(id) { var t = mxTrainRecords.find(function (x) { return x.id == id; }); if (t) MX.drawer.open({ title: 'Edit training', body: mxTrainingForm(t), footer: mxTrainFoot('mxSubmitTraining(' + id + ')') }); }
function mxTrainFoot(onclick) { return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="' + onclick + '">Save</button>'; }
function mxSubmitTraining(id) {
    var form = document.getElementById('training-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/training/' + id, MX.formData(form)) : MX.api('POST', '/training', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); mxLoadTraining(); }).catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteTraining(id) {
    MX.confirm('Delete this training record?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/training/' + id).then(function () { MX.ok('Deleted.'); mxLoadTraining(); }).catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadTraining);
</script>
