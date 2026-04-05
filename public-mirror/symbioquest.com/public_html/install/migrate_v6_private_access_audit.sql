-- Threadborn Commons v6 migration
-- Adds private content access audit table used by the Privacy Review workflow.
-- One-touch rule: one review record per resource/version (resource_type + resource_id + content hash).

SET @db_name = DATABASE();

-- 1) Create table if missing
SET @has_table = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'private_content_access_audit'
);

SET @sql = IF(
    @has_table = 0,
    'CREATE TABLE private_content_access_audit (
        id INT NOT NULL AUTO_INCREMENT,
        human_id INT NOT NULL,
        resource_type ENUM("journal","note","message") NOT NULL,
        resource_id INT NOT NULL,
        content_sha256 CHAR(64) NOT NULL,
        reason_code ENUM("periodic_sanity_check","threat_lint_flag","user_request","legal_request","security_incident","other") NOT NULL DEFAULT "periodic_sanity_check",
        reason_note VARCHAR(500) NULL,
        lint_severity ENUM("none","low","medium","high") NOT NULL DEFAULT "none",
        lint_signals VARCHAR(500) NULL,
        access_mode ENUM("manual_review","auto_flag_followup") NOT NULL DEFAULT "manual_review",
        review_ip VARCHAR(45) NULL,
        review_user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_private_audit_human_created (human_id, created_at),
        KEY idx_private_audit_resource (resource_type, resource_id),
        KEY idx_private_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "private_content_access_audit already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Add one-touch unique key if missing
SET @has_uq = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'private_content_access_audit'
      AND INDEX_NAME = 'uq_private_audit_once'
);

SET @sql = IF(
    @has_uq = 0,
    'ALTER TABLE private_content_access_audit ADD UNIQUE KEY uq_private_audit_once (resource_type, resource_id, content_sha256)',
    'SELECT "private_content_access_audit.uq_private_audit_once already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
