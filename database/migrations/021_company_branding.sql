-- 021 Company branding.
--
-- Separates the short interface display name from the full legal name, and adds
-- a complete logo asset set: full logo and icon mark, each in a light and a
-- dark variant, plus the favicon paths generated from the icon mark. Every
-- asset column stores a filename relative to the branding backup root, never an
-- absolute filesystem path, so an archive restores onto any server.
--
-- Additive only. Idempotent, and safe on a fresh install.

ALTER TABLE company_profile
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(64)  NULL AFTER legal_name,
    ADD COLUMN IF NOT EXISTS logo_light   VARCHAR(255) NULL AFTER logo_path,
    ADD COLUMN IF NOT EXISTS logo_dark    VARCHAR(255) NULL AFTER logo_light,
    ADD COLUMN IF NOT EXISTS icon_light   VARCHAR(255) NULL AFTER logo_dark,
    ADD COLUMN IF NOT EXISTS icon_dark    VARCHAR(255) NULL AFTER icon_light,
    ADD COLUMN IF NOT EXISTS favicon_32   VARCHAR(255) NULL AFTER icon_dark,
    ADD COLUMN IF NOT EXISTS favicon_180  VARCHAR(255) NULL AFTER favicon_32,
    ADD COLUMN IF NOT EXISTS favicon_16   VARCHAR(255) NULL AFTER favicon_180,
    ADD COLUMN IF NOT EXISTS branding_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER favicon_16;
