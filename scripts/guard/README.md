# Migration Guard + Auto Migration Stub

Bu sistem, veritabani tarafini etkileyen degisikliklerde migration disiplini
zorunlu olsun diye iki katmanda calisir:

- `pre-commit`: DB-etkili degisiklikte migration yoksa otomatik stub uretir.
- `pre-push`: migration hala yoksa push'u engeller.

## Kurulum (bir kez)

```powershell
powershell -ExecutionPolicy Bypass -File scripts/guard/install-hooks.ps1
```

## Manuel calistirma

```bash
php scripts/guard/migration_guard.php --cached
```

veya

```bash
composer guard:migration
```

Otomatik migration stub uretimini elle tetiklemek icin:

```bash
php scripts/guard/migration_autogen.php --cached --stage
```

## Davranis

- DB-etkili degisiklik + migration yok:
  - pre-commit otomatik migration stub uretir ve stage eder.
  - pre-push asamasinda migration hala yoksa push engellenir.
- DB-etkili degisiklik + migration var: gecer.
- DB-etkili degisiklik yok: gecer.

## Not

Otomatik olusan migration dosyasi bir "stub" olabilir; deploydan once SQL'i
gozden gecirip netlestirmek en guvenli yaklasimdir.
