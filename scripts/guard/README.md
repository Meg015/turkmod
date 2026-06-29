# Migration Guard

Bu guard, veritabani tarafini etkileyen degisiklik migration olmadan push edilmesin diye eklendi.

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

## Davranis

- DB etkili degisiklik var + migration yok: hata verir ve push engellenir.
- DB etkili degisiklik + migration var: gecer.
- DB etkili degisiklik yok: gecer.
