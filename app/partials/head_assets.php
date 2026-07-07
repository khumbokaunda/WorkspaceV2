<?php // Shared <head> assets. Included by the app layout and by bare pages.
// All third-party assets are pinned local copies under assets/vendor, so
// the app renders identically with or without internet access. ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (!empty($_SESSION['user_id'])): ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php endif; ?>
<script>
// Apply the stored theme before first paint to avoid a flash of the wrong theme.
(function () {
    var t = localStorage.getItem('mx-theme');
    if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<link href="/assets/vendor/css/fonts.css" rel="stylesheet">
<link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/vendor/css/fontawesome.min.css" rel="stylesheet">
<link href="/assets/vendor/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="/assets/css/app.css" rel="stylesheet">
