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
bash scripts/deploy-production.sh --dry-run
bash scripts/deploy-production.sh
```

Deploy betigi:
- `origin/master` dalini sadece fast-forward olarak uygular.
- Commit ile silinen takipli dosyalari Git uzerinden canlidan da kaldirir.
- Takipsiz ve ignore edilmeyen eski uygulama kalintilarini temizler.
- `.env`, `uploads/`, `storage/`, `vendor/` ve `includes/storage/` yollarini korur.
- Composer production bagimliliklarini yeniler ve tum PHP dosyalarini lint eder.

Onemli: `git clean -x` veya `git clean -X` canlida kullanilmaz. Bu kipler upload,
ortam ve runtime verilerini silebilir.

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
