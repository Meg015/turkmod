# Database Sync Cleanup Implementation Plan

> **For agentic workers:** Implement the steps in this session using the existing workspace.

**Goal:** Tamamlanan senkronizasyonları panelden ve sayaçlardan otomatik kaldırmak.

**Architecture:** Admin raporunun görünür migrationlarını durumlarına göre süz.
Uygulama motorunu ve kalıcı migration kayıtlarını koru. Aynı görünüm kodu
git aktarımından sonra her ortamın kendi bekleyen işlerini gösterir.

**Tech Stack:** PHP 8.1+, mevcut admin bileşenleri, MySQL.

**Spec:** `docs/superpowers/specs/2026-10-25-database-sync-cleanup-design.md`

## Global Constraints

- Migration dosyaları ve veritabanındaki uygulama kayıtları korunur.
- Bekleyen veya hatalı migrationlar görünür kalır.
- Çalışma alanındaki diğer değişikliklere dokunulmaz.

## Review Focus

- Tamamen uygulanmış rapor sıfır dosya ve güncel boş durum gösterir.
- Karışık rapor uygulanmış dosyaları çıkarır, bekleyen/hatalı dosyaları korur.
- Modül hatası, migration listesi boş olsa da görünür kalır.
- Başka ortamda uygulanmamış migrationlar görünür kalır.
- Sonraki senkronizasyonda tamamlanan dosyalar otomatik kaybolur.

## Task 1: Panel listesini ve sayaçları temizle

**Files:** Modify `admin/database-sync/index.php`.

- [x] GET raporundaki uygulanmış migrationları görünür modül/root listelerinden çıkar.
- [x] İşlemi kalmayan modülleri çıkar, modül hatalarını koru.
- [x] Uygulanmış sayacını kaldır; açıklama ve boş durum metinlerini güncelle.
- [x] PHP sözdizimini, karışık rapor senaryosunu ve gerçek raporun boş durumunu doğrula.

## Verification

- `php -l admin/database-sync/index.php` ve dosyaya özel `git diff --check` geçti.
- Yerel MySQL raporundaki 48 uygulanmış migration görünür listede sıfıra indi.
- Karışık rapor kontrolü bekleyen/hatalı dosyaları ve boş hatalı modülü korudu.
- İşlemler uygulanmış olarak işaretlendiğinde sonraki görünüm otomatik boşaldı.
- Kontrol öncesi ve sonrasında tüm migration takip tablolarının satırları aynıydı.
- Gerçek PHP şablonu mevcut admin yardımcılarıyla render edildi: iki güncel boş
  durum, beş durum kartı, devre dışı uygulama düğmesi ve eski dosyalar olmadan.
- Canlıya dağıtım veya git push/pull bu işlem kapsamında çalıştırılmadı.
