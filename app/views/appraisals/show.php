<?php
// Appraisal detail: overall rating and summary, the goals with ratings, and the
// submit and acknowledge actions.
$stChip = ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Acknowledged' => 'mx-chip-success'][$appraisal['status']] ?? 'mx-chip-plain';
$rate = fn($v) => $v !== null ? rtrim(rtrim(number_format((float)$v, 1), '0'), '.') : '';
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($appraisal['person_name']) ?></h1>
                <span class="mx-chip <?= $stChip ?>"><?= e($appraisal['status']) ?></span>
            </div>
            <div class="text-muted mt-1"><?= e($appraisal['cycle_name']) ?><?= $appraisal['job_title'] ? ' &middot; ' . e($appraisal['job_title']) : '' ?><?= $appraisal['reviewer_name'] ? ' &middot; reviewer ' . e($appraisal['reviewer_name']) : '' ?></div>
        </div>
        <div class="text-end">
            <div class="mx-stat-value" style="font-size:22px"><?= $appraisal['overall_rating'] !== null ? e($rate($appraisal['overall_rating'])) . ' / 5' : '&ndash;' ?></div>
            <div class="mx-stat-label">Overall rating</div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="/appraisals" class="btn btn-subtle btn-sm">All appraisals</a>
<?php if ($canManage): ?>
    <button class="btn btn-outline-primary btn-sm" onclick="mxEditAppraisal()"><i class="fa-solid fa-pen me-1"></i>Rating and summary</button>
    <button class="btn btn-outline-primary btn-sm" onclick="mxAddGoal()"><i class="fa-solid fa-plus me-1"></i>Add goal</button>
<?php if ($appraisal['status'] === 'Draft'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxSubmit()"><i class="fa-solid fa-paper-plane me-1"></i>Submit to employee</button>
<?php endif; ?>
<?php endif; ?>
<?php if ($isOwn && $appraisal['status'] === 'Submitted'): ?>
    <button class="btn btn-primary btn-sm" onclick="mxAcknowledge()"><i class="fa-solid fa-check me-1"></i>Acknowledge</button>
<?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Goals and ratings</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$goals): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-bullseye"></i><p class="mb-0">No goals recorded.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Goal</th><th class="text-end">Rating</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
<?php foreach ($goals as $g): ?>
                        <tr>
                            <td><?= e($g['goal']) ?><?= $g['comments'] ? '<span class="text-muted d-block" style="font-size:11px">' . e($g['comments']) . '</span>' : '' ?></td>
                            <td class="text-end mx-tabular"><?= $g['rating'] !== null ? e($rate($g['rating'])) . ' / 5' : '' ?></td>
<?php if ($canManage): ?>
                            <td class="text-end"><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteGoal(<?= (int)$g['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table></div>
<?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Summary</h2></div>
            <div class="mx-card-body">
                <p class="mb-0" style="font-size:13px;white-space:pre-line"><?= e($appraisal['summary'] ?: 'No summary yet.') ?></p>
<?php if ($appraisal['acknowledged_at']): ?>
                <hr>
                <small class="text-muted">Acknowledged by the employee on <?= e(date('j M Y', strtotime($appraisal['acknowledged_at']))) ?>.</small>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var mxAppraisalId = <?= (int)$appraisal['id'] ?>;
var mxAppraisal = <?= json_encode(['overall_rating' => $appraisal['overall_rating'], 'summary' => $appraisal['summary']]) ?>;

function mxEditAppraisal() {
    var body = '<form id="ap-form">' +
        '<div class="mb-3"><label class="form-label">Overall rating (0 to 5)</label><input type="number" step="0.1" min="0" max="5" class="form-control" name="overall_rating" value="' + (mxAppraisal.overall_rating != null ? mxAppraisal.overall_rating : '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Summary</label><textarea class="form-control" name="summary" rows="5" maxlength="5000">' + MX.escape(mxAppraisal.summary || '') + '</textarea></div>' +
        '</form>';
    MX.drawer.open({ title: 'Rating and summary', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveAppraisal()">Save</button>' });
}
function mxSaveAppraisal() {
    var form = document.getElementById('ap-form');
    if (!form.reportValidity()) return;
    MX.api('PATCH', '/appraisals/' + mxAppraisalId, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxAddGoal() {
    var body = '<form id="goal-form">' +
        '<div class="mb-3"><label class="form-label">Goal</label><textarea class="form-control" name="goal" required rows="2" maxlength="500"></textarea></div>' +
        '<div class="mb-3"><label class="form-label">Rating (0 to 5)</label><input type="number" step="0.1" min="0" max="5" class="form-control" name="rating"></div>' +
        '<div class="mb-3"><label class="form-label">Comments</label><input class="form-control" name="comments" maxlength="1000"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add goal', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSaveGoal()">Add</button>' });
}
function mxSaveGoal() {
    var form = document.getElementById('goal-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/appraisals/' + mxAppraisalId + '/goals', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Added.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxDeleteGoal(id) {
    MX.confirm('Remove this goal?').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/appraisals/goals/' + id).then(function () { MX.ok('Removed.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxSubmit() {
    MX.confirm('Submit this appraisal to the employee?', 'They will be able to see and acknowledge it.').then(function (go) {
        if (!go) return;
        MX.api('POST', '/appraisals/' + mxAppraisalId + '/submit', {}).then(function () { MX.ok('Submitted.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxAcknowledge() {
    MX.confirm('Acknowledge this appraisal?', 'This records that you have seen and discussed it.').then(function (go) {
        if (!go) return;
        MX.api('POST', '/appraisals/' + mxAppraisalId + '/acknowledge', {}).then(function () { MX.ok('Acknowledged.'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
