# Katéter adatbevitel (PHP + SQLite)

Egyszerű, IIS alatt futtatható PHP alkalmazás beteg- és katéter-adatok rögzítésére.

## Funkciók

- Bejelentkezés fix felhasználókkal:
  - `jkk1 / bevitel1`
  - `jkk5 / bevitel5`
- Beteg adatok mentése/szerkesztése.
- Több katéter rögzítése egy beteghez.
- Keresés betegek és katéterek között.
- Egyedi azonosító mentése minden rekordhoz (`PATIENT-000001-jkk1`, `CATHETER-000001-jkk1`).
- XLSX export két munkalappal:
  - `Betegek`
  - `Kateterek`

## Fájlok

- `index.php` – teljes webes felület és vezérlés.
- `db.php` – SQLite kapcsolat és tábla inicializálás.
- `config.php` – alapbeállítások, felhasználók.
- `helpers.php` – segédfüggvények.
- `xlsx.php` – külső csomag nélküli XLSX export.

## Indítás helyben

```bash
php -S 0.0.0.0:8080 -t /workspace/simple
```

Majd böngészőben: `http://localhost:8080/index.php`

## IIS rövid útmutató (Windows Server 2022, HTTP/80)

1. Telepíts PHP-t IIS-hez (FastCGI).
2. A webhely gyökere legyen ez a mappa.
3. Alapértelmezett dokumentumokhoz add hozzá: `index.php`.
4. A `data` mappára írható jog kell az IIS alkalmazás felhasználónak.
5. Első futáskor az alkalmazás automatikusan létrehozza az SQLite adatbázist: `data/app.sqlite`.

> Ha inkább MSSQL/MySQL kell, a `db.php` PDO DSN-jét kell átállítani és a sémát migrálni.
