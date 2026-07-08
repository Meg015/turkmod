<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$stmt = $pdo->query('SHOW COLUMNS FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
