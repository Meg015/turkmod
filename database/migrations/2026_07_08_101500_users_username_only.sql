-- ============================================================================
-- Users username-only migration (2026-07-08)
-- ============================================================================
-- Goal:
-- - make users.username the only identity column
-- - backfill/normalize usernames safely
-- - enforce NOT NULL + unique constraint
-- - drop legacy users.name column
-- ============================================================================

ALTER TABLE `users`
    ADD COLUMN `username` VARCHAR(30) NULL AFTER `id`;

UPDATE `users`
SET `username` = LOWER(TRIM(`username`))
WHERE `username` IS NOT NULL;

UPDATE `users`
SET `username` = REPLACE(`username`, ' ', '_')
WHERE `username` IS NOT NULL;

UPDATE `users`
SET `username` = CONCAT('u', `id`, '_', RIGHT(MD5(`id`), 6))
WHERE `username` IS NULL
   OR TRIM(`username`) = ''
   OR CHAR_LENGTH(`username`) < 3
   OR `username` REGEXP '[^a-z0-9_-]';

UPDATE `users`
SET `username` = LEFT(`username`, 30)
WHERE CHAR_LENGTH(`username`) > 30;

UPDATE `users` u
INNER JOIN `users` older
    ON older.`username` = u.`username`
   AND older.`id` < u.`id`
SET u.`username` = CONCAT('u', u.`id`, '_', RIGHT(MD5(u.`id`), 6));

UPDATE `users`
SET `username` = LEFT(`username`, 30)
WHERE CHAR_LENGTH(`username`) > 30;

ALTER TABLE `users`
    ADD UNIQUE INDEX `users_username_unique` (`username`);

ALTER TABLE `users`
    MODIFY COLUMN `username` VARCHAR(30) NOT NULL;

ALTER TABLE `users`
    DROP COLUMN `name`;
