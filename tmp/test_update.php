<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$sql = "UPDATE `users`
SET `username` = CONCAT(
    SUBSTRING_INDEX(`email`, '@', 1),
    IF(
        (SELECT COUNT(*) FROM (SELECT `email` FROM `users`) AS temp WHERE SUBSTRING_INDEX(temp.`email`, '@', 1) = SUBSTRING_INDEX(`users`.`email`, '@', 1)) > 1,
        `id`,
        ''
    )
)";
$pdo->exec($sql);
echo "Done";
