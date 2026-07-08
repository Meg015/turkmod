<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';

// First, make some dummy data
$pdo->exec("UPDATE users SET username = CONCAT('u', id, '_', RIGHT(MD5(id), 6)) WHERE id IN (1,2,3)");
$pdo->exec("UPDATE users SET email = 'ali@gmail.com' WHERE id = 1");
$pdo->exec("UPDATE users SET email = 'ali@yahoo.com' WHERE id = 2");
$pdo->exec("UPDATE users SET email = 'veli@hotmail.com' WHERE id = 3");

$sql = "
UPDATE `users`
SET `username` = CONCAT(
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
)
WHERE `username` REGEXP '^u[0-9]+_[a-f0-9]+$';
";

try {
    $pdo->exec($sql);
    echo "Update successful\n";
    $stmt = $pdo->query("SELECT id, email, username FROM users WHERE id IN (1,2,3)");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
