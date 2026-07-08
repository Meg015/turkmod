<?php
/**
 * KULLANICI ADI KURTARICI
 * Bu dosyayı canlı sunucunuza yükleyip tarayıcıdan çalıştırın (site.com/restore_usernames.php)
 * İşlem bittikten sonra bu dosyayı silmeyi unutmayın!
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

echo "<h2>Kullanıcı Adları E-posta Adreslerinden Kurtarılıyor...</h2>";

try {
    $stmt = $pdo->query("SELECT id, email, username FROM users WHERE username LIKE 'u%_%' OR username = '' OR username IS NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    
    foreach ($users as $user) {
        // E-postanın '@' işaretinden önceki kısmını al
        $parts = explode('@', $user['email']);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($parts[0]));
        if (strlen($baseName) < 3) {
            $baseName .= $user['id'];
        }
        
        $newName = $baseName;
        $counter = 1;
        
        // Bu kullanıcı adının sistemde daha önce alınıp alınmadığını kontrol et
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->execute([$newName, $user['id']]);
            if (!$checkStmt->fetch()) {
                break; // Kullanıcı adı boşta
            }
            // Doluysa sonuna numara ekle
            $newName = $baseName . $counter;
            $counter++;
        }
        
        // Kullanıcı adını güncelle
        $updateStmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $updateStmt->execute([$newName, $user['id']]);
        $updated++;
    }
    
    echo "<h3 style='color:green;'>Başarılı! Toplam $updated adet kullanıcı adı (u1_... şeklindeki isimler) e-posta adreslerinden anlaşılır hale getirildi.</h3>";

} catch (Throwable $e) {
    echo "<h3 style='color:red;'>Hata Oluştu:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<p><strong>İşlem bitti! Lütfen sunucunuzdan restore_usernames.php dosyasını silin.</strong></p>";
