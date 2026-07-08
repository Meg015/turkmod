<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
