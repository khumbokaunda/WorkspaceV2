-- 002 One-time recovery codes for two-factor authentication.
--
-- Each code is stored only as a bcrypt hash, never in plaintext. Codes are
-- shown to the user once, at enrolment, and are not retrievable afterwards.
-- A code is spent by stamping used_at. Re-runnable: guarded with IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS totp_recovery_codes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_recovery_user (user_id, used_at),
    CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
