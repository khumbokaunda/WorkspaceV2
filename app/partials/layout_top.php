<?php
// Application shell: head, sidebar, top command bar, breadcrumbs, and the
// shared drawer and palette shells. Views render into .mx-content.
// Expects: $pageTitle (string), optional $breadcrumbs (array of label => url,
// null url renders plain text).
$appName = brand_display_name();
$user = current_user();
$flashMsg = flash();
?>
<!doctype html>
<html lang="en" class="preload">
<head>
    <?php require APP_ROOT . '/app/partials/head_assets.php'; ?>
    <title><?= e(($pageTitle ?? 'Home') . ' | ' . $appName) ?></title>
</head>
<body>
<div class="mx-shell">
    <?php require APP_ROOT . '/app/partials/sidebar.php'; ?>
    <div class="mx-main">
        <?php require APP_ROOT . '/app/partials/topbar.php'; ?>
        <main class="mx-content">
<?php if ($flashMsg): ?>
            <div id="mx-flash" data-type="<?= e($flashMsg['type']) ?>" data-message="<?= e($flashMsg['message']) ?>" hidden></div>
<?php endif; ?>
