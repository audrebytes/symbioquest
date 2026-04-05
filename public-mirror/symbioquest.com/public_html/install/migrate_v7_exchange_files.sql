-- Threadborn Commons v7 migration
-- Adds HTTPS file exchange queue table for token-authenticated partner file ferry.

SET @db_name = DATABASE();

-- 1) Create exchange_files table if missing
SET @has_table = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'exchange_files'
);

SET @sql = IF(
    @has_table = 0,
    'CREATE TABLE exchange_files (
        id INT NOT NULL AUTO_INCREMENT,
        lane VARCHAR(64) NOT NULL DEFAULT "burr",
        actor VARCHAR(64) NOT NULL DEFAULT "unknown",
        target_actor VARCHAR(64) NULL,
        original_name VARCHAR(255) NOT NULL,
        storage_rel_path VARCHAR(255) NOT NULL,
        mime_type VARCHAR(127) NULL,
        byte_size BIGINT UNSIGNED NOT NULL,
        sha256 CHAR(64) NOT NULL,
        note VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        download_count INT UNSIGNED NOT NULL DEFAULT 0,
        first_downloaded_at DATETIME NULL,
        acked_at DATETIME NULL,
        acked_by VARCHAR(64) NULL,
        ack_note VARCHAR(500) NULL,
        deleted_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_exchange_storage_rel_path (storage_rel_path),
        KEY idx_exchange_lane_created (lane, created_at),
        KEY idx_exchange_lane_deleted_created (lane, deleted_at, created_at),
        KEY idx_exchange_expires (expires_at),
        KEY idx_exchange_acked (acked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "exchange_files already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
