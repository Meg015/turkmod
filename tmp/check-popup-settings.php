<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=turkmod;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '');
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'popup_%' ORDER BY setting_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['setting_key'] . ' = ' . var_export($row['setting_value'], true) . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'DB Error: ' . $e->getMessage() . PHP_EOL;
}
