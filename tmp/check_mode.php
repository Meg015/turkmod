<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$settings = getAdminSettings($pdo);
print_r($settings['login_identifier_mode'] ?? 'NOT SET');
