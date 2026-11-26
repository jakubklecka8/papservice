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

## Dispečink (portál)

Adresa `/dispecink/` (rozcestník je na `/`). CZ/PL.

- zakázky v záložkách (nové, rozpracované, ke schválení, uzavřené), filtr podle projektu, technika a hledání
- nová zakázka: projekt, vyhledání provozovny, priorita, termín, nahlašovatel, rovnou přidělení technikovi
- detail: úprava, přidělení a přeřazení, čeká, zrušit, znovu otevřít, historie
- návštěvy techniků s díly a fotkami, výběr fotek pro zákazníka, vrácení technikovi k doplnění
- protokol CZ/PL: náhled (v prohlížeči Uložit jako PDF) a schválení, které zakázku uzavře
- provozovny: zakládání a úprava (admin, dispečer svého projektu)
- uživatelé (jen admin): role, projekty, provozovny zákazníka, jazyk, deaktivace
- společník a ekonom mají jen čtení

## Příjem požadavků e-mailem (M3, krok 07)

Dispečink → **Nastavení**: schránky (každá patří k projektu) a API klíč pro AI. Hesla a klíč se ukládají šifrovaně v DB.

- cron každé 2 minuty načte nové zprávy (IMAP, zprávy zůstávají nepřečtené); stejná zpráva se uloží jen jednou (Message-ID)
- odpověď ve vlákně (In-Reply-To/References nebo číslo PAP-… v předmětu) se připojí k existující zakázce i s fotkami
- nový e-mail → AI (Claude) vytáhne typ, prioritu, název a nápovědy k provozovně → provozovna se dohledá v DB; bez API klíče se hledá jen kód provozovny v textu
- nejasná provozovna → zakázka s „⚠ ověřit provozovnu“, dispečer vybere z kandidátů
- potvrzení s číslem odejde až po uložení zakázky, jednou, ve stejném vlákně, přes SMTP schránky (CZ/PL podle jazyka e-mailu)
- **E-maily** v dispečinku: přehled, k řešení (nejasné, chyby, ignorované), ruční „Vytvořit zakázku“

## Sklady (M4)

V dispečinku **Sklady**. Sklady Zásilkovna, Allegro, AmRest, BK REX, PLK REX a sklad každého technika (zakládá se sám).

- stav zásob = součet pohybů (příjem, převod, korekce, spotřeba), nikde se neukládá ručně
- převod nepustí víc, než je na skladě; opakované odeslání formuláře nic nezdvojí
- technik v aplikaci vybírá díl z katalogu a vidí, kolik ho má u sebe; po odeslání návštěvy se spotřeba odečte z jeho skladu (po vrácení a novém odeslání se přepočítá)
- díly s povinným S/N: zakázka i protokol označí chybějící číslo
- minima na skladech a seznam „Doplnit“ (návrhy doplnění zásob)

## Zákaznický portál

Adresa `/zakaznik/`. CZ/PL, funguje i v mobilu. Účet zákazníka založí admin v Uživatelích (role Zákazník + jeho provozovny).

- nový požadavek pro vlastní provozovnu, s fotkami (zmenšují se v prohlížeči)
- seznam otevřených a uzavřených požadavků, průběh (přijato → přiděleno → práce → hotovo → uzavřeno)
- schválený protokol a fotky, které dispečer označil pro zákazníka; možnost dofotit k otevřenému požadavku

## Aplikace pro techniky (PWA)

Adresa `/technik/` (z `/` se přesměruje). V telefonu otevřít v Chrome/Safari a dát **Přidat na plochu**.

- přihlášení technika, seznam přidělených otevřených zakázek, detail s navigací a voláním
- záznam návštěvy: příjezd a odjezd, km, práce, oprava, test, návod, díly se S/N, fotky (zmenšené v telefonu na 1600 px)
- stav „čeká“ s poznámkou, odeslání ke schválení
- **offline:** vše se ukládá do telefonu (IndexedDB) a odesílá frontou, až je signál. Aplikace se otevře i bez signálu.
- CZ/PL podle profilu uživatele, přepínání v menu
- **push upozornění**: nová / přeřazená zakázka, vrácená návštěva, zrušená zakázka. Zapíná se v aplikaci (banner nebo menu), v menu je i zkušební upozornění. Na iPhonu jen po přidání na plochu (iOS 16.4+). Klíče VAPID se vygenerují samy do `storage/app/private/vapid.json`. Odesílá je cron (do minuty).
- zdrojáky jsou v `public/technik/` (čisté JS, bez buildu)

## Další krok

- **Import (krok 03):** do `.env` doplnit `LEGACY_DB_*` a spustit `php artisan pap:import-legacy --inventory`. Mapování se dopíše podle exportu struktury od Piotra.
- **Webový portál a mobilní PWA** nad tímto API.
- **M3:** IMAP schránka v `pap:tick`, potvrzení e-mailem po uložení.
