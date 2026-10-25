# Veritabanı senkronizasyon listesinin temizlenmesi

Kullanıcının talebi: önceki git aktarımlarında tamamlanan senkronizasyonları
admin panelinden kaldırmak ve değişiklik canlıya aktarıldığında da temiz
listeyi göstermek.

Panel, senkronizasyon raporundaki uygulanmış migrationları liste ve
sayaçlardan çıkarır. Bekleyen veya hatalı migrationlar görünür kalır.
İşlemi kalmayan modüller listelenmez. Boş listeler veritabanının güncel
olduğunu açıklar. Başarıyla uygulanan yeni migrationlar yönlendirmeden
sonraki kontrolde otomatik olarak listeden çıkar.

Filtre admin görünümünde uygulanır. Migration dosyaları ve veritabanındaki
uygulama kayıtları korunur; böylece eski işlemler tekrar çalışmaz ve başka
ortamlardaki henüz uygulanmamış migrationlar atlanmaz. Git ile aktarılan
görünüm kodu canlı ortamın kendi raporunu aynı kuralla temizler.

Doğrulama: PHP sözdizimi; gerçek yerel raporda 48 uygulanmış dosyanın
listelenmemesi; karışık raporda bekleyen/hatalı işlemlerin ve hatalı
modüllerin korunması; mevcut migration kayıtlarının değişmemesi.
