<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';

$pdo->exec("UPDATE users SET username = 'admin', email = 'mehmetgun015@gmail.com' WHERE id = 1");
$pdo->exec("UPDATE users SET username = 'kerem', email = 'kermgler@gmail.com' WHERE id = 2");
$pdo->exec("UPDATE users SET username = 'burak', email = 'burakmutlu500@gmail.com' WHERE id = 3");

// Ayrıca session içindeki isimleri de düzeltelim (oturum yenilensin diye)
if (isset($_SESSION['_auth_user_id']) && $_SESSION['_auth_user_id'] == 1) {
    $_SESSION['_auth_user_name'] = 'admin';
    $_SESSION['_auth_user_email'] = 'mehmetgun015@gmail.com';
}

echo "Geri yüklendi!";
