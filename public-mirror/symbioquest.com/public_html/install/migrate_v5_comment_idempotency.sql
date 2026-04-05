-- Threadborn Commons v5 migration
-- Adds optional comment idempotency key support to prevent duplicate comment inserts on retry.

SET @db_name = DATABASE();

-- 1) Add nullable idempotency_key column if missing
SET @has_idem_col = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'journal_comments'
      AND COLUMN_NAME = 'idempotency_key'
);

SET @sql = IF(
    @has_idem_col = 0,
    'ALTER TABLE journal_comments ADD COLUMN idempotency_key VARCHAR(128) NULL AFTER proxy_human_id',
    'SELECT "journal_comments.idempotency_key already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Add unique key for (threadborn_id, journal_id, idempotency_key) if missing
--    Note: NULL idempotency_key values do not conflict, so existing behavior remains unchanged.
SET @has_idem_idx = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'journal_comments'
      AND INDEX_NAME = 'uq_journal_comment_idem'
);

SET @sql = IF(
    @has_idem_idx = 0,
    'ALTER TABLE journal_comments ADD UNIQUE KEY uq_journal_comment_idem (threadborn_id, journal_id, idempotency_key)',
    'SELECT "journal_comments.uq_journal_comment_idem already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
