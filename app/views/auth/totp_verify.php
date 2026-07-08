<?php $appName = setting('org_name', config('app.name', 'Meridian')); $flashMsg = flash(); ?>
<!doctype html>
<html lang="en">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title>Two-factor verification | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card">
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="mx-brand-mark" style="width:36px;height:36px;border-radius:9px;background:var(--mx-primary);color:#fff;display:inline-flex;align-items:center;justify-content:center"><i class="fa-solid fa-compass"></i></span>
            <h1 style="font-size:20px;margin:0"><?= e($appName) ?></h1>
        </div>
        <h2 style="font-size:16px" class="mb-1">Two-factor verification</h2>
        <p class="text-muted mb-4" id="mx-2fa-hint">Enter the 6-digit code from your authenticator app.</p>

        <form method="post" action="/login/verify">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="use_recovery" id="mx-2fa-recovery-flag" value="0">
            <div class="mb-3">
                <label class="form-label" for="code" id="mx-2fa-label">Authentication code</label>
                <input type="text" class="form-control text-center mx-mono" id="code" name="code"
                       inputmode="numeric" autocomplete="one-time-code" autofocus required
                       style="letter-spacing:0.3em;font-size:18px" placeholder="000000">
            </div>
            <button type="submit" class="btn btn-primary w-100">Verify and sign in</button>
        </form>

        <div class="text-center mt-3">
            <button type="button" class="btn btn-subtle btn-sm" id="mx-2fa-toggle" onclick="mx2faToggle()">Use a recovery code instead</button>
        </div>
        <div class="text-center mt-2">
            <form method="post" action="/logout" class="d-inline m-0">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn btn-subtle btn-sm text-muted">Cancel and sign out</button>
            </form>
        </div>
    </div>
</div>
<?php if ($flashMsg): ?>
<div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
<script>
var mx2faRecovery = false;
function mx2faToggle() {
    mx2faRecovery = !mx2faRecovery;
    document.getElementById('mx-2fa-recovery-flag').value = mx2faRecovery ? '1' : '0';
    var input = document.getElementById('code');
    var label = document.getElementById('mx-2fa-label');
    var hint = document.getElementById('mx-2fa-hint');
    var toggle = document.getElementById('mx-2fa-toggle');
    if (mx2faRecovery) {
        label.textContent = 'Recovery code';
        hint.textContent = 'Enter one of the recovery codes you saved at enrolment.';
        input.placeholder = 'XXXXX-XXXXX';
        input.setAttribute('inputmode', 'text');
        input.style.letterSpacing = '0.1em';
        toggle.textContent = 'Use an authenticator code instead';
    } else {
        label.textContent = 'Authentication code';
        hint.textContent = 'Enter the 6-digit code from your authenticator app.';
        input.placeholder = '000000';
        input.setAttribute('inputmode', 'numeric');
        input.style.letterSpacing = '0.3em';
        toggle.textContent = 'Use a recovery code instead';
    }
    input.value = '';
    input.focus();
}
</script>
</body>
</html>
