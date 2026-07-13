<?php
// Branding upload and favicon generation. Required by the branding controller.
// Uploads follow the hardened pattern (size limit, extension allowlist, finfo
// MIME verification, MIME and extension agreement, random stored filename,
// storage outside the web root) with two additions for this category: raster
// uploads are re-encoded through GD, which strips embedded metadata and any
// appended payload and guarantees the stored file is a real image, and the
// favicon set is generated from the icon mark rather than uploaded.
//
// SVG decision: Option A. SVG is not accepted at all. An uploaded SVG is an XML
// document that can carry scripts and external references, and serving it from
// the application origin is a persistence and privilege-escalation vector even
// for an administrator upload. Accepting only PNG, JPG and WEBP removes the
// entire class of issue. The interface advises exporting a transparent PNG.

declare(strict_types=1);

// The maximum side length accepted, guarding against decompression attacks.
const BRAND_MAX_SIDE = 4000;
const BRAND_MAX_BYTES = 2 * 1024 * 1024;

// Validate, re-encode and store a branding image, returning the stored filename
// relative to the branding root. Throws RuntimeException with a message safe to
// show the administrator on any failure.
function brand_store_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No file received or the upload failed.');
    }
    if ($file['size'] > BRAND_MAX_BYTES || $file['size'] <= 0) {
        throw new RuntimeException('The image must be under 2 MB.');
    }
    $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Use a PNG, JPG or WEBP image. Export your logo as a transparent PNG at about 512 pixels wide.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== $allowed[$ext]) {
        throw new RuntimeException('The image content does not match its extension.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Image processing is unavailable on this server.');
    }
    $img = brand_gd_load((string)$file['tmp_name'], $mime);
    if ($img === null) {
        throw new RuntimeException('That image could not be read.');
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 1 || $h < 1 || $w > BRAND_MAX_SIDE || $h > BRAND_MAX_SIDE) {
        imagedestroy($img);
        throw new RuntimeException('The image dimensions are out of range. Keep each side under ' . BRAND_MAX_SIDE . ' pixels.');
    }

    $dir = storage_path('branding');
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        imagedestroy($img);
        throw new RuntimeException('Could not prepare the branding directory.');
    }
    // Re-encode to PNG so the stored bytes are a freshly written image with no
    // metadata or appended payload, and transparency is preserved.
    $stored = bin2hex(random_bytes(16)) . '.png';
    imagealphablending($img, false);
    imagesavealpha($img, true);
    if (!imagepng($img, $dir . '/' . $stored)) {
        imagedestroy($img);
        throw new RuntimeException('Could not store the image.');
    }
    imagedestroy($img);
    return $stored;
}

// Load an image into a GD resource by detected MIME, or null if unsupported.
function brand_gd_load(string $path, string $mime)
{
    switch ($mime) {
        case 'image/png':
            return imagecreatefrompng($path) ?: null;
        case 'image/jpeg':
            return imagecreatefromjpeg($path) ?: null;
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? (imagecreatefromwebp($path) ?: null) : null;
        default:
            return null;
    }
}

// Generate the favicon set from a stored icon-mark PNG. Returns a map of the
// profile columns to their stored filenames. If GD is unavailable, falls back
// to serving the icon mark itself at its native size and notes it in the log
// rather than failing the upload.
function brand_generate_favicons(string $iconStored): array
{
    $src = storage_path('branding') . '/' . $iconStored;
    if (!extension_loaded('gd') || !is_file($src)) {
        error_log('Branding: GD unavailable or icon missing, favicon falls back to the icon mark at native size.');
        return ['favicon_32' => $iconStored, 'favicon_180' => $iconStored, 'favicon_16' => $iconStored];
    }
    $img = @imagecreatefrompng($src);
    if ($img === false) {
        error_log('Branding: could not read the icon mark for favicon generation, falling back to it directly.');
        return ['favicon_32' => $iconStored, 'favicon_180' => $iconStored, 'favicon_16' => $iconStored];
    }
    $sw = imagesx($img);
    $sh = imagesy($img);
    $dir = storage_path('branding');
    $out = [];
    foreach (['favicon_32' => 32, 'favicon_180' => 180, 'favicon_16' => 16] as $col => $size) {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        // Fit the icon inside the square, centred, preserving aspect.
        $scale = min($size / $sw, $size / $sh);
        $dw = (int)round($sw * $scale);
        $dh = (int)round($sh * $scale);
        $dx = (int)(($size - $dw) / 2);
        $dy = (int)(($size - $dh) / 2);
        imagecopyresampled($canvas, $img, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        $name = bin2hex(random_bytes(16)) . '.png';
        imagepng($canvas, $dir . '/' . $name);
        imagedestroy($canvas);
        $out[$col] = $name;
    }
    imagedestroy($img);
    return $out;
}

// Delete a stored branding file if it is a local branding filename. Ignores
// blanks and anything that looks like a path, so it can never remove elsewhere.
function brand_delete_file(?string $stored): void
{
    $stored = trim((string)$stored);
    if ($stored === '' || $stored !== basename($stored)) {
        return;
    }
    $path = storage_path('branding') . '/' . $stored;
    if (is_file($path)) {
        @unlink($path);
    }
}
