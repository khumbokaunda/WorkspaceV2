<?php // Shared script tags, loaded at the end of <body> on every page. ?>
<script src="/assets/vendor/js/jquery.min.js"></script>
<script src="/assets/vendor/js/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/js/dataTables.min.js"></script>
<script src="/assets/vendor/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/vendor/js/parsley.min.js"></script>
<script src="/assets/vendor/js/purify.min.js"></script>
<script src="/assets/vendor/js/sweetalert2.all.min.js"></script>
<script src="<?= e(asset_url('/assets/js/app.js')) ?>"></script>
<?php
// Hand a freshly issued persistent device token to the browser for storage.
// This covers the redirect-based login flow, which has no JSON response to
// carry the token. The value is server-issued and validated to 40 hex
// characters, so it is safe to inline; it is read once and cleared.
$mxDeviceToken = '';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['device_token_issue'])) {
    $candidate = (string)$_SESSION['device_token_issue'];
    unset($_SESSION['device_token_issue']);
    if (preg_match('/^[a-f0-9]{40}$/', $candidate) === 1) {
        $mxDeviceToken = $candidate;
    }
}
?>
<?php if ($mxDeviceToken !== ''): ?>
<script>window.MX && MX.device && MX.device.adopt('<?= $mxDeviceToken ?>');</script>
<?php endif; ?>
