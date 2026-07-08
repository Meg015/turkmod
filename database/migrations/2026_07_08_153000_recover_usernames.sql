-- Kullanıcı adlarını e-posta adreslerinden kurtarma migration'ı
-- Bu işlem, yanlışlıkla u1_... formatına dönüşen kullanıcı adlarını düzeltir.

UPDATE `users`
SET `username` = REPLACE(REPLACE(CONCAT(
    SUBSTRING_INDEX(`email`, '@', 1),
    IF(
        (SELECT count_val FROM (
            SELECT SUBSTRING_INDEX(`email`, '@', 1) as prefix, COUNT(*) as count_val 
            FROM `users` 
            GROUP BY prefix
        ) as t WHERE t.prefix = SUBSTRING_INDEX(`users`.`email`, '@', 1)) > 1,
        `id`,
        ''
    )
), '.', ''), '+', '')
WHERE `username` REGEXP '^u[0-9]+_[a-f0-9]+$';

UPDATE `users` SET `username` = LEFT(`username`, 30) WHERE CHAR_LENGTH(`username`) > 30;
