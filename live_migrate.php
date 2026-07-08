<?php
/**
 * GEÇİCİ MİGRATİON ÇALIŞTIRICI
 * Bu dosyayı canlı sunucunuza yükleyin ve tarayıcıdan çalıştırın (örneğin: site.com/live_migrate.php)
 * Çalıştırdıktan sonra güvenlik için bu dosyayı MUTLAKA SİLİN.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

echo "<h2>Veritabanı Senkronizasyonu Başlatılıyor...</h2>";

try {
    $syncService = new \App\Core\Database\DatabaseSyncService();
    $report = $syncService->run(true);

    echo "<pre>";
    print_r($report['summary']);
    echo "</pre>";

    if (!empty($report['errors'])) {
        echo "<h3>Hatalar:</h3><pre>";
        print_r($report['errors']);
        echo "</pre>";
    } else {
        echo "<h3 style='color:green;'>Migration'lar başarıyla uygulandı! Şimdi giriş yapabilirsiniz.</h3>";
    }

} catch (Throwable $e) {
    echo "<h3 style='color:red;'>Hata Oluştu:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<p><strong>İşlem tamamlandıktan sonra bu dosyayı (live_migrate.php) sunucunuzdan silmeyi unutmayın!</strong></p>";
