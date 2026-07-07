<?php
// Meridian configuration. Copy this file to config.php and fill in real values.
// config.php is gitignored and lives outside the web document root, so it is
// never reachable over HTTP.

return [
    'app' => [
        // Random 64 hex chars. Used to sign file download tokens and other HMACs.
        // Generate with: php -r "echo bin2hex(random_bytes(32));"
        'key'          => 'CHANGE_ME_64_HEX_CHARS',
        'name'         => 'Meridian',
        'timezone'     => 'Africa/Blantyre',
        // Base URL without trailing slash, used in emails. Example: https://meridian.example.com
        'base_url'     => 'http://localhost:8080',
        // Set true when served over HTTPS so session cookies are marked Secure.
        'https'        => false,
        // Idle timeout and absolute session lifetime, in seconds.
        'session_idle' => 1800,
        'session_max'  => 43200,
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'meridian',
        'user'     => 'meridian',
        'pass'     => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],
    'mail' => [
        // When enabled is false, outgoing mail is written to storage/logs/mail.log
        // instead of being sent. Useful in development.
        'enabled'    => false,
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',
        'from_email' => 'meridian@example.com',
        'from_name'  => 'Meridian',
    ],
    'uploads' => [
        // Absolute path is resolved from this file's directory.
        'dir'            => __DIR__ . '/storage/uploads',
        'max_bytes'      => 10 * 1024 * 1024,
        'allowed_ext'    => ['pdf', 'doc', 'docx', 'odt', 'txt', 'png', 'jpg', 'jpeg'],
        'allowed_mime'   => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
            'text/plain',
            'image/png',
            'image/jpeg',
        ],
    ],
];
