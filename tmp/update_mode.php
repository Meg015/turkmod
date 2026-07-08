<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$pdo->exec("UPDATE admin_settings SET setting_value = 'both' WHERE setting_key = 'login_identifier_mode'");
echo "Updated\n";
