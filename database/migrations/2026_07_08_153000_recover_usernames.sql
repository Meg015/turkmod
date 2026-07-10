-- Restore usernames from XenForo by matching on email.
-- Admin-sync friendly, backup-first, and idempotent.

CREATE TABLE IF NOT EXISTS `users_username_backup_20260710_184907` (
  `id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `old_username` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `backed_up_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users_username_backup_20260710_184907` (`id`, `old_username`, `user_email`, `backed_up_at`)
SELECT
    u.`id`,
    u.`username`,
    u.`email`,
    NOW()
FROM `users` u
WHERE NOT EXISTS (
    SELECT 1
    FROM `users_username_backup_20260710_184907`
    LIMIT 1
)
ORDER BY u.`id` ASC;

CREATE TEMPORARY TABLE IF NOT EXISTS `xf_username_restore_map` (
  `email_norm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`email_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `xf_username_restore_map` (`email_norm`, `new_username`)
SELECT
    LOWER(TRIM(CONVERT(xf.`email` USING utf8mb4))) COLLATE utf8mb4_unicode_ci AS `email_norm`,
    LEFT(TRIM(CONVERT(xf.`username` USING utf8mb4)), 30) AS `new_username`
FROM `turkmodxen`.`xf_user` xf
WHERE xf.`email` IS NOT NULL
  AND TRIM(xf.`email`) <> ''
  AND xf.`username` IS NOT NULL
  AND TRIM(xf.`username`) <> ''
ON DUPLICATE KEY UPDATE
    `new_username` = VALUES(`new_username`);

UPDATE `users` u
INNER JOIN `xf_username_restore_map` map
    ON LOWER(TRIM(CONVERT(u.`email` USING utf8mb4))) COLLATE utf8mb4_unicode_ci = map.`email_norm`
SET u.`username` = map.`new_username`;

DROP TEMPORARY TABLE IF EXISTS `xf_username_restore_map`;
