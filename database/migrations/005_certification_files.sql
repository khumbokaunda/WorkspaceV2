-- 005 Certificate file attachments.
--
-- Extends certifications so the actual certificate can be attached, not just
-- recorded: a stored PDF or image kept under a random name in storage/uploads
-- and served only through the gated download route, never a direct web path.
-- This directly serves the tender use case, where scanned certificates are
-- submitted as evidence of a qualification.
--
-- All columns are nullable so existing certifications without a file are
-- unaffected. Re-runnable.

ALTER TABLE certifications
    ADD COLUMN IF NOT EXISTS cert_stored_name   VARCHAR(80)  NULL UNIQUE AFTER status,
    ADD COLUMN IF NOT EXISTS cert_original_name VARCHAR(200) NULL AFTER cert_stored_name,
    ADD COLUMN IF NOT EXISTS cert_mime          VARCHAR(120) NULL AFTER cert_original_name,
    ADD COLUMN IF NOT EXISTS cert_size_bytes    INT UNSIGNED NULL AFTER cert_mime;
