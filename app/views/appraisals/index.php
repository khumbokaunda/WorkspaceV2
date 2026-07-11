<?php
// Appraisals: my reviews, and for reviewers the cycles and every appraisal.
$stChip = fn($s) => ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Acknowledged' => 'mx-chip-success'][$s] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Appraisals</h1>
<?php if ($canManage): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="mxNewCycle()"><i class="fa-solid fa-calendar-plus me-2"></i>New cycle</button>
        <button class="btn btn-primary" onclick="mxNewAppraisal()"><i class="fa-solid fa-plus me-2"></i>New appraisal</button>
    </div>
<?php endif; ?>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>My appraisals</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$myAppraisals): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-star-half-stroke"></i><p class="mb-0">You have no appraisals yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Cycle</th><th>Overall</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($myAppraisals as $a): ?>
                <tr>
                    <td><a href="/appraisals/<?= (int)$a['id'] ?>"><?= e($a['cycle_name']) ?></a></td>
                    <td class="mx-tabular"><?= $a['overall_rating'] !== null ? e(rtrim(rtrim(number_format((float)$a['overall_rating'], 1), '0'), '.')) . ' / 5' : '' ?></td>
                    <td><span class="mx-chip <?= $stChip($a['status']) ?>"><?= e($a['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Review cycles</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$cycles): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-calendar-days"></i><p class="mb-0">No cycles yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($cycles as $c): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($c['name']) ?></strong> <span class="mx-chip <?= $c['status'] === 'Open' ? 'mx-chip-success' : 'mx-chip-plain' ?>" style="font-size:10px"><?= e($c['status']) ?></span></div>
                            <small class="text-muted"><?= e(trim(($c['start_date'] ?? '') . ' ' . ($c['end_date'] ? 'to ' . $c['end_date'] : ''))) ?> &middot; <?= (int)$c['appraisal_count'] ?> appraisals</small>
                        </div>
                        <button class="btn btn-subtle btn-sm" onclick='mxEditCycle(<?= json_encode(["id"=>(int)$c["id"],"name"=>$c["name"],"start_date"=>$c["start_date"],"end_date"=>$c["end_date"],"status"=>$c["status"]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="mx-card">
            <div class="mx-card-header"><h2>All appraisals</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$allAppraisals): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-list-check"></i><p class="mb-0">No appraisals created.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Person</th><th>Cycle</th><th>Overall</th><th>Status</th></tr></thead>
                    <tbody>
<?php foreach ($allAppraisals as $a): ?>
                        <tr>
                            <td><a href="/appraisals/<?= (int)$a['id'] ?>"><?= e($a['person_name']) ?></a></td>
                            <td><?= e($a['cycle_name']) ?></td>
                            <td class="mx-tabular"><?= $a['overall_rating'] !== null ? e(rtrim(rtrim(number_format((float)$a['overall_rating'], 1), '0'), '.')) : '' ?></td>
                            <td><span class="mx-chip <?= $stChip($a['status']) ?>"><?= e($a['status']) ?></span></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table></div>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var mxCycles = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name']], $cycles)) ?>;
var mxAppraisalPeople = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>;

function mxCycleForm(c) {
    c = c || {};
    return '<form id="cycle-form">' + (c.id ? '<input type="hidden" name="id" value="' + c.id + '">' : '') +
        '<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="160" value="' + MX.escape(c.name || '') + '" placeholder="e.g. 2026 mid-year"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Start</label><input type="date" class="form-control" name="start_date" value="' + (c.start_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">End</label><input type="date" class="form-control" name="end_date" value="' + (c.end_date || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option' + (c.status === 'Open' || !c.id ? ' selected' : '') + '>Open</option><option' + (c.status === 'Closed' ? ' selected' : '') + '>Closed</option></select></div>' +
        '</form>';
}
function mxNewCycle() { MX.drawer.open({ title: 'New review cycle', body: mxCycleForm(null), footer: mxApFoot('mxSaveCycle()') }); }
function mxEditCycle(c) { MX.drawer.open({ title: 'Edit cycle', body: mxCycleForm(c), footer: mxApFoot('mxSaveCycle()') }); }
function mxApFoot(onclick) { return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="' + onclick + '">Save</button>'; }
function mxSaveCycle() {
    var form = document.getElementById('cycle-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/appraisals/cycles', MX.formData(form))
        .then(function () { MX.drawer.close(); MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 500); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
function mxNewAppraisal() {
    if (!mxCycles.length) { MX.fail('Create a review cycle first.'); return; }
    var cycleOpts = mxCycles.map(function (c) { return '<option value="' + c.id + '">' + MX.escape(c.name) + '</option>'; }).join('');
    var peopleOpts = mxAppraisalPeople.map(function (p) { return '<option value="' + p.id + '">' + MX.escape(p.name) + '</option>'; }).join('');
    var body = '<form id="appraisal-form">' +
        '<div class="mb-3"><label class="form-label">Cycle</label><select class="form-select" name="cycle_id" required>' + cycleOpts + '</select></div>' +
        '<div class="mb-3"><label class="form-label">Person</label><select class="form-select" name="person_id" required>' + peopleOpts + '</select></div>' +
        '</form>';
    MX.drawer.open({ title: 'New appraisal', body: body, footer: mxApFoot('mxSaveAppraisal()') });
}
function mxSaveAppraisal() {
    var form = document.getElementById('appraisal-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/appraisals', MX.formData(form))
        .then(function (d) { MX.drawer.close(); if (d.appraisal_id) location.href = '/appraisals/' + d.appraisal_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
<?php endif; ?>
