-- RAFT v2.0.0 migration
-- Run this if upgrading from v1.0.0
-- Safe to run once — uses IF NOT EXISTS / IF EXISTS guards

ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `url_token` VARCHAR(6) NULL DEFAULT NULL AFTER `id`;

-- Generate url_token for existing projects
UPDATE `projects`
SET `url_token` = LOWER(CONV(FLOOR(RAND() * POW(16,6)), 10, 16))
WHERE `url_token` IS NULL;

-- Now enforce NOT NULL and UNIQUE
ALTER TABLE `projects`
    MODIFY COLUMN `url_token` VARCHAR(6) NOT NULL,
    ADD UNIQUE KEY `uq_url_token` (`url_token`);