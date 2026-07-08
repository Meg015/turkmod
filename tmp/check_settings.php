<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$stmt = $pdo->query("SELECT * FROM admin_settings WHERE setting_key = 'login_identifier_mode'");
print_r($stmt->fetch());
