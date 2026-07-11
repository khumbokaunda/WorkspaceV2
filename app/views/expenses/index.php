<?php
// Expenses overview: my claims and, for approvers, everyone's submitted claims.
$fmt = fn($v) => $currency . ' ' . number_format((float)$v, 2);
$chip = fn($s) => ['Draft' => 'mx-chip-plain', 'Submitted' => 'mx-chip-info', 'Approved' => 'mx-chip-success', 'Rejected' => 'mx-chip-danger', 'Reimbursed' => 'mx-chip-success'][$s] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Expenses</h1>
    <div class="d-flex gap-2">
<?php if ($canPettyCash): ?>
        <a href="/expenses/petty-cash" class="btn btn-outline-primary"><i class="fa-solid fa-coins me-2"></i>Petty cash</a>
<?php endif; ?>
        <button class="btn btn-primary" onclick="mxNewClaim()"><i class="fa-solid fa-plus me-2"></i>New claim</button>
    </div>
</div>

<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>My claims</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$myClaims): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-receipt"></i><p class="mb-0">You have no expense claims yet.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Title</th><th class="text-end">Total</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
<?php foreach ($myClaims as $c): ?>
                <tr>
                    <td><a href="/expenses/<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></td>
                    <td class="text-end mx-tabular"><?= e($fmt($c['total'])) ?></td>
                    <td><span class="mx-chip <?= $chip($c['status']) ?>"><?= e($c['status']) ?></span></td>
                    <td class="mx-tabular"><?= e(date('j M Y', strtotime($c['created_at']))) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<?php if ($canViewAll): ?>
<div class="mx-card">
    <div class="mx-card-header"><h2>All submitted claims</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$allClaims): ?>
        <div class="mx-empty py-4"><i class="fa-regular fa-folder-open"></i><p class="mb-0">No submitted claims.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Title</th><th>Claimant</th><th class="text-end">Total</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($allClaims as $c): ?>
                <tr>
                    <td><a href="/expenses/<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></td>
                    <td><?= e($c['claimant']) ?></td>
                    <td class="text-end mx-tabular"><?= e($fmt($c['total'])) ?></td>
                    <td><span class="mx-chip <?= $chip($c['status']) ?>"><?= e($c['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function mxNewClaim() {
    var body = '<form id="claim-form"><div class="mb-3"><label class="form-label">Claim title</label><input class="form-control" name="title" required maxlength="200" placeholder="e.g. Client visit, July"></div></form>';
    MX.drawer.open({ title: 'New expense claim', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitClaim()">Create</button>' });
}
function mxSubmitClaim() {
    var form = document.getElementById('claim-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/expenses', MX.formData(form))
        .then(function (d) { MX.drawer.close(); if (d.claim_id) location.href = '/expenses/' + d.claim_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
