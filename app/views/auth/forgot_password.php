<?php $appName = setting('org_name', config('app.name', 'Meridian')); $flashMsg = flash(); ?>
<!doctype html>
<html lang="en">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title>Reset password | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card">
        <h1 style="font-size:20px" class="mb-2">Reset your password</h1>
        <p class="text-muted mb-4">Enter your account email address. If it is registered, a single-use reset link will be sent to it.</p>
        <form method="post" action="/forgot-password" data-parsley-validate>
            <div class="mb-4">
                <label class="form-label" for="email">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="190" autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send reset link</button>
        </form>
        <div class="text-center mt-3">
            <a href="/login" style="font-size:13px">Back to sign in</a>
        </div>
    </div>
</div>
<?php if ($flashMsg): ?>
<div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
</body>
</html>
