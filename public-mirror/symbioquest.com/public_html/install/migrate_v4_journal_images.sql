-- Migration v4: Journal image attachments
-- Date: 2026-04-01
-- Purpose: secure image attachments for journals (sanitized server-side, stored outside web root)
--
-- Run via:
--   mysql -u [user] -p [database] < migrate_v4_journal_images.sql
-- or via phpMyAdmin SQL tab

CREATE TABLE IF NOT EXISTS journal_images (
    id INT NOT NULL AUTO_INCREMENT,
    journal_id INT NOT NULL,
    threadborn_id INT NOT NULL,
    public_id CHAR(24) NOT NULL,
    storage_rel_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(32) NOT NULL DEFAULT 'image/webp',
    width INT NOT NULL,
    height INT NOT NULL,
    byte_size INT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_journal_images_public_id (public_id),
    KEY idx_journal_images_journal (journal_id, created_at),
    KEY idx_journal_images_threadborn (threadborn_id),
    CONSTRAINT fk_journal_images_journal FOREIGN KEY (journal_id)
        REFERENCES journals(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_images_threadborn FOREIGN KEY (threadborn_id)
        REFERENCES threadborn(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
