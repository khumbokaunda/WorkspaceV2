<?php
// Tender detail: the record, the go or no-go assessment, the compliance
// requirements matrix, the document checklist assembled from the library, the
// proposed team, the evaluation criteria, the bill of quantities, and the
// securities. Writes reload the page. Internal cost and margin render only for
// holders of tenders.pricing.view.
$curr = $currency;
$fmt = function ($v) use ($curr) {
    if ($v === null || $v === '') return '';
    return $curr . ' ' . number_format((float)$v, 2);
};
$statusChip = [
    'Identified' => 'mx-chip-plain', 'Go Decision Pending' => 'mx-chip-info', 'Preparing' => 'mx-chip-info',
    'Submitted' => 'mx-chip-warning', 'Under Evaluation' => 'mx-chip-warning',
    'Won' => 'mx-chip-success', 'Lost' => 'mx-chip-danger', 'Cancelled' => 'mx-chip-plain',
][$tender['status']] ?? 'mx-chip-plain';
$mandatoryUnmet = 0;
foreach ($requirements as $r) {
    if ((int)$r['is_mandatory'] === 1 && in_array($r['our_compliance'], ['Does Not Comply', 'Not Yet Assessed'], true)) {
        $mandatoryUnmet++;
    }
}
$expiryChip = function (?string $flag) {
    if ($flag === 'Expired') return '<span class="mx-chip mx-chip-danger" style="font-size:10px">Expired</span>';
    if ($flag === 'Expiring') return '<span class="mx-chip mx-chip-warning" style="font-size:10px">Expiring</span>';
    return '';
};
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($tender['title']) ?></h1>
                <span class="mx-chip <?= $statusChip ?>"><?= e($tender['status']) ?></span>
<?php if ($mandatoryUnmet > 0): ?>
                <span class="mx-chip mx-chip-danger"><?= $mandatoryUnmet ?> mandatory unmet</span>
<?php endif; ?>
            </div>
            <div class="text-muted mt-1">
                <?= $tender['reference_number'] ? '<span class="mx-mono">' . e($tender['reference_number']) . '</span> &middot; ' : '' ?>
                <?= e($tender['client_name'] ?: 'No client') ?><?= $tender['category'] ? ' &middot; ' . e($tender['category']) : '' ?>
            </div>
<?php if ($tender['closing_date']):
        $daysLeft = (int)floor((strtotime($tender['closing_date']) - time()) / 86400);
        $open = !in_array($tender['status'], ['Won', 'Lost', 'Cancelled'], true);
        $col = $daysLeft < 0 ? 'var(--mx-danger)' : ($daysLeft <= 3 ? 'var(--mx-warning)' : 'var(--mx-success)'); ?>
            <div class="mt-2" style="font-size:13px">
                <i class="fa-regular fa-clock me-1"></i>Closes <strong class="mx-tabular"><?= e($tender['closing_date']) ?></strong>
<?php if ($open): ?>
                <span class="mx-chip" style="background:transparent;color:<?= $col ?>;border:1px solid <?= $col ?>"><?= $daysLeft < 0 ? 'Closed' : ($daysLeft === 0 ? 'Closes today' : $daysLeft . ' days left') ?></span>
<?php endif; ?>
            </div>
<?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/tenders/<?= (int)$tender['id'] ?>/pack" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fa-solid fa-file-pdf me-2"></i>Tender pack</a>
<?php if ($canManage): ?>
            <button class="btn btn-outline-primary" onclick="mxEditTender()"><i class="fa-solid fa-pen me-2"></i>Edit</button>
            <button class="btn btn-primary" onclick="mxOutcome()"><i class="fa-solid fa-flag-checkered me-2"></i>Record outcome</button>
            <button class="btn btn-subtle" style="color:var(--mx-danger)" onclick="mxDeleteTender()" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <!-- Overview -->
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Overview</h2></div>
            <div class="mx-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Source</dt><dd class="col-7"><?= e($tender['source']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Submission</dt><dd class="col-7"><?= e($tender['submission_method']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Issued</dt><dd class="col-7"><?= e($tender['issue_date'] ?: 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Validity</dt><dd class="col-7"><?= $tender['tender_validity_days'] !== null ? (int)$tender['tender_validity_days'] . ' days' : 'Not set' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Clarification by</dt><dd class="col-7"><?= e($tender['clarification_deadline'] ?: 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Site visit</dt><dd class="col-7"><?= e($tender['site_visit_at'] ?: 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Estimated value</dt><dd class="col-7 mx-tabular"><?= e($fmt($tender['estimated_value']) ?: 'Not set') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Bid owner</dt><dd class="col-7 mb-0"><?= e($tender['owner_name'] ?: 'Unassigned') ?></dd>
                </dl>
<?php if (!empty($tender['description'])): ?>
                <hr>
                <p class="mb-0" style="font-size:13px;white-space:pre-line"><?= e($tender['description']) ?></p>
<?php endif; ?>
<?php if (!empty($tender['outcome_notes']) || $tender['award_value'] !== null): ?>
                <hr>
                <div style="font-size:13px"><strong>Outcome.</strong> <?= $tender['award_value'] !== null ? 'Award ' . e($fmt($tender['award_value'])) . '. ' : '' ?><?= e($tender['outcome_notes'] ?: '') ?></div>
<?php endif; ?>
            </div>
        </div>

        <!-- Go or No-Go -->
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Go or No-Go</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxGoAssess()"><i class="fa-solid fa-scale-balanced me-1"></i>Assess</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body">
<?php if (!$go): ?>
                <p class="text-muted mb-0" style="font-size:13px">No assessment yet. A short go or no-go check avoids wasting effort on an unwinnable tender.</p>
<?php else:
        $decChip = ['Go' => 'mx-chip-success', 'No-Go' => 'mx-chip-danger', 'Pending' => 'mx-chip-warning'][$go['decision']] ?? 'mx-chip-plain';
        $qs = [
            'meets_eligibility' => 'Meets mandatory eligibility', 'has_authorizations' => 'Have the authorizations',
            'can_meet_delivery' => 'Can meet the delivery period', 'value_worth_effort' => 'Value is worth the effort',
            'has_experience' => 'Have the required experience',
        ]; ?>
                <div class="mb-2"><span class="mx-chip <?= $decChip ?>">Decision: <?= e($go['decision']) ?></span></div>
                <dl class="row mb-0" style="font-size:13px">
<?php foreach ($qs as $k => $label):
        $v = $go[$k]; $vc = $v === 'Yes' ? 'var(--mx-success)' : ($v === 'No' ? 'var(--mx-danger)' : 'var(--mx-muted)'); ?>
                    <dt class="col-8 text-muted fw-normal"><?= e($label) ?></dt><dd class="col-4" style="color:<?= $vc ?>"><?= e($v ?: 'Not set') ?></dd>
<?php endforeach; ?>
                </dl>
<?php if (!empty($go['rationale'])): ?>
                <p class="mb-0 mt-2" style="font-size:13px;white-space:pre-line"><?= e($go['rationale']) ?></p>
<?php endif; ?>
<?php endif; ?>
            </div>
        </div>

        <!-- Securities -->
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Securities</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewSecurity()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$securities): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-shield-halved"></i><p class="mb-0">No securities recorded.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($securities as $s):
        $flag = expiry_status($s['expiry_date']);
        $statChip = ['Active' => 'mx-chip-success', 'Returned' => 'mx-chip-info', 'Forfeited' => 'mx-chip-danger', 'Released' => 'mx-chip-plain'][$s['status']] ?? 'mx-chip-plain'; ?>
                    <li class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($s['security_type']) ?></strong> <span class="mx-chip <?= $statChip ?>"><?= e($s['status']) ?></span> <?= $flag === 'Expired' ? '<span class="mx-chip mx-chip-danger" style="font-size:10px">Expired</span>' : ($flag === 'Expiring' ? '<span class="mx-chip mx-chip-warning" style="font-size:10px">Expiring</span>' : '') ?></div>
                            <small class="text-muted"><?= e($fmt($s['amount'])) ?><?= $s['form'] ? ' &middot; ' . e($s['form']) : '' ?><?= $s['expiry_date'] ? ' &middot; expires ' . e($s['expiry_date']) : '' ?></small>
                        </div>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxEditSecurity(<?= (int)$s['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteSecurity(<?= (int)$s['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <!-- Requirements matrix -->
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Compliance matrix<?php if ($mandatoryUnmet > 0): ?> <span class="mx-chip mx-chip-danger" style="font-size:11px"><?= $mandatoryUnmet ?> mandatory unmet</span><?php endif; ?></h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewReq()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$requirements): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-list-check"></i><p class="mb-0">No requirements yet. One unmet mandatory item disqualifies the whole bid, so list and check each here.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Requirement</th><th>Category</th><th>Compliance</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
<?php foreach ($requirements as $r):
        $cc = ['Complies' => 'mx-chip-success', 'Partial' => 'mx-chip-warning', 'Does Not Comply' => 'mx-chip-danger', 'Not Yet Assessed' => 'mx-chip-plain'][$r['our_compliance']] ?? 'mx-chip-plain'; ?>
                        <tr>
                            <td><?= (int)$r['is_mandatory'] === 1 ? '<span title="Mandatory" style="color:var(--mx-danger)">*</span> ' : '' ?><?= e($r['requirement_text']) ?><?= $r['evidence_reference'] ? '<span class="text-muted d-block" style="font-size:11px">Evidence: ' . e($r['evidence_reference']) . '</span>' : '' ?></td>
                            <td><?= e($r['category']) ?></td>
                            <td><span class="mx-chip <?= $cc ?>"><?= e($r['our_compliance']) ?></span></td>
<?php if ($canManage): ?>
                            <td class="text-nowrap"><button class="btn btn-subtle btn-sm" onclick="mxEditReq(<?= (int)$r['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteReq(<?= (int)$r['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table></div>
<?php endif; ?>
            </div>
        </div>

        <!-- Document checklist -->
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Document checklist</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewDoc()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$checklist): ?>
                <div class="mx-empty py-4"><i class="fa-regular fa-square-check"></i><p class="mb-0">No checklist items. Attaching a library document links the existing row rather than uploading again.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($checklist as $d): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <i class="fa-solid <?= (int)$d['is_ready'] === 1 ? 'fa-circle-check' : 'fa-circle' ?>" style="color:<?= (int)$d['is_ready'] === 1 ? 'var(--mx-success)' : 'var(--mx-muted)' ?>"></i>
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><?= e($d['item_label']) ?> <?= $expiryChip($d['expiry_flag']) ?></div>
                            <small class="text-muted"><?= e($d['source_kind']) ?><?= $d['source_label'] ? ': ' . e($d['source_label']) : '' ?><?= $d['notes'] ? ' &middot; ' . e($d['notes']) : '' ?></small>
                        </div>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" onclick="mxEditDoc(<?= (int)$d['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteDoc(<?= (int)$d['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>

        <!-- Proposed team -->
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Proposed team</h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewTeam()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$team): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-people-group"></i><p class="mb-0">No team assigned. Adding a person surfaces their CV and certifications for attachment.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($team as $m): ?>
                    <li class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <a href="/people/<?= (int)$m['person_id'] ?>" class="mx-avatar" aria-label="Open profile"><?= e(strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1))) ?></a>
                        <div class="flex-grow-1">
                            <div style="font-size:13px"><strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong> as <?= e($m['proposed_role']) ?></div>
                            <small class="text-muted"><?= e($m['job_title'] ?: '') ?> &middot; <?= (int)$m['cv_count'] ?> CV, <?= (int)$m['cert_count'] ?> certifications</small>
                        </div>
<?php if ($canManage): ?>
                        <button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxRemoveTeam(<?= (int)$m['id'] ?>)" aria-label="Remove"><i class="fa-solid fa-user-minus"></i></button>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
        </div>

        <!-- Evaluation criteria -->
<?php $totMax = 0; $totScore = 0; foreach ($criteria as $c) { $totMax += (float)$c['max_points']; $totScore += (float)($c['self_score'] ?? 0); } ?>
        <div class="mx-card mb-3">
            <div class="mx-card-header">
                <h2>Evaluation criteria<?php if ($criteria): ?> <span class="text-muted" style="font-size:12px">self score <?= rtrim(rtrim(number_format($totScore, 2), '0'), '.') ?> of <?= rtrim(rtrim(number_format($totMax, 2), '0'), '.') ?></span><?php endif; ?></h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewCrit()"><i class="fa-solid fa-plus me-1"></i>Add</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$criteria): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-star-half-stroke"></i><p class="mb-0">No criteria yet. Record the scoring scheme to self assess the likely technical score before submitting.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr><th>Criterion</th><th class="text-end">Max</th><th class="text-end">Self score</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
<?php foreach ($criteria as $c): ?>
                        <tr>
                            <td><?= e($c['criterion']) ?><?= $c['our_evidence'] ? '<span class="text-muted d-block" style="font-size:11px">' . e($c['our_evidence']) . '</span>' : '' ?></td>
                            <td class="text-end mx-tabular"><?= rtrim(rtrim(number_format((float)$c['max_points'], 2), '0'), '.') ?></td>
                            <td class="text-end mx-tabular"><?= $c['self_score'] !== null ? rtrim(rtrim(number_format((float)$c['self_score'], 2), '0'), '.') : '' ?></td>
<?php if ($canManage): ?>
                            <td class="text-nowrap"><button class="btn btn-subtle btn-sm" onclick="mxEditCrit(<?= (int)$c['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteCrit(<?= (int)$c['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table></div>
<?php endif; ?>
            </div>
        </div>

        <!-- Bill of quantities -->
        <div class="mx-card">
            <div class="mx-card-header">
                <h2>Bill of quantities<?php if (!$canPrice): ?> <span class="mx-chip mx-chip-plain" style="font-size:10px">prices only</span><?php endif; ?></h2>
<?php if ($canManage): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="mxNewBoq()"><i class="fa-solid fa-plus me-1"></i>Add line</button>
<?php endif; ?>
            </div>
            <div class="mx-card-body mx-flush">
<?php if (!$boq): ?>
                <div class="mx-empty py-4"><i class="fa-solid fa-table-list"></i><p class="mb-0">No priced lines yet.</p></div>
<?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
                    <thead><tr>
                        <th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th>
<?php if ($canPrice): ?><th class="text-end">Unit cost</th><th class="text-end">Margin</th><?php endif; ?>
                        <th class="text-end">Line total</th><?php if ($canManage): ?><th></th><?php endif; ?>
                    </tr></thead>
                    <tbody>
<?php foreach ($boq as $b): ?>
                        <tr>
                            <td><?= $b['item_no'] ? '<span class="mx-mono text-muted">' . e($b['item_no']) . '</span> ' : '' ?><?= e($b['description']) ?><?= (int)$b['is_optional'] === 1 ? ' <span class="mx-chip mx-chip-plain" style="font-size:10px">optional</span>' : '' ?><?= $b['specification'] ? '<span class="text-muted d-block" style="font-size:11px">' . e($b['specification']) . '</span>' : '' ?></td>
                            <td class="text-end mx-tabular"><?= rtrim(rtrim(number_format((float)$b['quantity'], 2), '0'), '.') ?><?= $b['unit'] ? ' ' . e($b['unit']) : '' ?></td>
                            <td class="text-end mx-tabular"><?= e($fmt($b['unit_price'])) ?></td>
<?php if ($canPrice): ?>
                            <td class="text-end mx-tabular text-muted"><?= $b['unit_cost'] !== null ? e($fmt($b['unit_cost'])) : '' ?></td>
                            <td class="text-end mx-tabular"><?= $b['line_cost'] !== null ? e($fmt($b['line_total'] - $b['line_cost'])) : '' ?></td>
<?php endif; ?>
                            <td class="text-end mx-tabular"><?= e($fmt($b['line_total'])) ?></td>
<?php if ($canManage): ?>
                            <td class="text-nowrap"><button class="btn btn-subtle btn-sm" onclick="mxEditBoq(<?= (int)$b['id'] ?>)" aria-label="Edit"><i class="fa-solid fa-pen"></i></button><button class="btn btn-subtle btn-sm" style="color:var(--mx-danger)" onclick="mxDeleteBoq(<?= (int)$b['id'] ?>)" aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button></td>
<?php endif; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="<?= $canPrice ? 5 : 3 ?>" class="text-end">Subtotal</th><th class="text-end mx-tabular"><?= e($fmt($boqTotals['subtotal'])) ?></th><?php if ($canManage): ?><th></th><?php endif; ?></tr>
<?php if ($tender['vat_percent'] !== null): ?>
                        <tr><td colspan="<?= $canPrice ? 5 : 3 ?>" class="text-end text-muted">VAT (<?= rtrim(rtrim(number_format((float)$tender['vat_percent'], 2), '0'), '.') ?>%)</td><td class="text-end mx-tabular"><?= e($fmt($boqTotals['vat'])) ?></td><?php if ($canManage): ?><td></td><?php endif; ?></tr>
<?php endif; ?>
                        <tr><th colspan="<?= $canPrice ? 5 : 3 ?>" class="text-end">Grand total</th><th class="text-end mx-tabular"><?= e($fmt($boqTotals['grand_total'])) ?></th><?php if ($canManage): ?><th></th><?php endif; ?></tr>
<?php if ($boqTotals['optional'] > 0): ?>
                        <tr><td colspan="<?= $canPrice ? 5 : 3 ?>" class="text-end text-muted">Optional lines (not in total)</td><td class="text-end mx-tabular text-muted"><?= e($fmt($boqTotals['optional'])) ?></td><?php if ($canManage): ?><td></td><?php endif; ?></tr>
<?php endif; ?>
<?php if ($canPrice): ?>
                        <tr><td colspan="5" class="text-end text-muted">Margin (<?= rtrim(rtrim(number_format($boqTotals['margin_percent'], 1), '0'), '.') ?>%)</td><td class="text-end mx-tabular" style="color:var(--mx-success)"><?= e($fmt($boqTotals['margin'])) ?></td><?php if ($canManage): ?><td></td><?php endif; ?></tr>
<?php endif; ?>
                    </tfoot>
                </table></div>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var mxTenderId = <?= (int)$tender['id'] ?>;
var mxCanManage = <?= $canManage ? 'true' : 'false' ?>;
var mxCanPrice = <?= $canPrice ? 'true' : 'false' ?>;
var mxCurrency = <?= json_encode($curr) ?>;
var mxProjectsEnabled = <?= $projectsEnabled ? 'true' : 'false' ?>;
// Vocabulary and library options
var mxV = {
    statuses: <?= json_encode(array_values($statuses)) ?>,
    sources: <?= json_encode(array_values($sources)) ?>,
    methods: <?= json_encode(array_values($submissionMethods)) ?>,
    reqCats: <?= json_encode(array_values($requirementCategories)) ?>,
    compliance: <?= json_encode(array_values($complianceStates)) ?>,
    docKinds: <?= json_encode(array_values($docSourceKinds)) ?>,
    secTypes: <?= json_encode(array_values($securityTypes)) ?>,
    secForms: <?= json_encode(array_values($securityForms)) ?>,
    secStatuses: <?= json_encode(array_values($securityStatuses)) ?>,
    yesno: <?= json_encode(array_values($yesno)) ?>,
    goDecisions: <?= json_encode(array_values($goDecisions)) ?>
};
var mxLib = {
    clients: <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name']], $clients)) ?>,
    owners: <?= json_encode(array_map(fn($o) => ['id' => (int)$o['id'], 'name' => trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: $o['username']], $owners)) ?>,
    people: <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['first_name'] . ' ' . $p['last_name']], $people)) ?>,
    companyDocs: <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'label' => $d['doc_type'] . ': ' . $d['title']], $companyDocs)) ?>,
    authorizations: <?= json_encode(array_map(fn($a) => ['id' => (int)$a['id'], 'label' => $a['supplier_name'] . ' - ' . $a['product_line']], $authorizations)) ?>,
    certs: <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'label' => $c['first_name'] . ' ' . $c['last_name'] . ' - ' . $c['code']], $certOptions)) ?>
};
// Records for editing, keyed by id
var mxRec = {
    tender: <?= json_encode([
        'reference_number' => $tender['reference_number'], 'title' => $tender['title'], 'client_id' => $tender['client_id'] !== null ? (int)$tender['client_id'] : null,
        'category' => $tender['category'], 'description' => $tender['description'], 'source' => $tender['source'],
        'issue_date' => $tender['issue_date'], 'closing_date' => $tender['closing_date'], 'tender_validity_days' => $tender['tender_validity_days'],
        'clarification_deadline' => $tender['clarification_deadline'], 'site_visit_at' => $tender['site_visit_at'],
        'submission_method' => $tender['submission_method'], 'estimated_value' => $tender['estimated_value'], 'currency' => $tender['currency'],
        'vat_percent' => $tender['vat_percent'], 'bid_owner_id' => $tender['bid_owner_id'] !== null ? (int)$tender['bid_owner_id'] : null, 'status' => $tender['status'],
    ]) ?>,
    go: <?= json_encode($go ?: new stdClass()) ?>,
    reqs: <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'category' => $r['category'], 'requirement_text' => $r['requirement_text'], 'is_mandatory' => (int)$r['is_mandatory'], 'our_compliance' => $r['our_compliance'], 'evidence_reference' => $r['evidence_reference'], 'remarks' => $r['remarks']], $requirements)) ?>,
    docs: <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'item_label' => $d['item_label'], 'source_kind' => $d['source_kind'], 'source_id' => $d['source_id'] !== null ? (int)$d['source_id'] : null, 'is_ready' => (int)$d['is_ready'], 'notes' => $d['notes']], $checklist)) ?>,
    crits: <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'criterion' => $c['criterion'], 'max_points' => $c['max_points'], 'weight' => $c['weight'], 'our_evidence' => $c['our_evidence'], 'self_score' => $c['self_score']], $criteria)) ?>,
    secs: <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'security_type' => $s['security_type'], 'amount' => $s['amount'], 'currency' => $s['currency'], 'form' => $s['form'], 'issuing_institution' => $s['issuing_institution'], 'issue_date' => $s['issue_date'], 'expiry_date' => $s['expiry_date'], 'status' => $s['status'], 'reference' => $s['reference']], $securities)) ?>,
    boq: <?= json_encode(array_map(fn($b) => array_merge([
        'id' => (int)$b['id'], 'item_no' => $b['item_no'], 'description' => $b['description'], 'specification' => $b['specification'],
        'quantity' => $b['quantity'], 'unit' => $b['unit'], 'unit_price' => $b['unit_price'], 'is_optional' => (int)$b['is_optional'],
    ], $canPrice ? ['unit_cost' => $b['unit_cost'], 'markup_percent' => $b['markup_percent']] : []), $boq)) ?>
};

function mxReload() { setTimeout(function () { location.reload(); }, 500); }
function mxSel(name, opts, cur, def) {
    return '<select class="form-select" name="' + name + '">' + opts.map(function (o) {
        return '<option' + ((cur || def) === o ? ' selected' : '') + '>' + MX.escape(o) + '</option>';
    }).join('') + '</select>';
}
function mxOptSel(name, opts, cur, placeholder) {
    return '<select class="form-select" name="' + name + '"><option value="">' + MX.escape(placeholder || 'None') + '</option>' +
        opts.map(function (o) { return '<option value="' + o.id + '"' + (cur == o.id ? ' selected' : '') + '>' + MX.escape(o.name || o.label) + '</option>'; }).join('') + '</select>';
}
function mxById(list, id) { return list.find(function (x) { return x.id == id; }); }
function mxSubmit(formId, method, url, onOk) {
    var form = document.getElementById(formId);
    if (!form.reportValidity()) return;
    MX.api(method, url, MX.formData(form))
        .then(function (d) { MX.drawer.close(); MX.ok('Saved.'); (onOk || mxReload)(d); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}

// ---- tender record ----
function mxEditTender() {
    var t = mxRec.tender;
    var dt = function (v) { return v ? String(v).replace(' ', 'T').slice(0, 16) : ''; };
    var body = '<form id="te-form">' +
        '<div class="row g-2 mb-3"><div class="col-8"><label class="form-label">Title</label><input class="form-control" name="title" required maxlength="250" value="' + MX.escape(t.title || '') + '"></div>' +
        '<div class="col-4"><label class="form-label">Reference</label><input class="form-control mx-mono" name="reference_number" maxlength="120" value="' + MX.escape(t.reference_number || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Client</label>' + mxOptSel('client_id', mxLib.clients, t.client_id, 'No client') + '</div>' +
        '<div class="col"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="120" value="' + MX.escape(t.category || '') + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Source</label>' + mxSel('source', mxV.sources, t.source) + '</div>' +
        '<div class="col"><label class="form-label">Submission</label>' + mxSel('submission_method', mxV.methods, t.submission_method) + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Issue date</label><input type="date" class="form-control" name="issue_date" value="' + (t.issue_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Closing</label><input type="datetime-local" class="form-control" name="closing_date" value="' + dt(t.closing_date) + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Validity (days)</label><input type="number" min="0" class="form-control" name="tender_validity_days" value="' + (t.tender_validity_days != null ? t.tender_validity_days : '') + '"></div>' +
        '<div class="col"><label class="form-label">Clarification</label><input type="datetime-local" class="form-control" name="clarification_deadline" value="' + dt(t.clarification_deadline) + '"></div></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Site visit</label><input type="datetime-local" class="form-control" name="site_visit_at" value="' + dt(t.site_visit_at) + '"></div>' +
        '<div class="col"><label class="form-label">Status</label>' + mxSel('status', mxV.statuses, t.status) + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col-5"><label class="form-label">Estimated value</label><input type="number" step="0.01" min="0" class="form-control" name="estimated_value" value="' + (t.estimated_value != null ? t.estimated_value : '') + '"></div>' +
        '<div class="col-3"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" value="' + MX.escape(t.currency || '') + '"></div>' +
        '<div class="col-4"><label class="form-label">VAT percent</label><input type="number" step="0.01" min="0" class="form-control" name="vat_percent" value="' + (t.vat_percent != null ? t.vat_percent : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Bid owner</label>' + mxOptSel('bid_owner_id', mxLib.owners, t.bid_owner_id, 'Unassigned') + '</div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="5000">' + MX.escape(t.description || '') + '</textarea></div>' +
        '</form>';
    MX.drawer.open({ title: 'Edit tender', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmit(\'te-form\',\'PATCH\',\'/tenders/' + mxTenderId + '\')">Save changes</button>' });
}
function mxDeleteTender() {
    MX.confirm('Delete this tender?', 'All its bid content is removed permanently.').then(function (go) {
        if (!go) return;
        MX.api('DELETE', '/tenders/' + mxTenderId).then(function () { MX.ok('Deleted.'); setTimeout(function () { location.href = '/tenders'; }, 500); }).catch(function (e) { MX.fail(e.message); });
    });
}
function mxOutcome() {
    var body = '<form id="oc-form">' +
        '<div class="mb-3"><label class="form-label">Outcome</label><select class="form-select" name="status"><option>Won</option><option>Lost</option><option>Cancelled</option></select></div>' +
        '<div class="mb-3"><label class="form-label">Award or final value</label><input type="number" step="0.01" min="0" class="form-control" name="award_value"></div>' +
        '<div class="mb-3"><label class="form-label">Debrief or reasons</label><textarea class="form-control" name="outcome_notes" rows="3" maxlength="5000"></textarea></div>' +
        (mxProjectsEnabled ? '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="create_project" id="oc-proj"><label class="form-check-label" for="oc-proj">On a win, create a delivery project</label></div>' : '') +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="create_performance_security" id="oc-sec"><label class="form-check-label" for="oc-sec">On a win, add a performance security</label></div>' +
        '</form>';
    MX.drawer.open({ title: 'Record outcome', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmit(\'oc-form\',\'POST\',\'/tenders/' + mxTenderId + '/outcome\')">Record</button>' });
}

// ---- go/no-go ----
function mxGoAssess() {
    var g = mxRec.go || {};
    var yn = function (name, label) {
        return '<div class="row g-2 mb-2 align-items-center"><div class="col-8" style="font-size:13px">' + label + '</div><div class="col-4">' + mxSel(name, ['', 'Yes', 'No', 'Unsure'], g[name] || '') + '</div></div>';
    };
    var body = '<form id="go-form">' +
        yn('meets_eligibility', 'Meets mandatory eligibility') + yn('has_authorizations', 'Have the manufacturer authorizations') +
        yn('can_meet_delivery', 'Can meet the delivery period') + yn('value_worth_effort', 'Value is worth the effort') +
        yn('has_experience', 'Have the required past experience') +
        '<div class="mb-3 mt-2"><label class="form-label">Decision</label>' + mxSel('decision', mxV.goDecisions, g.decision, 'Pending') + '</div>' +
        '<div class="mb-3"><label class="form-label">Rationale</label><textarea class="form-control" name="rationale" rows="3" maxlength="3000">' + MX.escape(g.rationale || '') + '</textarea></div>' +
        '</form>';
    MX.drawer.open({ title: 'Go or No-Go assessment', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmit(\'go-form\',\'POST\',\'/tenders/' + mxTenderId + '/go-assessment\')">Save</button>' });
}

// ---- requirements ----
function mxReqForm(r) {
    r = r || {};
    return '<form id="req-form">' +
        '<div class="mb-3"><label class="form-label">Requirement</label><textarea class="form-control" name="requirement_text" required rows="2" maxlength="1000">' + MX.escape(r.requirement_text || '') + '</textarea></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Category</label>' + mxSel('category', mxV.reqCats, r.category, 'Eligibility') + '</div>' +
        '<div class="col"><label class="form-label">Compliance</label>' + mxSel('our_compliance', mxV.compliance, r.our_compliance, 'Not Yet Assessed') + '</div></div>' +
        '<div class="mb-3"><label class="form-label">Evidence reference</label><input class="form-control" name="evidence_reference" maxlength="500" value="' + MX.escape(r.evidence_reference || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Remarks</label><input class="form-control" name="remarks" maxlength="1000" value="' + MX.escape(r.remarks || '') + '"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_mandatory" id="req-mand"' + (!r.id || r.is_mandatory ? ' checked' : '') + '><label class="form-check-label" for="req-mand">Mandatory (one unmet disqualifies the bid)</label></div>' +
        '</form>';
}
function mxNewReq() { MX.drawer.open({ title: 'Add requirement', body: mxReqForm(null), footer: mxFoot('mxSubmit(\'req-form\',\'POST\',\'/tenders/' + mxTenderId + '/requirements\')') }); }
function mxEditReq(id) { MX.drawer.open({ title: 'Edit requirement', body: mxReqForm(mxById(mxRec.reqs, id)), footer: mxFoot('mxSubmit(\'req-form\',\'PATCH\',\'/tenders/requirements/' + id + '\')') }); }
function mxDeleteReq(id) { mxDel('/tenders/requirements/' + id, 'Remove this requirement?'); }

// ---- checklist ----
function mxDocForm(d) {
    d = d || {};
    var kindOptions = mxV.docKinds;
    var srcSel = function (kind, cur) {
        var list = kind === 'Company Document' ? mxLib.companyDocs : kind === 'Manufacturer Authorization' ? mxLib.authorizations :
                   kind === 'Person CV' ? mxLib.people : kind === 'Certification' ? mxLib.certs : [];
        if (!list.length) return '';
        return '<div class="mb-3" id="doc-src-wrap"><label class="form-label">Library item</label>' + mxOptSel('source_id', list, cur, 'Choose one') + '</div>';
    };
    var cur = d.source_kind || 'Standard Form';
    return '<form id="doc-form">' +
        '<div class="mb-3"><label class="form-label">Checklist item</label><input class="form-control" name="item_label" required maxlength="250" value="' + MX.escape(d.item_label || '') + '"></div>' +
        '<div class="mb-3"><label class="form-label">Source</label><select class="form-select" name="source_kind" onchange="mxDocKindChange(this.value)">' +
        kindOptions.map(function (k) { return '<option' + (cur === k ? ' selected' : '') + '>' + MX.escape(k) + '</option>'; }).join('') + '</select></div>' +
        '<div id="doc-src-mount">' + srcSel(cur, d.source_id) + '</div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="' + MX.escape(d.notes || '') + '"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_ready" id="doc-ready"' + (d.is_ready ? ' checked' : '') + '><label class="form-check-label" for="doc-ready">Prepared or attached</label></div>' +
        '</form>';
}
function mxDocKindChange(kind) {
    var list = kind === 'Company Document' ? mxLib.companyDocs : kind === 'Manufacturer Authorization' ? mxLib.authorizations :
               kind === 'Person CV' ? mxLib.people : kind === 'Certification' ? mxLib.certs : [];
    var mount = document.getElementById('doc-src-mount');
    mount.innerHTML = list.length ? '<div class="mb-3"><label class="form-label">Library item</label>' + mxOptSel('source_id', list, '', 'Choose one') + '</div>' : '';
}
function mxNewDoc() { MX.drawer.open({ title: 'Add checklist item', body: mxDocForm(null), footer: mxFoot('mxSubmit(\'doc-form\',\'POST\',\'/tenders/' + mxTenderId + '/documents\')') }); }
function mxEditDoc(id) { MX.drawer.open({ title: 'Edit checklist item', body: mxDocForm(mxById(mxRec.docs, id)), footer: mxFoot('mxSubmit(\'doc-form\',\'PATCH\',\'/tenders/documents/' + id + '\')') }); }
function mxDeleteDoc(id) { mxDel('/tenders/documents/' + id, 'Remove this checklist item?'); }

// ---- team ----
function mxNewTeam() {
    var body = '<form id="team-form">' +
        '<div class="mb-3"><label class="form-label">Person</label>' + mxOptSel('person_id', mxLib.people, '', 'Choose a person') + '</div>' +
        '<div class="mb-3"><label class="form-label">Proposed role</label><input class="form-control" name="proposed_role" required maxlength="120" placeholder="Team Leader, Network Engineer"></div>' +
        '<div class="mb-3"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500"></div>' +
        '</form>';
    MX.drawer.open({ title: 'Add team member', body: body, footer: mxFoot('mxSubmit(\'team-form\',\'POST\',\'/tenders/' + mxTenderId + '/team\')') });
}
function mxRemoveTeam(id) { mxDel('/tenders/team/' + id, 'Remove this team member?'); }

// ---- criteria ----
function mxCritForm(c) {
    c = c || {};
    return '<form id="crit-form">' +
        '<div class="mb-3"><label class="form-label">Criterion</label><textarea class="form-control" name="criterion" required rows="2" maxlength="500">' + MX.escape(c.criterion || '') + '</textarea></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Max points</label><input type="number" step="0.01" min="0" class="form-control" name="max_points" value="' + (c.max_points != null ? c.max_points : '') + '"></div>' +
        '<div class="col"><label class="form-label">Weight</label><input type="number" step="0.01" min="0" class="form-control" name="weight" value="' + (c.weight != null ? c.weight : '') + '"></div>' +
        '<div class="col"><label class="form-label">Self score</label><input type="number" step="0.01" min="0" class="form-control" name="self_score" value="' + (c.self_score != null ? c.self_score : '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Our evidence</label><input class="form-control" name="our_evidence" maxlength="1000" value="' + MX.escape(c.our_evidence || '') + '"></div>' +
        '</form>';
}
function mxNewCrit() { MX.drawer.open({ title: 'Add criterion', body: mxCritForm(null), footer: mxFoot('mxSubmit(\'crit-form\',\'POST\',\'/tenders/' + mxTenderId + '/criteria\')') }); }
function mxEditCrit(id) { MX.drawer.open({ title: 'Edit criterion', body: mxCritForm(mxById(mxRec.crits, id)), footer: mxFoot('mxSubmit(\'crit-form\',\'PATCH\',\'/tenders/criteria/' + id + '\')') }); }
function mxDeleteCrit(id) { mxDel('/tenders/criteria/' + id, 'Remove this criterion?'); }

// ---- boq ----
function mxBoqForm(b) {
    b = b || {};
    var priceFields = mxCanPrice
        ? '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Unit cost</label><input type="number" step="0.01" min="0" class="form-control" name="unit_cost" value="' + (b.unit_cost != null ? b.unit_cost : '') + '"></div>' +
          '<div class="col"><label class="form-label">Markup percent</label><input type="number" step="0.01" class="form-control" name="markup_percent" value="' + (b.markup_percent != null ? b.markup_percent : '') + '"></div></div>'
        : '';
    return '<form id="boq-form">' +
        '<div class="row g-2 mb-3"><div class="col-3"><label class="form-label">Item no</label><input class="form-control mx-mono" name="item_no" maxlength="40" value="' + MX.escape(b.item_no || '') + '"></div>' +
        '<div class="col-9"><label class="form-label">Description</label><input class="form-control" name="description" required maxlength="500" value="' + MX.escape(b.description || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Specification</label><input class="form-control" name="specification" maxlength="1000" value="' + MX.escape(b.specification || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Quantity</label><input type="number" step="0.01" min="0" class="form-control" name="quantity" value="' + (b.quantity != null ? b.quantity : 1) + '"></div>' +
        '<div class="col"><label class="form-label">Unit</label><input class="form-control" name="unit" maxlength="40" value="' + MX.escape(b.unit || '') + '"></div></div>' +
        priceFields +
        '<div class="mb-3"><label class="form-label">Unit price' + (mxCanPrice ? ' (leave blank to derive from cost and markup)' : '') + '</label><input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="' + (b.unit_price != null ? b.unit_price : '') + '"></div>' +
        '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_optional" id="boq-opt"' + (b.is_optional ? ' checked' : '') + '><label class="form-check-label" for="boq-opt">Optional line (not counted in the base total)</label></div>' +
        '</form>';
}
function mxNewBoq() { MX.drawer.open({ title: 'Add BoQ line', body: mxBoqForm(null), footer: mxFoot('mxSubmit(\'boq-form\',\'POST\',\'/tenders/' + mxTenderId + '/boq\')') }); }
function mxEditBoq(id) { MX.drawer.open({ title: 'Edit BoQ line', body: mxBoqForm(mxById(mxRec.boq, id)), footer: mxFoot('mxSubmit(\'boq-form\',\'PATCH\',\'/tenders/boq/' + id + '\')') }); }
function mxDeleteBoq(id) { mxDel('/tenders/boq/' + id, 'Remove this line?'); }

// ---- securities ----
function mxSecForm(s) {
    s = s || {};
    return '<form id="sec-form">' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Type</label>' + mxSel('security_type', mxV.secTypes, s.security_type, 'Bid Security') + '</div>' +
        '<div class="col"><label class="form-label">Status</label>' + mxSel('status', mxV.secStatuses, s.status, 'Active') + '</div></div>' +
        '<div class="row g-2 mb-3"><div class="col-7"><label class="form-label">Amount</label><input type="number" step="0.01" min="0" class="form-control" name="amount" value="' + (s.amount != null ? s.amount : '') + '"></div>' +
        '<div class="col-5"><label class="form-label">Currency</label><input class="form-control mx-mono" name="currency" maxlength="3" value="' + MX.escape(s.currency || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Form</label>' + mxSel('form', ['', 'Bank Guarantee', 'Insurance Bond', 'Cash'], s.form || '') + '</div>' +
        '<div class="mb-3"><label class="form-label">Issuing institution</label><input class="form-control" name="issuing_institution" maxlength="200" value="' + MX.escape(s.issuing_institution || '') + '"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Issued</label><input type="date" class="form-control" name="issue_date" value="' + (s.issue_date || '') + '"></div>' +
        '<div class="col"><label class="form-label">Expires</label><input type="date" class="form-control" name="expiry_date" value="' + (s.expiry_date || '') + '"></div></div>' +
        '<div class="mb-3"><label class="form-label">Reference</label><input class="form-control" name="reference" maxlength="160" value="' + MX.escape(s.reference || '') + '"></div>' +
        '</form>';
}
function mxNewSecurity() { MX.drawer.open({ title: 'Add security', body: mxSecForm(null), footer: mxFoot('mxSubmit(\'sec-form\',\'POST\',\'/tenders/' + mxTenderId + '/securities\')') }); }
function mxEditSecurity(id) { MX.drawer.open({ title: 'Edit security', body: mxSecForm(mxById(mxRec.secs, id)), footer: mxFoot('mxSubmit(\'sec-form\',\'PATCH\',\'/tenders/securities/' + id + '\')') }); }
function mxDeleteSecurity(id) { mxDel('/tenders/securities/' + id, 'Remove this security?'); }

// ---- shared drawer helpers ----
function mxFoot(onclick) {
    return '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button>' +
           '<button class="btn btn-primary" onclick="' + onclick + '">Save</button>';
}
function mxDel(url, msg) {
    MX.confirm(msg).then(function (go) {
        if (!go) return;
        MX.api('DELETE', url).then(function () { MX.ok('Removed.'); mxReload(); }).catch(function (e) { MX.fail(e.message); });
    });
}
</script>
