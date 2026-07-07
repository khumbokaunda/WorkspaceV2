<?php // One project: header with status and lead, then its task surface. ?>
<?php $chip = match ($project['status']) { 'Active' => 'mx-chip-success', 'On Hold' => 'mx-chip-warning', 'Completed' => 'mx-chip-info', default => 'mx-chip-plain' }; ?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-center gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($project['name']) ?></h1>
                <span class="mx-chip <?= $chip ?>"><?= e($project['status']) ?></span>
            </div>
            <div class="text-muted mt-1" style="font-size:13px">
                <?= e($project['lead_first'] ? 'Lead: ' . $project['lead_first'] . ' ' . $project['lead_last'] : 'No lead') ?>
            </div>
<?php if ($project['description']): ?>
            <p class="text-muted mt-2 mb-0" style="font-size:13px;white-space:pre-wrap"><?= e($project['description']) ?></p>
<?php endif; ?>
        </div>
<?php if ($canManage): ?>
        <button class="btn btn-outline-primary" onclick="mxEditProject()"><i class="fa-solid fa-pen me-2"></i>Edit</button>
<?php endif; ?>
    </div>
</div>

<?php
$taskProjectId = (int)$project['id'];
$taskProjects = [];
require APP_ROOT . '/app/partials/task_views.php';
?>

<script>
var mxProject = <?= json_encode([
    'id' => (int)$project['id'],
    'name' => $project['name'],
    'description' => $project['description'],
    'status' => $project['status'],
    'lead_id' => $project['lead_id'] !== null ? (int)$project['lead_id'] : null,
]) ?>;
function mxEditProject() {
    var body = '<form id="proj-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160" value="' + MX.escape(mxProject.name) + '"></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="2000">' + MX.escape(mxProject.description || '') + '</textarea></div>' +
        '<div class="mb-3"><label class="form-label">Lead</label><select class="form-select" name="lead_id"><option value="">None</option>' +
        mxTaskPeople.map(function (p) { return '<option value="' + p.id + '"' + (mxProject.lead_id === p.id ? ' selected' : '') + '>' + MX.escape(p.name) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">' +
        ['Active', 'On Hold', 'Completed', 'Archived'].map(function (s) { return '<option' + (mxProject.status === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
        '</select></div>' +
        '</form>';
    MX.drawer.open({
        title: 'Edit project',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSaveProject()">Save changes</button>'
    });
}
function mxSaveProject() {
    var form = document.getElementById('proj-form');
    if (!form.reportValidity()) return;
    MX.api('PATCH', '/projects/' + mxProject.id, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
