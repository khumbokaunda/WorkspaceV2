<?php $appName = setting('org_name', config('app.name', 'Meridian')); $flashMsg = flash(); ?>
<!doctype html>
<html lang="en">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title>Choose a new password | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card">
        <h1 style="font-size:20px" class="mb-2">Choose a new password</h1>
        <p class="text-muted mb-4">Use at least 12 characters.</p>
        <form method="post" action="/reset-password" data-parsley-validate>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label" for="new_password">New password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="12" autocomplete="new-password" autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required data-parsley-equalto="#new_password" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save new password</button>
        </form>
    </div>
</div>
<?php if ($flashMsg): ?>
<div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
</body>
</html>
