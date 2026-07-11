<?php
// Factory reset wizard. Every step is server validated; this page reflects the
// server-held progress and never lets the destructive step run until each prior
// step is satisfied. Read colours from the design tokens so both themes render.
$a = $state['archive'] ?? null;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 style="font-size:20px" class="mb-0">Factory Reset</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary btn-sm" href="/admin/restore"><i class="fa-solid fa-upload me-1"></i>Restore from archive</a>
        <a class="btn btn-outline-primary btn-sm" href="/admin/reset/history"><i class="fa-solid fa-clock-rotate-left me-1"></i>Reset history</a>
    </div>
</div>

<div class="mx-card mb-3" style="border-left:3px solid var(--mx-danger)">
    <div class="mx-card-body d-flex align-items-start gap-3">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:22px;color:var(--mx-danger)"></i>
        <div>
            <h2 style="font-size:16px" class="mb-1">This erases the instance</h2>
            <p class="text-muted mb-0" style="font-size:13px">A full reset permanently erases all business data and uploaded files, then re-seeds the fresh default state with a single administrator. A full archive is produced and downloaded first so the wiped state can be restored later. The reset history and the reset key are never erased.</p>
        </div>
    </div>
</div>

<div id="rw" data-step="<?= (int)$state['step'] ?>" style="max-width:760px">

    <!-- Step 1: Understand -->
    <div class="mx-card mb-2 rw-step" data-panel="1">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">1</span> Understand what happens</h2>
            <i class="fa-solid fa-check rw-done-mark" style="color:var(--mx-success);display:none"></i>
        </div>
        <div class="mx-card-body rw-body">
            <form id="rw-understand">
                <label class="d-flex gap-2 mb-2" style="font-size:14px"><input type="checkbox" class="form-check-input" name="ack_erase"> I understand all business data and uploaded files will be permanently erased.</label>
                <label class="d-flex gap-2 mb-2" style="font-size:14px"><input type="checkbox" class="form-check-input" name="ack_undo"> I understand this cannot be undone except by restoring the archive.</label>
                <label class="d-flex gap-2 mb-3" style="font-size:14px"><input type="checkbox" class="form-check-input" name="ack_users"> I understand all users except one administrator will be removed.</label>
                <button type="button" class="btn btn-primary btn-sm" onclick="RW.understand()">Continue</button>
            </form>
        </div>
    </div>

    <!-- Step 2: Reason -->
    <div class="mx-card mb-2 rw-step" data-panel="2">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">2</span> Reason</h2>
            <i class="fa-solid fa-check rw-done-mark" style="color:var(--mx-success);display:none"></i>
        </div>
        <div class="mx-card-body rw-body">
            <form id="rw-reason">
                <div class="mb-3"><label class="form-label">Category</label>
                    <select class="form-select" name="reason_category">
                        <option value="">Choose a reason</option>
<?php foreach ($categories as $c): ?>
                        <option<?= (($state['reason']['category'] ?? '') === $c) ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Explanation</label>
                    <textarea class="form-control" name="reason_text" rows="3" maxlength="2000" placeholder="Why is this reset happening?"><?= e($state['reason']['text'] ?? '') ?></textarea>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="RW.reason()">Continue</button>
            </form>
<?php if ($twoPerson): ?>
            <div class="mt-3 pt-3" style="border-top:1px solid var(--mx-border)">
                <p class="text-muted mb-2" style="font-size:13px"><i class="fa-solid fa-user-shield me-1"></i>Two-person approval is enabled. A second administrator must approve this reset before it can run.</p>
                <div id="rw-approval-box"></div>
            </div>
<?php endif; ?>
        </div>
    </div>

    <!-- Step 3: Authenticate -->
    <div class="mx-card mb-2 rw-step" data-panel="3">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">3</span> Authenticate</h2>
            <i class="fa-solid fa-check rw-done-mark" style="color:var(--mx-success);display:none"></i>
        </div>
        <div class="mx-card-body rw-body">
<?php if (!$hasTotp): ?>
            <div class="alert alert-warning py-2" style="font-size:13px"><i class="fa-solid fa-shield-halved me-1"></i>You must enrol in two-factor authentication before you can reset. <a href="/account/two-factor">Enrol now</a>.</div>
<?php else: ?>
            <p class="text-muted mb-3" style="font-size:13px">Confirm all three factors: your account password, a current authenticator code, and the reset key.</p>
            <form id="rw-auth" autocomplete="off">
                <div class="mb-3"><label class="form-label">Account password</label><input type="password" class="form-control" name="password" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Authenticator code</label><input class="form-control mx-mono" name="totp" inputmode="numeric" maxlength="6" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Reset key</label><input type="password" class="form-control mx-mono" name="reset_key" autocomplete="off"></div>
                <button type="button" class="btn btn-primary btn-sm" onclick="RW.authenticate()">Verify</button>
            </form>
<?php endif; ?>
        </div>
    </div>

    <!-- Step 4: Backup -->
    <div class="mx-card mb-2 rw-step" data-panel="4">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">4</span> Backup and download</h2>
            <i class="fa-solid fa-check rw-done-mark" style="color:var(--mx-success);display:none"></i>
        </div>
        <div class="mx-card-body rw-body">
            <p class="text-muted mb-3" style="font-size:13px">The full archive is generated and downloaded now, before anything is erased. You must save it and confirm its checksum before the reset can proceed.</p>
            <button type="button" class="btn btn-primary btn-sm mb-3" id="rw-gen" onclick="RW.backup()"><i class="fa-solid fa-box-archive me-1"></i>Generate backup</button>
            <div id="rw-archive" style="display:none">
                <div class="p-3 mb-3" style="background:var(--mx-bg);border:1px solid var(--mx-border);border-radius:8px;font-size:13px">
                    <div><strong>File:</strong> <span id="rw-arc-name" class="mx-mono"></span></div>
                    <div><strong>Size:</strong> <span id="rw-arc-size"></span></div>
                    <div style="word-break:break-all"><strong>SHA-256:</strong> <span id="rw-arc-sum" class="mx-mono"></span></div>
                    <a class="btn btn-outline-primary btn-sm mt-2" id="rw-download" href="/admin/reset/download"><i class="fa-solid fa-download me-1"></i>Download archive</a>
                </div>
                <form id="rw-confirm-backup">
                    <div class="mb-2"><label class="form-label">Re-enter the checksum to confirm you saved the file</label><input class="form-control mx-mono" name="checksum" placeholder="Paste the SHA-256 shown above"></div>
                    <label class="d-flex gap-2 mb-3" style="font-size:14px"><input type="checkbox" class="form-check-input" name="saved"> I have downloaded and safely saved this archive.</label>
                    <button type="button" class="btn btn-primary btn-sm" onclick="RW.confirmBackup()">Confirm backup saved</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Step 5: Password choice -->
    <div class="mx-card mb-2 rw-step" data-panel="5">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">5</span> Administrator password after reset</h2>
            <i class="fa-solid fa-check rw-done-mark" style="color:var(--mx-success);display:none"></i>
        </div>
        <div class="mx-card-body rw-body">
            <form id="rw-password">
                <label class="d-flex gap-2 mb-2" style="font-size:14px"><input type="radio" class="form-check-input" name="pw_choice" value="keep" checked> Keep my current password.</label>
                <label class="d-flex gap-2 mb-2" style="font-size:14px"><input type="radio" class="form-check-input" name="pw_choice" value="new"> Set a new password now.</label>
                <div class="mb-2 ms-4" id="rw-newpw" style="display:none"><input type="password" class="form-control" name="new_password" placeholder="New password" autocomplete="new-password"></div>
                <label class="d-flex gap-2 mb-3" style="font-size:14px"><input type="radio" class="form-check-input" name="pw_choice" value="factory"> Use the original factory setup password (forces a change at next login).</label>
                <button type="button" class="btn btn-primary btn-sm" onclick="RW.password()">Continue</button>
            </form>
        </div>
    </div>

    <!-- Step 6: Final confirmation and execute -->
    <div class="mx-card mb-2 rw-step" data-panel="6">
        <div class="mx-card-header d-flex align-items-center justify-content-between">
            <h2><span class="rw-num">6</span> Final confirmation</h2>
        </div>
        <div class="mx-card-body rw-body">
            <div class="alert alert-danger py-2" style="font-size:13px"><i class="fa-solid fa-triangle-exclamation me-1"></i>This is the last guard. Running this erases the instance.</div>
            <form id="rw-execute" autocomplete="off">
                <div class="mb-3"><label class="form-label">Type <strong><?= e($confirmPhrase) ?></strong> to confirm</label><input class="form-control" name="confirm_phrase" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Account password</label><input type="password" class="form-control" name="password" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Authenticator code</label><input class="form-control mx-mono" name="totp" inputmode="numeric" maxlength="6" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Reset key</label><input type="password" class="form-control mx-mono" name="reset_key" autocomplete="off"></div>
                <button type="button" class="btn btn-danger" onclick="RW.execute()"><i class="fa-solid fa-triangle-exclamation me-1"></i>Erase and reset now</button>
            </form>
        </div>
    </div>
</div>

<!-- Scoped reset -->
<div class="mx-card mt-4" style="max-width:760px">
    <div class="mx-card-header"><h2>Clear transactional data only</h2></div>
    <div class="mx-card-body">
        <p class="text-muted mb-3" style="font-size:13px">A lighter option that clears activity and transactions (attendance, leave, tenders, procurement, expenses, payroll runs, tickets, bookings, notifications and the audit log) while keeping people, accounts, groups, master data and configuration. A backup is still taken first.</p>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('rw-scoped-form').style.display='block';this.style.display='none'"><i class="fa-solid fa-broom me-1"></i>Clear transactional data</button>
        <form id="rw-scoped-form" style="display:none" autocomplete="off">
            <div class="row g-2 mb-2">
                <div class="col"><label class="form-label">Account password</label><input type="password" class="form-control" name="password" autocomplete="off"></div>
                <div class="col"><label class="form-label">Authenticator code</label><input class="form-control mx-mono" name="totp" inputmode="numeric" maxlength="6" autocomplete="off"></div>
            </div>
            <div class="mb-2"><label class="form-label">Note (optional)</label><input class="form-control" name="reason_text" maxlength="500" placeholder="Why are you clearing this?"></div>
            <div class="mb-3"><label class="form-label">Type <strong><?= e($confirmPhrase) ?></strong> to confirm</label><input class="form-control" name="confirm_phrase" autocomplete="off"></div>
            <button type="button" class="btn btn-danger btn-sm" onclick="RW.scoped()"><i class="fa-solid fa-broom me-1"></i>Clear transactional data now</button>
        </form>
    </div>
</div>

<script>
var RW = {
    step: <?= (int)$state['step'] ?>,
    twoPerson: <?= $twoPerson ? 'true' : 'false' ?>,
    approval: <?= json_encode($pendingApproval ?: null) ?>,
    confirmPhrase: <?= json_encode($confirmPhrase) ?>,
    archiveReady: <?= $a ? 'true' : 'false' ?>,

    render: function () {
        document.querySelectorAll('.rw-step').forEach(function (panel) {
            var n = parseInt(panel.dataset.panel, 10);
            var body = panel.querySelector('.rw-body');
            var done = panel.querySelector('.rw-done-mark');
            var num = panel.querySelector('.rw-num');
            var current = (n === RW.step);
            var complete = (n < RW.step);
            panel.style.opacity = (n > RW.step) ? '0.5' : '1';
            if (body) body.style.display = current ? '' : 'none';
            if (done) done.style.display = complete ? '' : 'none';
            if (num) num.style.color = complete ? 'var(--mx-success)' : '';
        });
        if (RW.twoPerson) RW.renderApproval();
    },
    advance: function (step) { RW.step = Math.max(RW.step, step); RW.render(); },
    fail: function (e) { MX.fail(e.message || 'That did not work.'); },

    understand: function () {
        var f = document.getElementById('rw-understand');
        MX.api('POST', '/admin/reset/understand', MX.formData(f))
            .then(function (r) { RW.advance(r.step); }).catch(RW.fail);
    },
    reason: function () {
        var f = document.getElementById('rw-reason');
        MX.api('POST', '/admin/reset/reason', MX.formData(f))
            .then(function (r) { RW.advance(r.step); }).catch(RW.fail);
    },
    authenticate: function () {
        var f = document.getElementById('rw-auth');
        if (!f) return;
        MX.api('POST', '/admin/reset/authenticate', MX.formData(f))
            .then(function (r) { MX.ok('Verified.'); f.reset(); RW.advance(r.step); }).catch(RW.fail);
    },
    backup: function () {
        var btn = document.getElementById('rw-gen');
        btn.disabled = true; btn.innerHTML = 'Generating...';
        MX.api('POST', '/admin/reset/backup', {})
            .then(function (r) {
                document.getElementById('rw-arc-name').textContent = r.filename;
                document.getElementById('rw-arc-size').textContent = Math.round(r.size / 1024) + ' KB';
                document.getElementById('rw-arc-sum').textContent = r.checksum;
                document.getElementById('rw-archive').style.display = '';
                btn.style.display = 'none';
                window.location = '/admin/reset/download';
            })
            .catch(function (e) { btn.disabled = false; btn.innerHTML = 'Generate backup'; RW.fail(e); });
    },
    confirmBackup: function () {
        var f = document.getElementById('rw-confirm-backup');
        MX.api('POST', '/admin/reset/backup-confirm', MX.formData(f))
            .then(function (r) { MX.ok('Backup confirmed.'); RW.advance(r.step); }).catch(RW.fail);
    },
    password: function () {
        var f = document.getElementById('rw-password');
        MX.api('POST', '/admin/reset/password', MX.formData(f))
            .then(function (r) { RW.advance(r.step); }).catch(RW.fail);
    },
    execute: function () {
        if (RW.twoPerson && (!RW.approval || RW.approval.status !== 'approved')) {
            MX.fail('A second administrator must approve this reset first.');
            return;
        }
        var f = document.getElementById('rw-execute');
        var data = MX.formData(f);
        MX.confirm('Erase and reset this instance?', 'This cannot be undone except by restoring your archive.', 'Erase now').then(function (go) {
            if (!go) return;
            MX.api('POST', '/admin/reset/execute', data)
                .then(function (r) {
                    Swal.fire({ title: 'Reset complete', text: 'The instance has been reset. You will be signed out.', icon: 'success', allowOutsideClick: false })
                        .then(function () { window.location = r.redirect || '/login'; });
                }).catch(RW.fail);
        });
    },
    scoped: function () {
        var f = document.getElementById('rw-scoped-form');
        var data = MX.formData(f);
        MX.confirm('Clear all transactional data?', 'Activity and transactions will be erased. People, accounts and configuration are kept.', 'Clear now').then(function (go) {
            if (!go) return;
            MX.api('POST', '/admin/reset/scoped', data)
                .then(function (r) { Swal.fire({ title: 'Cleared', text: r.message || 'Done.', icon: 'success' }).then(function () { location.reload(); }); })
                .catch(RW.fail);
        });
    },

    renderApproval: function () {
        var box = document.getElementById('rw-approval-box');
        if (!box) return;
        if (!RW.approval) {
            box.innerHTML = '<button type="button" class="btn btn-outline-primary btn-sm" onclick="RW.requestApproval()">Request approval</button>';
        } else if (RW.approval.status === 'pending') {
            box.innerHTML = '<span class="mx-chip mx-chip-warning">Awaiting a second administrator</span> ' +
                '<button type="button" class="btn btn-subtle btn-sm" onclick="RW.cancelApproval()">Cancel request</button>';
        } else if (RW.approval.status === 'approved') {
            box.innerHTML = '<span class="mx-chip mx-chip-success">Approved</span>';
        }
    },
    requestApproval: function () {
        MX.api('POST', '/admin/reset/request-approval', {})
            .then(function () { RW.approval = { status: 'pending' }; RW.renderApproval(); MX.ok('Approval requested.'); }).catch(RW.fail);
    },
    cancelApproval: function () {
        if (!RW.approval || !RW.approval.id) { location.reload(); return; }
        MX.api('POST', '/admin/reset/cancel-approval/' + RW.approval.id, {})
            .then(function () { RW.approval = null; RW.renderApproval(); }).catch(RW.fail);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#rw-password input[name=pw_choice]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('rw-newpw').style.display = (this.value === 'new' && this.checked) ? '' : 'none';
        });
    });
    if (RW.archiveReady) {
        document.getElementById('rw-arc-name').textContent = <?= json_encode($a['filename'] ?? '') ?>;
        document.getElementById('rw-arc-size').textContent = Math.round(<?= (int)($a['size'] ?? 0) ?> / 1024) + ' KB';
        document.getElementById('rw-arc-sum').textContent = <?= json_encode($a['checksum'] ?? '') ?>;
        document.getElementById('rw-archive').style.display = '';
        var gen = document.getElementById('rw-gen'); if (gen) gen.style.display = 'none';
    }
    RW.render();
});
</script>
