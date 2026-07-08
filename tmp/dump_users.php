<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$stmt = $pdo->query("SELECT id, username, email, status FROM users LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
