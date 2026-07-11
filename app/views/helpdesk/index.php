<?php
// Helpdesk: my tickets, and for agents the open queue.
$prChip = fn($p) => ['Low' => 'mx-chip-plain', 'Normal' => 'mx-chip-info', 'High' => 'mx-chip-warning', 'Urgent' => 'mx-chip-danger'][$p] ?? 'mx-chip-plain';
$stChip = fn($s) => ['Open' => 'mx-chip-info', 'In Progress' => 'mx-chip-warning', 'Resolved' => 'mx-chip-success', 'Closed' => 'mx-chip-plain'][$s] ?? 'mx-chip-plain';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Helpdesk</h1>
    <button class="btn btn-primary" onclick="mxNewTicket()"><i class="fa-solid fa-plus me-2"></i>Raise a ticket</button>
</div>

<?php if ($canManage): ?>
<div class="mx-card mb-3">
    <div class="mx-card-header"><h2>Open queue</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$queue): ?>
        <div class="mx-empty py-4"><i class="fa-regular fa-face-smile"></i><p class="mb-0">Nothing open. All caught up.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Subject</th><th>Category</th><th>Raised by</th><th>Assignee</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
<?php foreach ($queue as $t): ?>
                <tr>
                    <td><a href="/helpdesk/<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a></td>
                    <td><?= e($t['category']) ?></td>
                    <td><?= e($t['raiser'] ?: '') ?></td>
                    <td><?= $t['assignee'] ? e($t['assignee']) : '<span class="text-muted">Unassigned</span>' ?></td>
                    <td><span class="mx-chip <?= $prChip($t['priority']) ?>"><?= e($t['priority']) ?></span></td>
                    <td><span class="mx-chip <?= $stChip($t['status']) ?>"><?= e($t['status']) ?></span></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="mx-card">
    <div class="mx-card-header"><h2>My tickets</h2></div>
    <div class="mx-card-body mx-flush">
<?php if (!$myTickets): ?>
        <div class="mx-empty py-4"><i class="fa-solid fa-headset"></i><p class="mb-0">You have not raised any tickets.</p></div>
<?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0" style="font-size:13px">
            <thead><tr><th>Subject</th><th>Category</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Raised</th></tr></thead>
            <tbody>
<?php foreach ($myTickets as $t): ?>
                <tr>
                    <td><a href="/helpdesk/<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a></td>
                    <td><?= e($t['category']) ?></td>
                    <td><?= $t['assignee'] ? e($t['assignee']) : '<span class="text-muted">Unassigned</span>' ?></td>
                    <td><span class="mx-chip <?= $prChip($t['priority']) ?>"><?= e($t['priority']) ?></span></td>
                    <td><span class="mx-chip <?= $stChip($t['status']) ?>"><?= e($t['status']) ?></span></td>
                    <td class="mx-tabular"><?= e(date('j M Y', strtotime($t['created_at']))) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table></div>
<?php endif; ?>
    </div>
</div>

<script>
var mxHdCategories = <?= json_encode(array_values($categories)) ?>;
var mxHdPriorities = <?= json_encode(array_values($priorities)) ?>;

function mxNewTicket() {
    var sel = function (name, opts, def) { return '<select class="form-select" name="' + name + '">' + opts.map(function (o) { return '<option' + (o === def ? ' selected' : '') + '>' + MX.escape(o) + '</option>'; }).join('') + '</select>'; };
    var body = '<form id="ticket-form">' +
        '<div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" required maxlength="200"></div>' +
        '<div class="row g-2 mb-3"><div class="col"><label class="form-label">Category</label>' + sel('category', mxHdCategories, 'IT') + '</div>' +
        '<div class="col"><label class="form-label">Priority</label>' + sel('priority', mxHdPriorities, 'Normal') + '</div></div>' +
        '<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" maxlength="3000"></textarea></div>' +
        '</form>';
    MX.drawer.open({ title: 'Raise a ticket', body: body,
        footer: '<button class="btn btn-outline-primary" onclick="MX.drawer.close()">Cancel</button><button class="btn btn-primary" onclick="mxSubmitTicket()">Submit</button>' });
}
function mxSubmitTicket() {
    var form = document.getElementById('ticket-form');
    if (!form.reportValidity()) return;
    MX.api('POST', '/helpdesk', MX.formData(form))
        .then(function (d) { MX.drawer.close(); if (d.ticket_id) location.href = '/helpdesk/' + d.ticket_id; })
        .catch(function (e) { MX.fail(e.message); MX.showFieldErrors(form, e.fields); });
}
</script>
