<?php // Two-factor enrolment and management. Rendered inside the app shell. ?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="d-flex align-items-center gap-2 mb-4">
            <h1 style="font-size:20px" class="mb-0">Two-factor authentication</h1>
<?php if ($enrolled): ?>
            <span class="mx-chip mx-chip-success">Enabled</span>
<?php else: ?>
            <span class="mx-chip mx-chip-plain">Not enabled</span>
<?php endif; ?>
        </div>

<?php if ($enrolled): ?>
        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Status</h2></div>
            <div class="mx-card-body">
                <p style="font-size:14px">Two-factor authentication is on for your account. You will be asked for a code from your authenticator app each time you sign in.</p>
                <p class="text-muted mb-0" style="font-size:13px">You have <strong><?= (int)$recoveryRemaining ?></strong> unused recovery codes.</p>
            </div>
        </div>

        <div class="mx-card mb-3">
            <div class="mx-card-header"><h2>Recovery codes</h2></div>
            <div class="mx-card-body">
                <p class="text-muted" style="font-size:13px">Regenerating replaces all of your existing recovery codes with a fresh set of ten. Any old codes stop working immediately.</p>
                <button class="btn btn-outline-primary" onclick="mx2faRegenerate()"><i class="fa-solid fa-rotate me-2"></i>Regenerate recovery codes</button>
            </div>
        </div>

        <div class="mx-card">
            <div class="mx-card-header"><h2>Turn off two-factor</h2></div>
            <div class="mx-card-body">
                <p class="text-muted" style="font-size:13px">Turning two-factor off removes the extra sign in step and deletes your recovery codes. Your password is required.</p>
                <button class="btn btn-subtle" style="color:var(--mx-danger)" onclick="mx2faDisable()"><i class="fa-solid fa-shield-halved me-2"></i>Disable two-factor</button>
            </div>
        </div>
<?php else: ?>
        <div class="mx-card">
            <div class="mx-card-header"><h2>Set up two-factor</h2></div>
            <div class="mx-card-body">
                <ol style="font-size:14px;padding-left:18px" class="mb-4">
                    <li class="mb-2">Scan this QR code with an authenticator app such as Google Authenticator, Microsoft Authenticator, or Authy.</li>
                    <li class="mb-2">Enter the 6-digit code the app shows to confirm it is working.</li>
                    <li>Save the recovery codes that appear after you confirm.</li>
                </ol>

                <div class="d-flex flex-column flex-sm-row gap-4 align-items-center align-items-sm-start mb-4">
                    <div id="mx-2fa-qr" style="background:#fff;padding:10px;border-radius:10px;line-height:0"></div>
                    <div>
                        <div class="text-muted mb-1" style="font-size:12px">Cannot scan? Enter this secret manually:</div>
                        <code style="font-size:14px;word-break:break-all"><?= e($secret) ?></code>
                    </div>
                </div>

                <form id="mx-2fa-enable-form" data-parsley-validate onsubmit="return false">
                    <label class="form-label" for="confirm-code">Confirmation code</label>
                    <div class="d-flex gap-2" style="max-width:280px">
                        <input type="text" class="form-control text-center mx-mono" id="confirm-code" name="code"
                               inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                               style="letter-spacing:0.3em" placeholder="000000">
                        <button type="button" class="btn btn-primary" onclick="mx2faEnable()">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

<script src="/assets/vendor/js/qrcode-generator.js"></script>
<script>
<?php if (!$enrolled): ?>
document.addEventListener('DOMContentLoaded', function () {
    // Build the QR locally from the otpauth URI. The secret never leaves via
    // an external image request.
    var uri = <?= json_encode($uri) ?>;
    var qr = qrcode(0, 'M');
    qr.addData(uri);
    qr.make();
    document.getElementById('mx-2fa-qr').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 0 });
});

function mx2faEnable() {
    var code = document.getElementById('confirm-code').value.replace(/\s+/g, '');
    MX.api('POST', '/account/two-factor/enable', { code: code })
        .then(function (data) { mx2faShowCodes(data.recovery_codes, 'Two-factor is now on.'); })
        .catch(function (e) { MX.fail(e.message); });
}
<?php else: ?>
function mx2faDisable() {
    Swal.fire({
        title: 'Disable two-factor?',
        text: 'Enter your password to confirm. Your recovery codes will be deleted.',
        input: 'password',
        inputPlaceholder: 'Current password',
        showCancelButton: true,
        confirmButtonText: 'Disable',
        reverseButtons: true
    }).then(function (r) {
        if (!r.isConfirmed) return;
        MX.api('POST', '/account/two-factor/disable', { password: r.value || '' })
            .then(function () { MX.ok('Two-factor disabled.'); setTimeout(function () { location.reload(); }, 700); })
            .catch(function (e) { MX.fail(e.message); });
    });
}

function mx2faRegenerate() {
    Swal.fire({
        title: 'Regenerate recovery codes?',
        text: 'Enter your password. All existing recovery codes stop working.',
        input: 'password',
        inputPlaceholder: 'Current password',
        showCancelButton: true,
        confirmButtonText: 'Regenerate',
        reverseButtons: true
    }).then(function (r) {
        if (!r.isConfirmed) return;
        MX.api('POST', '/account/two-factor/recovery', { password: r.value || '' })
            .then(function (data) { mx2faShowCodes(data.recovery_codes, 'New recovery codes generated.'); })
            .catch(function (e) { MX.fail(e.message); });
    });
}
<?php endif; ?>

// Show the one-time recovery codes. They are never retrievable again.
function mx2faShowCodes(codes, title) {
    var html = '<p style="font-size:13px">Save these now. They will not be shown again. Each code works once.</p>' +
        '<div class="mx-mono" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:14px;text-align:center;margin:12px 0">' +
        codes.map(function (c) { return '<div style="padding:6px;border:1px solid var(--mx-border);border-radius:6px">' + MX.escape(c) + '</div>'; }).join('') +
        '</div>';
    Swal.fire({
        title: title + ' Save your recovery codes',
        html: html,
        icon: 'success',
        confirmButtonText: 'I have saved them',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(function () { location.href = '/account/two-factor'; });
}
</script>
