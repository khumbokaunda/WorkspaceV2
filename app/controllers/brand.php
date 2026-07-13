<?php
// Public branding asset server. Branding is intentionally public in effect: the
// logo and favicon appear on the login page, before authentication, so these
// files are served here rather than through the authenticated gated download
// route. The directory only ever holds images written by the branding upload
// and favicon generator, filenames are random, and only image content types are
// emitted with nosniff so nothing can be coaxed into executing.

declare(strict_types=1);

function serve(string $file): void
{
    // Basename only: no path traversal out of the branding directory.
    $name = basename($file);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
    ];
    if (!isset($types[$ext])) {
        render_error(404, 'Not found', 'That asset does not exist.');
    }
    $path = storage_path('branding') . '/' . $name;
    if (!is_file($path)) {
        render_error(404, 'Not found', 'That asset does not exist.');
    }
    header('Content-Type: ' . $types[$ext]);
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    // The URL is cache-busted by the branding version, so a given URL is
    // immutable and may be cached hard.
    header('Cache-Control: public, max-age=31536000, immutable');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($path);
    exit;
}
