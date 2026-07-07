<?php
// Shared error page. Renders inside the shell when signed in, bare otherwise.
$bare = empty($_SESSION['user_id']);
if ($bare): $appName = setting('org_name', config('app.name', 'Meridian')); ?>
<!doctype html>
<html lang="en">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title><?= e($errTitle) ?> | <?= e($appName) ?></title>
</head>
<body>
<div class="mx-auth-page">
    <div class="mx-auth-card text-center">
<?php else: ?>
<div class="d-flex justify-content-center">
    <div class="mx-card text-center" style="max-width:420px;width:100%">
        <div class="mx-card-body py-5">
<?php endif; ?>
        <div style="font-size:44px;font-weight:700;color:var(--mx-primary)"><?= (int)$errStatus ?></div>
        <h1 style="font-size:18px" class="mb-2"><?= e($errTitle) ?></h1>
        <p class="text-muted mb-4"><?= e($errMessage) ?></p>
<?php if ($bare): ?>
        <a class="btn btn-primary" href="/login">Go to sign in</a>
    </div>
</div>
<?php require APP_ROOT . '/app/partials/foot_assets.php'; ?>
</body>
</html>
<?php else: ?>
        <a class="btn btn-primary" href="/dashboard">Back to dashboard</a>
        </div>
    </div>
</div>
<?php endif; ?>
