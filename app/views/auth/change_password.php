<?php
// Rendered inside the layout for a voluntary change, bare for the forced
// first-login change (where the rest of the app is not yet reachable).
$appName = setting('org_name', config('app.name', 'Meridian'));
if ($forced): $flashMsg = flash(); ?>
<!doctype html>
<html lang="en">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title>Change password | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card">
        <h1 style="font-size:20px" class="mb-2">Choose a new password</h1>
        <p class="text-muted mb-4">You must set a new password before continuing. Use at least 12 characters.</p>
<?php else: ?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="mx-card">
            <div class="mx-card-header"><h2>Change password</h2></div>
            <div class="mx-card-body">
<?php endif; ?>
        <form id="mx-password-form" data-parsley-validate>
            <div class="mb-3">
                <label class="form-label" for="current_password">Current password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="new_password">New password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required data-parsley-equalto="#new_password" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save new password</button>
        </form>
<?php if ($forced): ?>
    </div>
</div>
<?php if ($flashMsg): ?>
<div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
<?php else: ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('mx-password-form');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (window.jQuery && $(form).parsley && !$(form).parsley().validate()) return;
        MX.api('POST', '/account/password', MX.formData(form))
            .then(function (data) {
                MX.ok('Password changed.');
                setTimeout(function () { window.location.href = data.redirect || '/dashboard'; }, 700);
            })
            .catch(function (err) {
                MX.fail(err.message);
                MX.showFieldErrors(form, err.fields);
            });
    });
});
</script>
<?php if ($forced): ?>
</body>
</html>
<?php endif; ?>
