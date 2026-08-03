<?php $appName = brand_display_name(); $flashMsg = flash(); ?>
<!doctype html>
<html lang="en" class="preload">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title>Sign in | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card">
        <div class="d-flex align-items-center gap-2 mb-4">
            <span class="mx-brand-logo-box">
                <img class="brand-logo brand-logo-light" src="<?= e(brand_asset('full', 'light')) ?>" alt="<?= e($appName) ?>" width="40" height="40">
                <img class="brand-logo brand-logo-dark" src="<?= e(brand_asset('full', 'dark')) ?>" alt="" width="40" height="40" aria-hidden="true">
            </span>
            <h1 style="font-size:20px;margin:0"><?= e($appName) ?></h1>
        </div>
        <p class="text-muted mb-4">Sign in to your workspace.</p>
        <form method="post" action="/login" data-parsley-validate data-device-capture>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required maxlength="60" autofocus autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign in</button>
        </form>
        <div class="text-center mt-3">
            <a href="/forgot-password" style="font-size:13px">Forgot your password?</a>
        </div>
    </div>
</div>
<?php if ($flashMsg): ?>
<div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
</body>
</html>
