-- 023 Persistent device token (fingerprint strengthening addendum).
--
-- Adds a durable, server-issued device identifier alongside the inferred
-- fingerprint hash. The token is a hard identifier that survives cache clears
-- and browser updates; the hash is the soft fallback for when the token is
-- absent (cleared storage, a private window). The two are independent: the
-- token is NEVER folded into the hash.
--
-- Additive only, and safe to run twice.

-- device_token: the value stored in the browser's localStorage and replayed on
-- every login and check-in. NULL for historical rows recorded before this
-- migration and for any event where the client never sent one.
ALTER TABLE device_events
    ADD COLUMN IF NOT EXISTS device_token CHAR(40) NULL AFTER device_hash;

ALTER TABLE device_events
    ADD KEY IF NOT EXISTS idx_dev_token (device_token);
