<?php
// Ticket detail: the request, the comment thread, and for agents the assign and
// status controls.
$prChip = ['Low' => 'mx-chip-plain', 'Normal' => 'mx-chip-info', 'High' => 'mx-chip-warning', 'Urgent' => 'mx-chip-danger'][$ticket['priority']] ?? 'mx-chip-plain';
$stChip = ['Open' => 'mx-chip-info', 'In Progress' => 'mx-chip-warning', 'Resolved' => 'mx-chip-success', 'Closed' => 'mx-chip-plain'][$ticket['status']] ?? 'mx-chip-plain';
?>
<div class="mx-card mb-3">
    <div class="mx-card-body d-flex align-items-start gap-3 flex-wrap">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 style="font-size:20px" class="mb-0"><?= e($ticket['subject']) ?></h1>
                <span class="mx-chip <?= $stChip ?>"><?= e($ticket['status']) ?></span>
                <span class="mx-chip <?= $prChip ?>"><?= e($ticket['priority']) ?></span>
            </div>
            <div class="text-muted mt-1"><?= e($ticket['category']) ?> &middot; raised by <?= e($ticket['raiser'] ?: 'unknown') ?> on <?= e(date('j M Y', strtotime($ticket['created_at']))) ?><?= $ticket['assignee'] ? ' &middot; assigned to ' . e($ticket['assignee']) : '' ?></div>
        </div>
        <a href="/helpdesk" class="btn btn-subtle btn-sm">All tickets</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Request</h2></div>
            <div class="mx-card-body">
                <p class="mb-0" style="font-size:13px;white-space:pre-line"><?= e($ticket['description'] ?: 'No description provided.') ?></p>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header"><h2>Comments</h2></div>
            <div class="mx-card-body mx-flush">
<?php if (!$comments): ?>
                <div class="mx-empty py-4"><i class="fa-regular fa-comments"></i><p class="mb-0">No comments yet.</p></div>
<?php else: ?>
                <ul class="list-unstyled m-0">
<?php foreach ($comments as $c): ?>
                    <li class="px-3 py-2 border-bottom">
                        <div style="font-size:13px;white-space:pre-line"><?= e($c['body']) ?></div>
                        <small class="text-muted"><?= e($c['author'] ?: '') ?> &middot; <?= e(date('j M Y, H:i', strtotime($c['created_at']))) ?></small>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
                <div class="p-3 border-top">
                    <textarea id="hd-comment" class="form-control mb-2" rows="2" maxlength="2000" placeholder="Write a reply"></textarea>
                    <button class="btn btn-primary btn-sm" onclick="mxAddComment()">Add comment</button>
                </div>
            </div>
        </div>
    </div>

<?php if ($canManage): ?>
    <div class="col-12 col-lg-4">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Manage</h2></div>
            <div class="mx-card-body">
                <form id="hd-manage">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
<?php foreach ($statuses as $s): ?>
                            <option<?= $ticket['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign to</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">Unassigned</option>
<?php foreach ($agents as $a): $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: $a['username']; ?>
                            <option value="<?= (int)$a['id'] ?>"<?= (int)($ticket['assigned_to'] ?? 0) === (int)$a['id'] ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="mxSaveManage()">Save</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
var mxTicketId = <?= (int)$ticket['id'] ?>;
function mxAddComment() {
    var el = document.getElementById('hd-comment');
    var body = MX.clean(el.value).trim();
    if (!body) { MX.fail('Write a comment first.'); return; }
    MX.api('POST', '/helpdesk/' + mxTicketId + '/comments', { body: body })
        .then(function () { MX.ok('Added.'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { MX.fail(e.message); });
}
function mxSaveManage() {
    var form = document.getElementById('hd-manage');
    MX.api('PATCH', '/helpdesk/' + mxTicketId, MX.formData(form))
        .then(function () { MX.ok('Saved.'); setTimeout(function () { location.reload(); }, 400); })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
