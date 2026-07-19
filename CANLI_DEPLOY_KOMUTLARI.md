# Canli Deploy Komutlari

## Local

```powershell
cd C:\xampp\htdocs\yenidosyalar
git status
composer guard:migration
git add .
git commit -m "Guncelleme"
git push origin master
```

Not:
- Eger sadece hazir commit'i gondereceksen `git add` ve `git commit` yapmana gerek yok, direkt `git push origin master` yeterli.

## Canli

```bash
cd /home/siteler/web/turkmod.net/public_html
git pull origin master
php -l includes/init.php
```

## Veritabani Senkronizasyonu (Pull Sonrasi)

Admin panelden:

1. `.../admin/database-sync/index.php` ekranini ac.
2. Bekleyen migration varsa `Bekleyen Migrationlari Uygula` butonuna bas.
3. Bekleyen sayisi `0` oldugunda veritabani guncel kabul edilir.

## Bakim Standartlari

Canliya almadan once bu dokumanlar kontrol edilmelidir:

- `docs/admin-ui-components.md`
- `docs/api-response-standard.md`
- `docs/cron-job-matrix.md`

Yeni admin ekran, AJAX endpoint veya cron gorevi eklendiyse ilgili dokuman ayni commit icinde guncellenmelidir.
