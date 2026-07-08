<?php
require 'c:/xampp/htdocs/yenidosyalar/includes/init.php';
$email = 'mehmetgun015@gmail.com';
$password = 'password'; // Assuming password might be 'password' or something else

$sql = "SELECT id, username, email, password, status, password_changed_at
        FROM users
        WHERE deleted_at IS NULL AND email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo "USER NOT FOUND";
} else {
    echo "User found: " . $user['username'] . "\n";
    echo "Status: " . $user['status'] . "\n";
    // echo "Password hash: " . $user['password'] . "\n";
}
