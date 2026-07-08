<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$stmt = $pdo->query('SELECT id, email, username FROM users LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
