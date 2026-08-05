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

## WebSocket Canli Kurulumu

Bu kurulum bir kez yapilir. Ratchet sunucusu yalnizca `127.0.0.1:8080`
uzerinde dinler; HTTPS ve WSS, Apache tarafinda sonlandirilir. `8080` veya
`8081` portlarini guvenlik duvarinda internete acmayin.

1. Uygulama `.env` dosyasina asagidaki degerleri ekleyin:

```dotenv
PUBLIC_WEBSOCKET_URL=/ws
WEBSOCKET_BIND_HOST=127.0.0.1
WEBSOCKET_PORT=8080
WEBSOCKET_BROADCAST_BIND_HOST=127.0.0.1
WEBSOCKET_BROADCAST_PORT=8081
WEBSOCKET_BROADCAST_MAX_BYTES=65536
WEBSOCKET_BROADCAST_TIMEOUT_SECONDS=3
```

2. Apache modullerini etkinlestirin ve HTTPS VirtualHost icine
`deployment/apache/mod-portal-websocket.conf` dosyasini include edin:

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel headers ssl
sudo apachectl -t
sudo systemctl reload apache2
```

3. Systemd servis dosyasindaki `User`, `Group`, `WorkingDirectory` ve PHP
yolunu sunucunuza gore duzenleyin. Ardindan kurup baslatin:

```bash
sudo install -m 0644 deployment/systemd/mod-portal-websocket.service /etc/systemd/system/mod-portal-websocket.service
sudo systemctl daemon-reload
sudo systemctl enable --now mod-portal-websocket
sudo systemctl status mod-portal-websocket --no-pager
```

4. Her uygulama deployundan sonra yeni PHP kodunu yuklemek icin servisi
yeniden baslatin ve saglik kontrolu yapin:

```bash
sudo systemctl restart mod-portal-websocket
sudo systemctl is-active --quiet mod-portal-websocket
ss -ltnp | grep -E '127\.0\.0\.1:(8080|8081)'
```

Tarayici gelistirici araclarinda baglantinin `wss://alanadiniz/ws` oldugunu
ve mesaj/bildirim geldikten sonra rozetlerin sayfa yenilenmeden degistigini
kontrol edin.

Deploy betigi:
- `origin/master` dalini sadece fast-forward olarak uygular.
- Commit ile silinen takipli dosyalari Git uzerinden canlidan da kaldirir.
- Takipsiz ve ignore edilmeyen eski uygulama kalintilarini temizler.
- `.env`, `uploads/`, `storage/`, `vendor/` ve `includes/storage/` yollarini korur.
- Composer production bagimliliklarini yeniler ve tum PHP dosyalarini lint eder.
- WebSocket systemd servisi varsa, deploy sonrasi elle yeniden baslatilmalidir.

Onemli: Kok dizinde kapsamsiz `git clean -x` veya `git clean -X` kullanilmaz.
Deploy betigi `-x` kipini yalniz kaldirilan gelistirme yollarinin acik listesinde
kullanir; upload, ortam ve runtime yollarini bu listeye dahil etmez.

## Veritabani Senkronizasyonu (Pull Sonrasi)

Admin panelden:

1. `.../admin/database-sync/index.php` ekranini ac.
2. Bekleyen migration varsa `Bekleyen Migrationlari Uygula` butonuna bas.
3. Bekleyen sayisi `0` oldugunda veritabani guncel kabul edilir.
