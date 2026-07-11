<?php
// Timesheets: the current week's entries with a running total, a log form, and
// for managers a utilisation summary across the team.
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Timesheets</h1>
<?php if ($canLog && $myPid): ?>
    <button class="btn btn-primary" onclick="mxLogTime()"><i class="fa-solid fa-plus me-2"></i>Log hours</button>
<?php endif; ?>
</div>

<?php if (!$myPid): ?>
<div class="mx-card mb-3"><div class="mx-card-body"><p class="mb-0 text-muted" style="font-size:13px">Your account is not linked to a person record, so you cannot log hours. Ask an administrator to link it.</p></div></div>
<?php endif; ?>

<div class="mx-card mb-3">
    <div class="mx-card-header">
        <h2>My week</h2>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-subtle btn-sm" onclick="mxWeekShift(-7)" aria-label="Previous week"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="mx-week-label" class="mx-tabular" style="font-size:13px"></span>
            <button class="btn btn-subtle btn-sm" onclick="mxWeekShift(7)" aria-label="Next week"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
    <div class="mx-card-body mx-flush" id="mx-week-body">
        <div class="mx-empty py-4"><i class="fa-solid fa-business-time"></i><p class="mb-0">Loading...</p></div>
    </div>
</div>

<?php if ($canViewAll): ?>
<div class="mx-card">
    <div class="mx-card-header"><h2>Utilisation this week</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$utilisation): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-chart-column"></i><p class="mb-0">No active people.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Person</th><th class="text-end">Total hours</th><th class="text-end">Billable</th><th style="width:30%">Billable share</th></tr></thead>
            <tbody>
<?php foreach ($utilisation as $u):
        $total = (float)$u['total_hours']; $bill = (float)$u['billable_hours'];
        $pct = $total > 0 ? round($bill / $total * 100) : 0; ?>
                <tr>
                    <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td class="text-end mx-tabular"><?= e(rtrim(rtrim(number_format($total, 2), '0'), '.')) ?></td>
                    <td class="text-end mx-tabular"><?= e(rtrim(rtrim(number_format($bill, 2), '0'), '.')) ?></td>
                    <td>
                        <div style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden"><div style="width:<?= $pct ?>%;height:100%;background:var(--mx-success)"></div></div>
                        <small class="text-muted"><?= $pct ?>%</small>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
var mxCanLog = <?= $canLog && $myPid ? 'true' : 'false' ?>;
var mxTsProjects = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name']], $projects)) ?>;
var mxWeekStart = '<?= e($weekStart) ?>';
var mxWeekEnd = '<?= e($weekEnd) ?>';

function mxWeekShift(delta) {
    var d = new Date(mxWeekStart + 'T00:00:00');
    d.setDate(d.getDate() + delta);
    location.href = '/timesheets?week=' + MX.isoDate(d);
}
function mxLoadWeek() {
    document.getElementById('mx-week-label').textContent = mxWeekStart + ' to ' + mxWeekEnd;
    fetch('/api/timesheets?from=' + mxWeekStart + '&to=' + mxWeekEnd, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { mxRenderWeek(data.entries || []); });
}
function mxRenderWeek(entries) {
    var body = document.getElementById('mx-week-body');
    if (!entries.length) {
        body.innerHTML = '<div class="mx-empty py-4"><i class="fa-solid fa-business-time"></i><p class="mb-0">No hours logged this week.</p></div>';
        return;
    }
    var total = 0, billable = 0;
    var rows = entries.map(function (e) {
        total += parseFloat(e.hours); if (e.is_billable == 1) billable += parseFloat(e.hours);
        var where = e.project_name ? MX.escape(e.project_name) : '<span class="text-muted">No project</span>';
        if (e.task_title) where += ' <span class="text-muted">/ ' + MX.escape(e.task_title) + '</span>';
        var actions = mxCanLog
            ? '<button class="btn btn-subtle btn-sm" onclick=\'mxEditTime(' + JSON.stringify(e).replace(/'/g, "&#39;") + ')\' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
              '<button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteTime(' + e.id + ')" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>'
            : '';
        return '<tr><td class="mx-tabular" style="font-size:13px">' + e.work_date + '</td>' +
            '<td style="font-size:13px">' + where + (e.description ? '<span class="text-muted d-block" style="font-size:11px">' + MX.escape(e.description) + '</span>' : '') + '</td>' +
            '<td class="text-end mx-tabular">' + parseFloat(e.hours) + '</td>' +
            '<td>' + (e.is_billable == 1 ? '<span class="mx-chip mx-chip-success" style="font-size:10px">billable</span>' : '') + '</td>' +
            '<td class="text-end text-nowrap">' + actions + '</td></tr>';
    }).join('');
    body.innerHTML = '<div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">' +
        '<thead><tr><th>Date</th><th>Project</th><th class="text-end">Hours</th><th>Billable</th><th></th></tr></thead><tbody>' + rows + '</tbody>' +
        '<tfoot><tr><th colspan="2" class="text-end">Total</th><th class="text-end mx-tabular">' + total.toFixed(2).replace(/\.00$/, '') + '</th><th colspan="2" class="text-muted" style="font-weight:400;font-size:12px">' + billable.toFixed(2).replace(/\.00$/, '') + ' billable</th></tr></tfoot>' +
        '</table></div>';
}

function mxTimeForm(e) {
    e = e || {};
    var projOpts = '<option value="">No project</option>' + mxTsProjects.map(function (p) { return '<option value="' + p.id + '"' + (e.project_id == p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('');
    return '<form id="ts-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Date</label><input type="date" class="form-control" name="work_date" required value="' + (e.work_date || MX.today()) + '"></div>' +
        '<div class="col"><label class="form-label">Hours</label><input type="number" step="0.25" min="0" max="24" class="form-control" name="hours" required value="' + (e.hours != null ? e.hours : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Project</label><select class="form-select" name="project_id" id="ts-project" onchange="mxLoadTasks(this.value)">' + projOpts + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Task</label><select class="form-select" name="task_id" id="ts-task"><option value="">No task</option></select></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="500" value="' + MX.escape(e.description || '') + '"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_billable" id="ts-bill"' + (e.is_billable == 1 ? ' checked' : '') + '><label class="form-check-label" for="ts-bill">Billable to the client</label></div>' +
        '</form>';
}
function mxLoadTasks(projectId, selectedTaskId) {
    var sel = document.getElementById('ts-task');
    if (!projectId) { sel.innerHTML = '<option value="">No task</option>'; return; }
    fetch('/api/timesheets/project/' + projectId + '/tasks', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (data) {
            sel.innerHTML = '<option value="">No task</option>' + (data.tasks || []).map(function (t) {
                return '<option value="' + t.id + '"' + (selectedTaskId == t.id ? ' selected' : '') + '>' + MX.escape(t.title) + '</option>';
            }).join('');
        });
}
function mxLogTime() {
    MX.drawer.open({ title: 'Log hours', body: mxTimeForm(null),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitTime(null)">Log</button>' });
}
function mxEditTime(e) {
    MX.drawer.open({ title: 'Edit entry', body: mxTimeForm(e),
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitTime(' + e.id + ')">Save</button>' });
    if (e.project_id) mxLoadTasks(e.project_id, e.task_id);
}
function mxSubmitTime(id) {
    var form = document.getElementById('ts-form');
    if (!form.reportValidity()) return;
    var call = id ? MX.api('PATCH', '/timesheets/' + id, MX.formData(form)) : MX.api('POST', '/timesheets', MX.formData(form));
    call.then(function () { MX.drawer.close(); MX.ok('Saved.'); mxLoadWeek(); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteTime(id) {
    MX.confirm('Delete this entry?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/timesheets/' + id).then(function () { MX.ok('Deleted.'); mxLoadWeek(); }).catch(function (e) { MX.fail(e.message); });
    });
}

document.addEventListener('DOMContentLoaded', mxLoadWeek);
</script>
