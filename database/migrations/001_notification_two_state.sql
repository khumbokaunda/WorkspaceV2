-- 001 Notification read state: two independent nullable timestamps.
--
-- Replaces the single is_read flag on notifications with a two-state model:
--   seen_at  NULL means unseen, which drives the bell badge count.
--   read_at  NULL means unread, which drives the per-item highlight.
-- Notifications remain one row per recipient user, so both timestamps live
-- directly on each user's own row.
--
-- Re-runnable: every step is guarded, and the data carry-over only runs while
-- the old is_read column still exists. Targets MariaDB, which supports the
-- IF EXISTS and IF NOT EXISTS clauses used here.

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS seen_at DATETIME NULL AFTER module_key,
    ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER seen_at;

-- Carry existing state across: a row already marked read is both seen and
-- read, timestamped from when it was created. Guarded so a second run, after
-- is_read has been dropped, does not reference a missing column.
SET @has_is_read := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notifications'
      AND column_name = 'is_read'
);
SET @carry := IF(@has_is_read > 0,
    'UPDATE notifications
        SET seen_at = COALESCE(seen_at, created_at),
            read_at = COALESCE(read_at, created_at)
      WHERE is_read = 1',
    'DO 0');
PREPARE carry_stmt FROM @carry;
EXECUTE carry_stmt;
DEALLOCATE PREPARE carry_stmt;

-- The old index referenced is_read. Add the replacement indexes first: the
-- user_id foreign key needs an index with user_id as its leftmost column, so
-- the old index cannot be dropped until one of these exists to take over.
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user_seen (user_id, seen_at);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user_read (user_id, read_at);
ALTER TABLE notifications DROP INDEX IF EXISTS idx_notif_user;

ALTER TABLE notifications DROP COLUMN IF EXISTS is_read;
