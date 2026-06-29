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

Admin panelden su sirayla calistir:

1. `.../admin/database-sync/index.php?preview=1`
2. `.../admin/database-sync/index.php`
