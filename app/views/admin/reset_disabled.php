<?php
// Shown when no reset key hash is configured. The reset area stays off until
// the owner seeds RESET_KEY_HASH in config.php.
?>
<h1 style="font-size:20px" class="mb-4">Factory Reset</h1>

<div class="mx-card" style="max-width:640px">
    <div class="mx-card-body">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-lock" style="font-size:22px;color:var(--mx-muted)"></i>
            <div>
                <h2 style="font-size:16px" class="mb-2">The reset area is disabled</h2>
                <p class="text-muted mb-3" style="font-size:14px">
                    Factory reset requires a reset key. The owner generates a long, high-entropy key once,
                    keeps the plaintext offline, and stores only its SHA-256 hash in <code>config.php</code>
                    under <code>reset.key_hash</code>. Until that hash is set, this area stays off.
                </p>
                <p class="text-muted mb-0" style="font-size:13px">
                    Generate a key and its hash with:
                </p>
                <pre class="mt-2 p-2" style="background:var(--mx-bg);border:1px solid var(--mx-border);border-radius:8px;font-size:12px;overflow:auto">php -r "$k=bin2hex(random_bytes(32)); echo \"key: $k\nhash: \".hash('sha256',$k).\"\n\";"</pre>
            </div>
        </div>
    </div>
</div>
