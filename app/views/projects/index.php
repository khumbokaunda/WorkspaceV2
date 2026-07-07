<?php // Projects overview plus the full task surface across all projects. ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Projects and Tasks</h1>
<?php if ($canManage): ?>
    <button class="btn btn-outline-primary" onclick="mxNewProject()"><i class="fa-solid fa-plus me-2"></i>New project</button>
<?php endif; ?>
</div>

<?php if ($projects): ?>
<div class="row g-3 mb-3">
<?php foreach ($projects as $pr):
    $chip = match ($pr['status']) { 'Active' => 'mx-chip-success', 'On Hold' => 'mx-chip-warning', 'Completed' => 'mx-chip-info', default => 'mx-chip-plain' };
    $pct = $pr['task_count'] > 0 ? (int)round($pr['done_count'] / $pr['task_count'] * 100) : 0;
?>
    <div class="col-12 col-md-6 col-xl-4">
        <a href="/projects/<?= (int)$pr['id'] ?>" class="mx-card d-block h-100" style="color:var(--mx-text)">
            <div class="mx-card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <strong><?= e($pr['name']) ?></strong>
                    <span class="mx-chip <?= $chip ?>"><?= e($pr['status']) ?></span>
                </div>
                <div class="text-muted mb-2" style="font-size:12px">
                    <?= e($pr['lead_first'] ? 'Lead: ' . $pr['lead_first'] . ' ' . $pr['lead_last'] : 'No lead') ?>
                    &middot; <?= (int)$pr['done_count'] ?>/<?= (int)$pr['task_count'] ?> tasks done
                </div>
                <div style="height:6px;border-radius:3px;background:var(--mx-border);overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:var(--mx-primary)"></div>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="mx-card mb-3">
    <div class="mx-empty">
        <i class="fa-solid fa-diagram-project"></i>
        <p>No projects yet. Tasks can also live outside projects.</p>
<?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="mxNewProject()">Create the first project</button>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$taskProjectId = null;
$taskProjects = $projects;
require APP_ROOT . '/app/partials/task_views.php';
?>

<script>
var mxPeopleForLead = mxTaskPeople;
function mxNewProject() {
    var body = '<form id="proj-form">' +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160"></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="2000"></textarea></div>' +
        '<div class="mb-3"><label class="form-label">Lead</label><select class="form-select" name="lead_id"><option value="">None</option>' +
        mxPeopleForLead.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('') +
        '</select></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option>On Hold</option><option>Completed</option><option>Archived</option></select></div>' +
        '</form>';
    MX.drawer.open({
        title: 'New project',
        body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
                '<button class="btn btn-primary" onclick="mxSubmitProject()">Create project</button>'
    });
}
function mxSubmitProject() {
    var form = document.getElementById('proj-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/projects', MX.formData(form))
        .then(function (d) { MX.ok('Project created.'); setTimeout(function () { location.href = '/projects/' + d.project_id; }, 600); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
