# PAP Service – portál a API (M1)

Laravel 13 (PHP 8.3) + MySQL. Jedno API `/api/v1` pro webový portál i mobilní aplikaci.
Běží na sdíleném hostingu Webglobe **bez SSH**: nasazení přes FTP z GitHubu, cron přes URL, údržba přes token.

## Co je hotové (M1 + jádro M2)

- Datový model: projekty, koncoví zákazníci, provozovny, uživatelé s kombinovatelnými rolemi, požadavky, historie stavů, návštěvy, díly (2 sériová čísla), přílohy, protokoly CZ/PL. Všude je `legacy_id` pro import.
- Role: `admin`, `ekonom`, `spolecnik` (jen čtení), `dispecer` (svoje projekty), `technik` (jen přidělené zakázky), `zakaznik` (jen svoje provozovny).
- Oprávnění se kontrolují v API i při stahování souborů. Přílohy leží mimo web (`storage/app/private`).
- Pilotní tok: dispečer založí a přidělí → technik uloží návštěvu (čas, km, práce, test, díly, fotky) → odešle → dispečer vytvoří protokol CZ/PL → schválí → zakázka se uzavře. Neúplnou návštěvu jde vrátit k doplnění.
- Offline: návštěvy, díly a fotky mají UUID z telefonu, takže opakované odeslání nevytvoří duplicitu.
- Testy kontrolních bodů 2.1, 2.2, 2.3, 3.1 (API), 3.2 a idempotence pro 6.1: `php artisan test`.

## Nasazení na Webglobe (jednorázově)

1. **Subdoména** `test.servicepap.com` s vlastní databází MySQL. Document root nastav na `/public`, a pokud to nejde, nech ho v kořeni (přesměruje to `.htaccess`).
2. **GitHub:** soukromé repo. V *Settings → Environments* založ `test` a `production` a do každého dej:
   - secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `PAP_MAINTENANCE_TOKEN`
   - variables: `FTP_DIR` (složka subdomény, např. `/test.servicepap.com/`), `APP_URL` (např. `https://test.servicepap.com`)
3. **`.env` na serveru:** zkopíruj `.env.example` přes FileZillu jako `.env` a vyplň DB, `APP_URL`, tokeny (`openssl rand -hex 32`) a admina. `APP_KEY` vygeneruj lokálně přes `php artisan key:generate --show`.
   Na testu nastav `PAP_SEED_DEMO=true` a `APP_ENV=staging`.
4. **Push do `develop`.** Action spustí testy, nahraje aplikaci přes FTP a zavolá `setup`, `migrate` a `optimize`. První nahrání trvá déle, protože se nahrává i `vendor/`.
5. **Seed** (projekty, admin, případně demo účty), jednou ručně:
   ```
   curl -X POST -H "X-Maintenance-Token: <token>" https://test.servicepap.com/_maint/seed
   ```
6. **Cron** ve Webglobe každou minutu:
   - když umí PHP CLI: `php /cesta/k/aplikaci/artisan pap:tick`
   - jinak URL: `https://test.servicepap.com/_cron/<PAP_CRON_TOKEN>`
7. **Kontrola:** `https://test.servicepap.com/_health` musí vrátit `database: true`, `storage: true`, a po pár minutách i `cron_ok: true`.

Větev `develop` jde na test, `main` na produkci.

## Údržba bez SSH

`POST /_maint/{akce}` s hlavičkou `X-Maintenance-Token`. Akce: `migrate`, `migrate-status`, `seed`, `optimize`, `optimize-clear`, `setup`, `tick`.
Se špatným nebo chybějícím tokenem vrací 404.

## Demo účty (jen s `PAP_SEED_DEMO=true`)

Heslo pro všechny je `demo1234`: `dispecer@`, `technik1@`, `technik2@`, `spolecnik@`, `ekonom@` a `zakaznik@demo.test`.

## API – přehled

Přihlášení: `POST /api/v1/auth/login {email, password, device_name}` vrátí `token`. Dál se posílá hlavička `Authorization: Bearer <token>`.

| Metoda | Cesta | Kdo |
|---|---|---|
| GET | `/tickets?status=&project_id=&mine=1&open=1&q=&updated_since=` | všichni (filtrováno) |
| POST | `/tickets` | dispečer, admin, zákazník (svoje provozovna) |
| GET/PATCH | `/tickets/{id}` | zobrazení dle přístupu / úprava dispečer |
| POST | `/tickets/{id}/assign`, `/tickets/{id}/status` | dispečer (technik jen probíhá/čeká) |
| PUT | `/tickets/{id}/visits/{uuid}` | přidělený technik (idempotentní upsert) |
| POST | `/visits/{uuid}/submit` · `/visits/{uuid}/return` | technik · dispečer |
| POST | `/visits/{uuid}/protocol {language: cs\|pl}` | dispečer |
| GET/POST | `/protocols/{id}` · `/protocols/{id}/html` · `/protocols/{id}/approve` | dle přístupu · dispečer |
| POST | `/attachments` (multipart: uuid, attachable_type, attachable_id, file) | dle zakázky |
| GET | `/attachments/{uuid}/download` | jen s oprávněním |
| PATCH | `/attachments/{uuid} {customer_visible}` | dispečer |
| GET | `/projects`, `/locations`, `/technicians` | číselníky |

## Další krok

- **Import (krok 03):** do `.env` doplnit `LEGACY_DB_*` a spustit `php artisan pap:import-legacy --inventory`. Mapování se dopíše podle exportu struktury od Piotra.
- **Webový portál a mobilní PWA** nad tímto API.
- **M3:** IMAP schránka v `pap:tick`, potvrzení e-mailem po uložení.
