// PAP Service – portál dispečera a administrátora.
// Čisté JS bez buildu, mluví s /api/v1 stejně jako aplikace technika.

// ================================================================ texty CZ / PL

const T = {
  cs: {
    title: 'Dispečink', email: 'E-mail', password: 'Heslo', login: 'Přihlásit', loginFailed: 'Špatný e-mail nebo heslo.',
    noAccess: 'Tento účet nemá přístup do dispečinku. Technici používají aplikaci /technik/.',
    navTickets: 'Zakázky', navNew: 'Nová zakázka', navLocations: 'Provozovny', navUsers: 'Uživatelé', navTechApp: 'Aplikace technika',
    logout: 'Odhlásit', lang: 'Polski',
    tabs: { new: 'Nové', active: 'Rozpracované', approve: 'Ke schválení', closed: 'Uzavřené', all: 'Vše' },
    allProjects: 'Všechny projekty', allTechs: 'Všichni technici', search: 'Hledat číslo, název, provozovnu…',
    col: { number: 'Číslo', title: 'Požadavek', location: 'Provozovna', tech: 'Technik', status: 'Stav', prio: 'Priorita', due: 'Termín', created: 'Založeno' },
    noTickets: 'Žádné zakázky.', prev: '← Předchozí', next: 'Další →', page: 'Strana',
    status: { new: 'Nový', assigned: 'Přidělený', in_progress: 'Probíhá', waiting: 'Čeká', completed: 'Dokončený', closed: 'Uzavřený', cancelled: 'Zrušený' },
    vstatus: { draft: 'Rozpracovaná', submitted: 'Odeslaná', returned: 'Vrácená', approved: 'Schválená' },
    prio: { low: 'Nízká', normal: 'Normální', high: 'Vysoká', urgent: 'Urgentní' },
    stype: { servis: 'Servis', profilaxe: 'Profylaxe', udrzba: 'Údržba', instalace: 'Instalace' },
    test: { ok: 'V pořádku', failed: 'Nevyhověl', na: 'Neprováděl se' },
    project: 'Projekt', location: 'Provozovna', pickLocation: 'Klikni a vyber, nebo hledej kód, název, město…', change: 'Změnit',
    noLocFound: 'Nic nenalezeno', pickFromList: 'Vyber provozovnu ze seznamu, nebo v seznamu klikni na „➕ Nová provozovna“.',
    reqTitle: 'Název / závada', description: 'Popis', serviceType: 'Typ', priority: 'Priorita', due: 'Termín (SLA)',
    reporter: 'Nahlásil', reporterName: 'Jméno', reporterEmail: 'E-mail', reporterPhone: 'Telefon',
    assignNow: 'Rovnou přidělit technikovi', none: '— nikdo —', create: 'Založit', save: 'Uložit', cancel: 'Zrušit', close: 'Zavřít',
    created: 'Zakázka založena', saved: 'Uloženo',
    info: 'Zakázka', edit: 'Upravit', address: 'Adresa', createdAt: 'Založeno', source: 'Zdroj', closedAt: 'Uzavřeno',
    sources: { manual: 'dispečer', portal: 'zákaznický portál', email: 'e-mail', import: 'import' },
    assignment: 'Přidělení', technician: 'Technik', assign: 'Přidělit', reassign: 'Přeřadit', assigned: 'Přiděleno',
    noTechInProject: 'V projektu nejsou technici – zobrazuji všechny.',
    actions: 'Akce', setWaiting: 'Čeká', setCancelled: 'Zrušit zakázku', reopen: 'Znovu otevřít', confirmCancel: 'Opravdu zrušit zakázku?',
    note: 'Poznámka', history: 'Historie',
    visits: 'Návštěvy', noVisits: 'Technik zatím nic nezapsal.', arrived: 'Příjezd', departed: 'Odjezd', minutes: 'Na místě', km: 'Km',
    work: 'Provedené práce', repair: 'Oprava / příčina', testResult: 'Test', instruction: 'Návod', parts: 'Díly',
    partName: 'Díl', qty: 'Ks', snOut: 'S/N demont.', snIn: 'S/N mont.', photos: 'Fotky', forCustomer: 'pro zákazníka',
    returnVisit: 'Vrátit technikovi', returnNote: 'Co má technik doplnit?', protocolCs: 'Protokol CZ', protocolPl: 'Protokol PL', protocolM4b: 'Protokol M4B', signatures: 'Podpisy',
    returnedNote: 'Vráceno:', protocols: 'Protokoly', preview: 'Náhled', approve: 'Schválit a uzavřít',
    confirmApprove: 'Schválit protokol? Zakázka se uzavře a zákazník protokol uvidí.', approved: 'Schváleno',
    pstatus: { draft: 'Návrh', approved: 'Schválený' },
    locations: 'Provozovny', newLocation: 'Nová provozovna', code: 'Kód', name: 'Název', street: 'Ulice', city: 'Město', zip: 'PSČ',
    country: 'Země', lat: 'Šířka (lat)', lng: 'Délka (lng)', endCustomer: 'Koncový zákazník', active: 'Aktivní', inactive: 'Neaktivní',
    users: 'Uživatelé', newUser: 'Nový uživatel', phone: 'Telefon', language: 'Jazyk', roles: 'Role', projects: 'Projekty',
    customerLocations: 'Provozovny zákazníka', newPassword: 'Nové heslo (prázdné = beze změny)', filter: 'Filtrovat…',
    role: { admin: 'Admin', ekonom: 'Ekonom', spolecnik: 'Společník', dispecer: 'Dispečer', technik: 'Technik', zakaznik: 'Zákazník' },
    readOnly: 'Máš přístup jen pro čtení.', error: 'Chyba', conflict: 'Data návštěvy se změnila – vytvoř protokol znovu.',
    loadFailed: 'Nepodařilo se načíst data.', yes: 'Ano', no: 'Ne',
    navStock: 'Sklady', stockLevels: 'Stav zásob', stockMoves: 'Pohyby', stockParts: 'Katalog dílů', stockReorder: 'Doplnit',
    warehouse: 'Sklad', allWarehouses: 'Všechny sklady', whType: { central: 'Centrální', project: 'Projektové', technician: 'Technici' },
    partNo: 'Kat. číslo', unit: 'Jedn.', serialized: 'S/N povinné', qtyCol: 'Stav', minCol: 'Minimum', setMin: 'Nastavit minimum (0 = zrušit)',
    receipt: 'Příjem', transfer: 'Převod', adjustment: 'Korekce', consumption: 'Spotřeba',
    mvType: 'Typ pohybu', from: 'Ze skladu', to: 'Do skladu', quantity: 'Množství', serial: 'Sériové číslo', available: 'K dispozici',
    adjustHint: 'Korekce: vyplň jen „Ze skladu“ (úbytek) nebo jen „Do skladu“ (přírůstek).',
    newPart: 'Nový díl', when: 'Kdy', who: 'Kdo', ticketCol: 'Zakázka', nonzero: 'Jen nenulové', pickPart: 'Vyber díl…',
    suggested: 'Doplnit', noReorder: 'Nic není pod minimem.', missingSn: 'chybí S/N', moved: 'Pohyb uložen',
    navEmails: 'E-maily', navSettings: 'Nastavení', emailCard: 'Z e-mailu', confirmSent: 'Potvrzení s číslem odesláno',
    confirmNotSent: 'Potvrzení zatím neodesláno', needsReview: 'Ověř provozovnu – z e-mailu nebyla jasná.', aiSays: 'AI', useLoc: 'Použít',
    noCandidates: 'Žádný kandidát – vyber provozovnu přes Upravit.', reviewBadge: 'ověřit provozovnu',
    est: { new: 'Čeká', ticket: 'Zakázka', appended: 'Připojeno', review: 'K ověření', ignored: 'Ignorováno', error: 'Chyba' },
    etab: { todo: 'K řešení', all: 'Vše' }, from_: 'Od', subject: 'Předmět', received: 'Přijato', attachments: 'Přílohy',
    ignore: 'Ignorovat', makeTicket: 'Vytvořit zakázku', noEmails: 'Žádné e-maily.',
    aiTitle: 'AI asistent (Claude)', apiKey: 'API klíč', apiKeySet: 'nastaven', apiKeyNone: 'není nastaven – použije se jednoduché vyhodnocení',
    model: 'Model', testAi: 'Otestovat AI', removeKey: 'Odebrat klíč', mailboxes: 'E-mailové schránky', newMailbox: 'Nová schránka',
    imap: 'Příjem (IMAP)', smtp: 'Odesílání (SMTP)', host: 'Server', port: 'Port', encryption: 'Šifrování', username: 'Uživatel',
    folder: 'Složka', fromAddress: 'Odesílatel (e-mail)', fromName: 'Odesílatel (jméno)', dedicated: 'Jen na požadavky',
    dedicatedHint: 'Ne = AI rozliší, co je požadavek; ostatní pošta se jen zaznamená.', autoReply: 'Posílat potvrzení s číslem',
    pwKeep: 'prázdné = beze změny', testConn: 'Otestovat připojení', fetchNow: 'Načíst teď', lastCheck: 'Naposledy',
    connOk: 'OK', saveFirst: 'Nejdřív ulož.', sentFolder: 'Složka Odeslané', sentHint: 'RITM s odpovědí v Odeslaných se nezakládají (vyřízeno např. ve staré appce). Název zjistíš přes Otestovat připojení.', lookback: 'Načíst zpětně (dní)', lookbackHint: 'Při uložení se schránka načte znovu od tohoto dne; nic se nezdvojí.', folders: 'Složky', sentRitm: 'RITM v Odeslaných',
    reprocess: 'Znovu zpracovat e-maily', reprocessConfirm: 'Zakázky z této schránky, na kterých se ještě nic nedělalo (nové, bez technika), se smažou a e-maily se zpracují znovu podle aktuálního nastavení. Pokračovat?',
    reportBtn: 'Odeslat report klientovi', reportResend: 'Odeslat report znovu', reportSent: 'Report odeslán', reportTo: 'Komu', reportBody: 'Text', reportPhotos: 'Fotky v příloze', reportSend: 'Odeslat', reportNoSmtp: 'Schránka nemá nastavené odesílání (SMTP) – doplň v Nastavení.',
    parser: 'Zpracování e-mailů', parsers: { ai: 'AI (obecné e-maily, M4B…)', zasilkovna_ritm: 'Zásilkovna RITM (pevný formát)' },
    parserHint: 'RITM: zpracují se jen e-maily s RITM v předmětu; stejné RITM = stejná zakázka; neznámý box se založí sám.', extId: 'Externí ID (box ID)',
  },
  pl: {
    title: 'Dyspozytornia', email: 'E-mail', password: 'Hasło', login: 'Zaloguj', loginFailed: 'Błędny e-mail lub hasło.',
    noAccess: 'To konto nie ma dostępu do dyspozytorni. Technicy używają aplikacji /technik/.',
    navTickets: 'Zlecenia', navNew: 'Nowe zlecenie', navLocations: 'Lokalizacje', navUsers: 'Użytkownicy', navTechApp: 'Aplikacja technika',
    logout: 'Wyloguj', lang: 'Čeština',
    tabs: { new: 'Nowe', active: 'W realizacji', approve: 'Do zatwierdzenia', closed: 'Zamknięte', all: 'Wszystkie' },
    allProjects: 'Wszystkie projekty', allTechs: 'Wszyscy technicy', search: 'Szukaj numeru, nazwy, lokalizacji…',
    col: { number: 'Numer', title: 'Zgłoszenie', location: 'Lokalizacja', tech: 'Technik', status: 'Status', prio: 'Priorytet', due: 'Termin', created: 'Utworzono' },
    noTickets: 'Brak zleceń.', prev: '← Poprzednia', next: 'Następna →', page: 'Strona',
    status: { new: 'Nowe', assigned: 'Przydzielone', in_progress: 'W toku', waiting: 'Oczekuje', completed: 'Zakończone', closed: 'Zamknięte', cancelled: 'Anulowane' },
    vstatus: { draft: 'Robocza', submitted: 'Wysłana', returned: 'Zwrócona', approved: 'Zatwierdzona' },
    prio: { low: 'Niski', normal: 'Normalny', high: 'Wysoki', urgent: 'Pilny' },
    stype: { servis: 'Serwis', profilaxe: 'Profilaktyka', udrzba: 'Konserwacja', instalace: 'Instalacja' },
    test: { ok: 'Poprawny', failed: 'Negatywny', na: 'Nie wykonano' },
    project: 'Projekt', location: 'Lokalizacja', pickLocation: 'Kliknij i wybierz albo szukaj kodu, nazwy, miasta…', change: 'Zmień',
    noLocFound: 'Nic nie znaleziono', pickFromList: 'Wybierz lokalizację z listy albo kliknij na liście „➕ Nowa lokalizacja”.',
    reqTitle: 'Nazwa / usterka', description: 'Opis', serviceType: 'Typ', priority: 'Priorytet', due: 'Termin (SLA)',
    reporter: 'Zgłaszający', reporterName: 'Imię i nazwisko', reporterEmail: 'E-mail', reporterPhone: 'Telefon',
    assignNow: 'Od razu przydziel technikowi', none: '— nikt —', create: 'Utwórz', save: 'Zapisz', cancel: 'Anuluj', close: 'Zamknij',
    created: 'Zlecenie utworzone', saved: 'Zapisano',
    info: 'Zlecenie', edit: 'Edytuj', address: 'Adres', createdAt: 'Utworzono', source: 'Źródło', closedAt: 'Zamknięto',
    sources: { manual: 'dyspozytor', portal: 'portal klienta', email: 'e-mail', import: 'import' },
    assignment: 'Przydział', technician: 'Technik', assign: 'Przydziel', reassign: 'Zmień technika', assigned: 'Przydzielono',
    noTechInProject: 'W projekcie brak techników – pokazuję wszystkich.',
    actions: 'Akcje', setWaiting: 'Oczekuje', setCancelled: 'Anuluj zlecenie', reopen: 'Otwórz ponownie', confirmCancel: 'Na pewno anulować zlecenie?',
    note: 'Uwaga', history: 'Historia',
    visits: 'Wizyty', noVisits: 'Technik jeszcze nic nie zapisał.', arrived: 'Przyjazd', departed: 'Wyjazd', minutes: 'Na miejscu', km: 'Km',
    work: 'Wykonane prace', repair: 'Naprawa / przyczyna', testResult: 'Test', instruction: 'Instrukcja', parts: 'Części',
    partName: 'Część', qty: 'Szt.', snOut: 'S/N zdem.', snIn: 'S/N zam.', photos: 'Zdjęcia', forCustomer: 'dla klienta',
    returnVisit: 'Zwróć technikowi', returnNote: 'Co technik ma uzupełnić?', protocolCs: 'Protokół CZ', protocolPl: 'Protokół PL', protocolM4b: 'Protokół M4B', signatures: 'Podpisy',
    returnedNote: 'Zwrócono:', protocols: 'Protokoły', preview: 'Podgląd', approve: 'Zatwierdź i zamknij',
    confirmApprove: 'Zatwierdzić protokół? Zlecenie zostanie zamknięte, a klient zobaczy protokół.', approved: 'Zatwierdzono',
    pstatus: { draft: 'Projekt', approved: 'Zatwierdzony' },
    locations: 'Lokalizacje', newLocation: 'Nowa lokalizacja', code: 'Kod', name: 'Nazwa', street: 'Ulica', city: 'Miasto', zip: 'Kod pocztowy',
    country: 'Kraj', lat: 'Szerokość (lat)', lng: 'Długość (lng)', endCustomer: 'Klient końcowy', active: 'Aktywna', inactive: 'Nieaktywna',
    users: 'Użytkownicy', newUser: 'Nowy użytkownik', phone: 'Telefon', language: 'Język', roles: 'Role', projects: 'Projekty',
    customerLocations: 'Lokalizacje klienta', newPassword: 'Nowe hasło (puste = bez zmian)', filter: 'Filtruj…',
    role: { admin: 'Admin', ekonom: 'Ekonomista', spolecnik: 'Wspólnik', dispecer: 'Dyspozytor', technik: 'Technik', zakaznik: 'Klient' },
    readOnly: 'Masz dostęp tylko do odczytu.', error: 'Błąd', conflict: 'Dane wizyty się zmieniły – utwórz protokół ponownie.',
    loadFailed: 'Nie udało się wczytać danych.', yes: 'Tak', no: 'Nie',
    navStock: 'Magazyny', stockLevels: 'Stany', stockMoves: 'Ruchy', stockParts: 'Katalog części', stockReorder: 'Do uzupełnienia',
    warehouse: 'Magazyn', allWarehouses: 'Wszystkie magazyny', whType: { central: 'Centralne', project: 'Projektowe', technician: 'Technicy' },
    partNo: 'Nr kat.', unit: 'Jedn.', serialized: 'Wymagany S/N', qtyCol: 'Stan', minCol: 'Minimum', setMin: 'Ustaw minimum (0 = usuń)',
    receipt: 'Przyjęcie', transfer: 'Przesunięcie', adjustment: 'Korekta', consumption: 'Zużycie',
    mvType: 'Typ ruchu', from: 'Z magazynu', to: 'Do magazynu', quantity: 'Ilość', serial: 'Numer seryjny', available: 'Dostępne',
    adjustHint: 'Korekta: wypełnij tylko „Z magazynu” (ubytek) albo tylko „Do magazynu” (przyrost).',
    newPart: 'Nowa część', when: 'Kiedy', who: 'Kto', ticketCol: 'Zlecenie', nonzero: 'Tylko niezerowe', pickPart: 'Wybierz część…',
    suggested: 'Uzupełnić', noReorder: 'Nic nie jest poniżej minimum.', missingSn: 'brak S/N', moved: 'Ruch zapisany',
    navEmails: 'E-maile', navSettings: 'Ustawienia', emailCard: 'Z e-maila', confirmSent: 'Potwierdzenie z numerem wysłane',
    confirmNotSent: 'Potwierdzenie jeszcze nie wysłane', needsReview: 'Sprawdź lokalizację – z e-maila nie była jasna.', aiSays: 'AI', useLoc: 'Użyj',
    noCandidates: 'Brak kandydatów – wybierz lokalizację przez Edytuj.', reviewBadge: 'sprawdź lokalizację',
    est: { new: 'Czeka', ticket: 'Zlecenie', appended: 'Dołączono', review: 'Do sprawdzenia', ignored: 'Zignorowano', error: 'Błąd' },
    etab: { todo: 'Do załatwienia', all: 'Wszystkie' }, from_: 'Od', subject: 'Temat', received: 'Otrzymano', attachments: 'Załączniki',
    ignore: 'Ignoruj', makeTicket: 'Utwórz zlecenie', noEmails: 'Brak e-maili.',
    aiTitle: 'Asystent AI (Claude)', apiKey: 'Klucz API', apiKeySet: 'ustawiony', apiKeyNone: 'nie ustawiony – użyta zostanie prosta ocena',
    model: 'Model', testAi: 'Testuj AI', removeKey: 'Usuń klucz', mailboxes: 'Skrzynki e-mail', newMailbox: 'Nowa skrzynka',
    imap: 'Odbiór (IMAP)', smtp: 'Wysyłka (SMTP)', host: 'Serwer', port: 'Port', encryption: 'Szyfrowanie', username: 'Użytkownik',
    folder: 'Folder', fromAddress: 'Nadawca (e-mail)', fromName: 'Nadawca (nazwa)', dedicated: 'Tylko zgłoszenia',
    dedicatedHint: 'Nie = AI rozpozna, co jest zgłoszeniem; pozostała poczta zostanie tylko zapisana.', autoReply: 'Wysyłaj potwierdzenie z numerem',
    pwKeep: 'puste = bez zmian', testConn: 'Testuj połączenie', fetchNow: 'Pobierz teraz', lastCheck: 'Ostatnio',
    connOk: 'OK', saveFirst: 'Najpierw zapisz.', sentFolder: 'Folder Wysłane', sentHint: 'RITM z odpowiedzią w Wysłanych nie są zakładane (załatwione np. w starej aplikacji). Nazwę sprawdzisz przez Testuj połączenie.', lookback: 'Wczytaj wstecz (dni)', lookbackHint: 'Po zapisaniu skrzynka zostanie wczytana ponownie od tego dnia; nic się nie zdubluje.', folders: 'Foldery', sentRitm: 'RITM w Wysłanych',
    reprocess: 'Przetwórz e-maile ponownie', reprocessConfirm: 'Zlecenia z tej skrzynki, przy których nic jeszcze nie zrobiono (nowe, bez technika), zostaną usunięte, a e-maile przetworzone ponownie według aktualnych ustawień. Kontynuować?',
    reportBtn: 'Wyślij raport klientowi', reportResend: 'Wyślij raport ponownie', reportSent: 'Raport wysłany', reportTo: 'Do', reportBody: 'Treść', reportPhotos: 'Zdjęcia w załączniku', reportSend: 'Wyślij', reportNoSmtp: 'Skrzynka nie ma ustawionej wysyłki (SMTP) – uzupełnij w Ustawieniach.',
    parser: 'Przetwarzanie e-maili', parsers: { ai: 'AI (ogólne e-maile, M4B…)', zasilkovna_ritm: 'Zásilkovna RITM (stały format)' },
    parserHint: 'RITM: przetwarzane są tylko e-maile z RITM w temacie; ten sam RITM = to samo zlecenie; nieznany box zostanie założony automatycznie.', extId: 'ID zewnętrzne (ID boxu)',
  },
};

let lang = localStorage.getItem('pap_portal_lang') || 'cs';
// ---- vyúčtování a ceník
Object.assign(T.cs, {
  navBilling: 'Vyúčtování', price: 'Cena', priceEdit: 'Upravit cenu', priceBase: 'Základ (výjezd)', priceKmRate: 'Sazba za km',
  priceKm: 'Ujeto km', priceKmAmount: 'Za km', priceExtra: 'Příplatky / slevy', priceTotal: 'Celkem', priceManual: 'ručně',
  priceTotalOverride: 'Pevná celková částka', priceTotalHint: 'Vyplň, když má zakázka dohodnutou pevnou cenu – přepíše výpočet.',
  priceFromTariff: 'z ceníku', priceNote: 'Poznámka k ceně', currency: 'Měna', priceLocked: 'Ceník zamrazen při uzavření',
  noTariff: 'Projekt nemá ceník – nastav ho v Nastavení.', priceReset: 'Vrátit na ceník',
  tariffs: 'Ceník', newTariff: 'Nová položka ceníku', allTypes: 'všechny typy', tariffBase: 'Cena za zakázku', tariffKm: 'Za km',
  tariffNote: 'Poznámka', del: 'Smazat', project: 'Projekt',
  billFrom: 'Od', billTo: 'Do', billStatus: 'Zakázky', billDone: 'Hotové + uzavřené', billClosed: 'Jen uzavřené', billAll: 'Všechny (i rozpracované)',
  billExport: 'Export do Excelu (CSV)', billByProject: 'Podle projektu', billByTech: 'Podle technika', billCount: 'Počet',
  billDate: 'Výjezd', billEmpty: 'V tomto období nejsou žádné zakázky s výjezdem.', thisMonth: 'Tento měsíc', lastMonth: 'Minulý měsíc',
});
Object.assign(T.pl, {
  navBilling: 'Rozliczenie', price: 'Cena', priceEdit: 'Edytuj cenę', priceBase: 'Podstawa (wyjazd)', priceKmRate: 'Stawka za km',
  priceKm: 'Przejechane km', priceKmAmount: 'Za km', priceExtra: 'Dopłaty / rabaty', priceTotal: 'Razem', priceManual: 'ręcznie',
  priceTotalOverride: 'Stała kwota całkowita', priceTotalHint: 'Wypełnij, gdy zlecenie ma ustaloną stałą cenę – nadpisze wyliczenie.',
  priceFromTariff: 'z cennika', priceNote: 'Uwaga do ceny', currency: 'Waluta', priceLocked: 'Cennik zamrożony przy zamknięciu',
  noTariff: 'Projekt nie ma cennika – ustaw go w Ustawieniach.', priceReset: 'Przywróć cennik',
  tariffs: 'Cennik', newTariff: 'Nowa pozycja cennika', allTypes: 'wszystkie typy', tariffBase: 'Cena za zlecenie', tariffKm: 'Za km',
  tariffNote: 'Uwaga', del: 'Usuń', project: 'Projekt',
  billFrom: 'Od', billTo: 'Do', billStatus: 'Zlecenia', billDone: 'Wykonane + zamknięte', billClosed: 'Tylko zamknięte', billAll: 'Wszystkie',
  billExport: 'Eksport do Excela (CSV)', billByProject: 'Wg projektu', billByTech: 'Wg technika', billCount: 'Ilość',
  billDate: 'Wyjazd', billEmpty: 'W tym okresie brak zleceń z wyjazdem.', thisMonth: 'Ten miesiąc', lastMonth: 'Poprzedni miesiąc',
});
const money = (v, cur) => Number(v || 0).toLocaleString(locale(), { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + (cur === 'CZK' ? 'Kč' : cur === 'EUR' ? '€' : cur === 'PLN' ? 'zł' : cur || '');
const canMoney = () => has('admin', 'dispecer', 'ekonom', 'spolecnik');

// ---- M4: zařízení, plánování, harmonogramy, projekty, kontrolní seznamy
Object.assign(T.cs, {
  navPlanning: 'Plánování', navDevices: 'Zařízení',
  calendar: 'Kalendář', schedules: 'Harmonogramy', unplanned: 'Nenaplánované', week: 'Týden', today: 'Dnes',
  plan: 'Naplánovat', planned: 'Naplánováno', plannedAt: 'Den a čas', unplan: 'Zrušit plán', planHint: 'Přetáhni zakázku do buňky technika, nebo klikni do buňky.',
  absence: 'Nepřítomnost', addAbsence: '+ Nepřítomnost', absenceNote: 'Důvod (dovolená, nemoc…)', dateFrom: 'Od', dateTo: 'Do',
  pickTicket: 'Vyber zakázku', noUnplanned: 'Vše je naplánované 👍', delete: 'Smazat',
  schedule: 'Harmonogram', newSchedule: 'Nový harmonogram', interval: 'Interval (měsíce)', nextDue: 'Další termín', leadDays: 'Založit dní předem',
  everyN: 'každé {n} měs.', runNow: 'Založit zakázku teď', created: 'Založeno', active: 'Aktivní', autoAssign: 'Rovnou přidělit technikovi',
  device: 'Zařízení', devices: 'Zařízení', newDevice: 'Nové zařízení', deviceType: 'Druh zařízení', model: 'Model', serial: 'Sériové číslo',
  installedAt: 'Instalace', handoverAt: 'Předáno', handoverTo: 'Předáno komu', devStatus: { active: 'Aktivní', removed: 'Demontováno', all: 'Vše' },
  history: 'Historie', addEvent: '+ Záznam', evt: { install: 'Instalace', handover: 'Předání', service: 'Servis', replace: 'Výměna', remove: 'Demontáž', note: 'Poznámka' },
  oldSerial: 'Původní S/N', newSerial: 'Nové S/N', exportCsv: '⬇ Soupis S/N (CSV)', noDevices: 'Žádná zařízení.', noDevice: '— bez zařízení —',
  checklist: 'Kontrolní seznam', checklists: 'Kontrolní seznamy', newChecklist: 'Nový kontrolní seznam', checklistItems: 'Body (každý na řádek, * na začátku = povinný)',
  noChecklist: '— bez seznamu —', res: { ok: 'OK', nok: 'Závada', na: 'N/A' },
  projects: 'Projekty', newProject: 'Nový projekt', serviceTypes: 'Typy služeb', countries: 'Země (CZ, PL, DE…)', protocolTpl: 'Vzhled protokolu',
  sla: 'SLA – termín za (hodin)', endCustomers: 'Koncoví zákazníci (KÓD;Název na řádek)', inactiveP: 'neaktivní',
});
Object.assign(T.pl, {
  navPlanning: 'Planowanie', navDevices: 'Urządzenia',
  calendar: 'Kalendarz', schedules: 'Harmonogramy', unplanned: 'Niezaplanowane', week: 'Tydzień', today: 'Dziś',
  plan: 'Zaplanuj', planned: 'Zaplanowano', plannedAt: 'Dzień i godzina', unplan: 'Anuluj plan', planHint: 'Przeciągnij zlecenie do komórki technika lub kliknij komórkę.',
  absence: 'Nieobecność', addAbsence: '+ Nieobecność', absenceNote: 'Powód (urlop, choroba…)', dateFrom: 'Od', dateTo: 'Do',
  pickTicket: 'Wybierz zlecenie', noUnplanned: 'Wszystko zaplanowane 👍', delete: 'Usuń',
  schedule: 'Harmonogram', newSchedule: 'Nowy harmonogram', interval: 'Interwał (miesiące)', nextDue: 'Następny termin', leadDays: 'Utworzyć dni wcześniej',
  everyN: 'co {n} mies.', runNow: 'Utwórz zlecenie teraz', created: 'Utworzono', active: 'Aktywny', autoAssign: 'Od razu przydziel technikowi',
  device: 'Urządzenie', devices: 'Urządzenia', newDevice: 'Nowe urządzenie', deviceType: 'Rodzaj urządzenia', model: 'Model', serial: 'Numer seryjny',
  installedAt: 'Instalacja', handoverAt: 'Przekazano', handoverTo: 'Przekazano komu', devStatus: { active: 'Aktywne', removed: 'Zdemontowane', all: 'Wszystkie' },
  history: 'Historia', addEvent: '+ Wpis', evt: { install: 'Instalacja', handover: 'Przekazanie', service: 'Serwis', replace: 'Wymiana', remove: 'Demontaż', note: 'Uwaga' },
  oldSerial: 'Poprzedni S/N', newSerial: 'Nowy S/N', exportCsv: '⬇ Spis S/N (CSV)', noDevices: 'Brak urządzeń.', noDevice: '— bez urządzenia —',
  checklist: 'Lista kontrolna', checklists: 'Listy kontrolne', newChecklist: 'Nowa lista kontrolna', checklistItems: 'Punkty (każdy w linii, * na początku = obowiązkowy)',
  noChecklist: '— bez listy —', res: { ok: 'OK', nok: 'Usterka', na: 'N/D' },
  projects: 'Projekty', newProject: 'Nowy projekt', serviceTypes: 'Typy usług', countries: 'Kraje (CZ, PL, DE…)', protocolTpl: 'Wygląd protokołu',
  sla: 'SLA – termin za (godzin)', endCustomers: 'Klienci końcowi (KOD;Nazwa w linii)', inactiveP: 'nieaktywny',
});
Object.assign(T.cs, { route: 'Trasa', routeOptimize: 'Seřadit optimálně', routeStart: 'Odjezd z domova', routeService: 'Min. na zastávce', routeGeocode: 'Dohledat polohy', routeMissing: 'Bez polohy: {n} – nejsou na mapě ani v odhadu km.', routeNoHome: 'Technik nemá zadanou základnu (Uživatelé → adresa) – trasa se počítá od první zastávky.', routeOpenGmaps: 'Otevřít v Google Maps', routeTotal: 'Celkem', routeDrive: 'jízda', homeAddress: 'Základna / domov (start trasy)', homeNotFound: 'adresa nenalezena', routeBack: 'návrat' });
Object.assign(T.pl, { route: 'Trasa', routeOptimize: 'Ułóż optymalnie', routeStart: 'Wyjazd z bazy', routeService: 'Min. na przystanku', routeGeocode: 'Znajdź lokalizacje', routeMissing: 'Bez lokalizacji: {n} – brak na mapie i w szacunku km.', routeNoHome: 'Technik nie ma bazy (Użytkownicy → adres) – trasa liczona od pierwszego przystanku.', routeOpenGmaps: 'Otwórz w Google Maps', routeTotal: 'Razem', routeDrive: 'jazda', homeAddress: 'Baza / dom (start trasy)', homeNotFound: 'adres nie znaleziony', routeBack: 'powrót' });
const SERVICE_TYPES = ['servis', 'profilaxe', 'udrzba', 'instalace'];
const ymdLocal = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const canPlan = () => has('admin', 'dispecer');
const canDevices = () => has('admin', 'dispecer', 'ekonom', 'spolecnik');

const t = (k) => k.split('.').reduce((o, x) => (o == null ? undefined : o[x]), T[lang]) ?? k;

// ================================================================ API

const state = {
  token: localStorage.getItem('pap_portal_token'),
  me: JSON.parse(localStorage.getItem('pap_portal_me') || 'null'),
  projects: null,
};

class ApiError extends Error {
  constructor(status, data) { super(data?.message || `HTTP ${status}`); this.status = status; this.data = data; }
}

async function api(method, path, body, { raw = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (state.token) headers.Authorization = 'Bearer ' + state.token;
  let payload;
  if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }

  const res = await fetch('/api/v1' + path, { method, headers, body: payload, cache: 'no-store' });
  if (res.status === 401 && path !== '/auth/login') { logout(true); throw new ApiError(401, {}); }
  if (raw && res.ok) return res;
  const text = await res.text();
  let data = null;
  if (text) { try { data = JSON.parse(text); } catch { data = { message: text.slice(0, 200) }; } }
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

function errText(e) {
  if (e?.data?.errors) return Object.values(e.data.errors).flat().join(' ');
  return e?.message || t('error');
}

// ================================================================ pomocníci

const $root = document.getElementById('root');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const locale = () => (lang === 'pl' ? 'pl-PL' : 'cs-CZ');
const fmt = (iso, time = true) => {
  if (!iso) return '—';
  const d = new Date(iso);
  return isNaN(d) ? '—' : d.toLocaleString(locale(), time
    ? { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: 'numeric', month: 'numeric', year: 'numeric' });
};
const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};
const fromLocalInput = (v) => (v ? new Date(v).toISOString() : null);
const chip = (cls, text) => `<span class="chip ${esc(cls)}">${esc(text)}</span>`;
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };
const addrOf = (l) => (l ? [l.street, [l.zip, l.city].filter(Boolean).join(' '), l.country].filter(Boolean).join(', ') : '');

let toastTimer;
function toast(msg, err = false) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast' + (err ? ' err' : '');
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, err ? 6000 : 3000);
}

/** Modální okno. bind(dlg, close) napojí ovládání; vrací Promise s výsledkem. */
function modal(html, bind) {
  const dlg = document.getElementById('modal');
  dlg.innerHTML = html;
  return new Promise((resolve) => {
    const close = (v) => { dlg.close(); resolve(v); };
    dlg.oncancel = () => resolve(undefined);
    dlg.querySelectorAll('[data-close]').forEach((b) => { b.onclick = () => close(undefined); });
    if (bind) bind(dlg, close);
    dlg.showModal();
  });
}

function confirmModal(text) {
  return modal(`<p>${esc(text)}</p><div class="modal-foot"><button class="btn" data-close>${esc(t('no'))}</button><button class="btn primary" data-yes>${esc(t('yes'))}</button></div>`,
    (d, close) => { d.querySelector('[data-yes]').onclick = () => close(true); });
}

function promptModal(title, required = false) {
  return modal(`<h3>${esc(title)}</h3><textarea data-in></textarea>
    <div class="modal-foot"><button class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary" data-ok>OK</button></div>`,
  (d, close) => {
    d.querySelector('[data-ok]').onclick = () => {
      const v = d.querySelector('[data-in]').value.trim();
      if (required && !v) { d.querySelector('[data-in]').focus(); return; }
      close(v);
    };
  });
}

/** Otevře soubor/HTML z API (s tokenem) v novém okně */
async function openAuthed(url) {
  const w = window.open('', '_blank');
  try {
    const res = await fetch(url, { headers: { Authorization: 'Bearer ' + state.token } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blob = await res.blob();
    w.location = URL.createObjectURL(blob);
  } catch (e) {
    w?.close();
    toast(errText(e), true);
  }
}

const imgCache = new Map();
async function loadImg(uuidAtt, img) {
  if (imgCache.has(uuidAtt)) { img.src = imgCache.get(uuidAtt); return; }
  try {
    const res = await api('GET', `/attachments/${uuidAtt}/download`, undefined, { raw: true });
    const u = URL.createObjectURL(await res.blob());
    imgCache.set(uuidAtt, u);
    img.src = u;
  } catch { /* nic */ }
}

const has = (...roles) => !!state.me && roles.some((r) => state.me.roles.includes(r));
const isAdmin = () => has('admin');
const managesProject = (pid) => isAdmin() || (has('dispecer') && state.me.project_ids.map(Number).includes(Number(pid)));
const canCreate = () => isAdmin() || (has('dispecer') && state.me.project_ids.length > 0);

async function projects() {
  if (!state.projects) state.projects = (await api('GET', '/projects')).data;
  return state.projects;
}

const techCache = new Map();
async function technicians(projectId) {
  const key = String(projectId || '');
  if (!techCache.has(key)) {
    let list = (await api('GET', '/technicians' + (projectId ? `?project_id=${projectId}` : ''))).data;
    let fallback = false;
    if (projectId && !list.length) {
      list = (await api('GET', '/technicians')).data;
      fallback = true;
    }
    techCache.set(key, { list, fallback });
  }
  return techCache.get(key);
}

// ================================================================ router a rozvržení

function parseHash() {
  const [path, qs] = location.hash.replace(/^#/, '').split('?');
  return { path: path || '/', q: new URLSearchParams(qs || '') };
}

async function route() {
  const { path, q } = parseHash();
  if (!state.token || !state.me) { renderLogin(); return; }

  let m;
  if (path === '/' || path === '') return renderTickets(q);
  if (path === '/new') return renderNewTicket();
  if ((m = path.match(/^\/t\/(\d+)$/))) return renderTicket(m[1]);
  if (path === '/locations') return renderLocations(q);
  if (path === '/stock') return renderStock(q);
  if (path === '/emails' && has('admin', 'dispecer')) return renderEmails(q);
  if (path === '/billing' && canMoney()) return renderBilling(q);
  if (path === '/planning' && canPlan()) return renderPlanning(q);
  if (path === '/devices' && canDevices()) return renderDevices(q);
  if (path === '/settings' && isAdmin()) return renderSettings();
  if (path === '/users' && isAdmin()) return renderUsers();
  location.hash = '#/';
}

function shell(active) {
  const nav = [
    ['tickets', '#/', '📋', t('navTickets')],
    canCreate() ? ['new', '#/new', '➕', t('navNew')] : null,
    canPlan() ? ['planning', '#/planning', '📅', t('navPlanning')] : null,
    ['locations', '#/locations', '📍', t('navLocations')],
    canDevices() ? ['devices', '#/devices', '🔧', t('navDevices')] : null,
    has('admin', 'dispecer', 'ekonom', 'spolecnik') ? ['stock', '#/stock', '📦', t('navStock')] : null,
    has('admin', 'dispecer') ? ['emails', '#/emails', '📧', t('navEmails')] : null,
    canMoney() ? ['billing', '#/billing', '💰', t('navBilling')] : null,
    isAdmin() ? ['settings', '#/settings', '⚙️', t('navSettings')] : null,
    isAdmin() ? ['users', '#/users', '👥', t('navUsers')] : null,
  ].filter(Boolean);

  const cur = nav.find((n) => n[0] === active);
  $root.innerHTML = `
    <div class="layout">
      <header class="mtop">
        <button class="mtop-btn" id="nav-toggle" aria-label="Menu">☰</button>
        <span class="mtop-title">${cur ? cur[2] + ' ' + esc(cur[3]) : 'PAP ' + esc(t('title'))}</span>
        ${canCreate() && active !== 'new' ? `<a class="mtop-btn" href="#/new" aria-label="${esc(t('navNew'))}">➕</a>` : ''}
      </header>
      <div class="nav-backdrop" id="nav-backdrop"></div>
      <aside class="side" id="side">
        <div class="brand"><img src="/technik/icons/icon-192.png" alt="">PAP ${esc(t('title'))}</div>
        <nav class="nav">
          ${nav.map(([k, href, ic, label]) => `<a href="${href}" class="${k === active ? 'on' : ''}">${ic} ${esc(label)}</a>`).join('')}
          <a href="/technik/" target="_blank">📱 ${esc(t('navTechApp'))}</a>
        </nav>
        <div class="side-foot">
          <div class="who">${esc(state.me.name)}</div>
          <div>${state.me.roles.map((r) => esc(t('role.' + r))).join(', ')}</div>
          <button id="btn-lang">${esc(t('lang'))}</button> · <button id="btn-logout">${esc(t('logout'))}</button>
        </div>
      </aside>
      <main class="content" id="content"><div class="spinner"></div></main>
    </div>`;
  const side = document.getElementById('side');
  const toggleNav = (open) => document.body.classList.toggle('nav-open', open);
  document.getElementById('nav-toggle').onclick = () => toggleNav(!document.body.classList.contains('nav-open'));
  document.getElementById('nav-backdrop').onclick = () => toggleNav(false);
  side.querySelectorAll('.nav a').forEach((a) => { a.addEventListener('click', () => toggleNav(false)); });
  toggleNav(false);
  document.getElementById('btn-logout').onclick = () => logout(false);
  document.getElementById('btn-lang').onclick = () => {
    lang = lang === 'cs' ? 'pl' : 'cs';
    localStorage.setItem('pap_portal_lang', lang);
    route();
  };
  document.title = `PAP ${t('title')}`;
  return document.getElementById('content');
}

// ================================================================ přihlášení

function renderLogin(msg) {
  $root.innerHTML = `
    <div class="login-wrap">
      <form class="card login" id="lf">
        <img src="/technik/icons/icon-192.png" alt="">
        <h1>PAP Service – ${esc(t('title'))}</h1>
        <label class="f"><span>${esc(t('email'))}</span><input type="email" name="email" required autocomplete="username"></label>
        <label class="f"><span>${esc(t('password'))}</span><input type="password" name="password" required autocomplete="current-password"></label>
        <div class="banner err" id="lerr" ${msg ? '' : 'hidden'}>${esc(msg || '')}</div>
        <button class="btn primary" style="width:100%">${esc(t('login'))}</button>
        <p style="text-align:center"><button type="button" class="btn sm" id="ll">${esc(t('lang'))}</button></p>
      </form>
    </div>`;
  document.getElementById('ll').onclick = () => { lang = lang === 'cs' ? 'pl' : 'cs'; localStorage.setItem('pap_portal_lang', lang); renderLogin(); };
  const f = document.getElementById('lf');
  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = document.getElementById('lerr');
    err.hidden = true;
    try {
      const res = await api('POST', '/auth/login', { email: f.email.value.trim(), password: f.password.value, device_name: 'portal' });
      const ok = res.user.roles.some((r) => ['admin', 'dispecer', 'spolecnik', 'ekonom'].includes(r));
      if (!ok) {
        state.token = res.token;
        await api('POST', '/auth/logout').catch(() => {});
        state.token = null;
        err.textContent = t('noAccess');
        err.hidden = false;
        return;
      }
      state.token = res.token;
      state.me = res.user;
      localStorage.setItem('pap_portal_token', res.token);
      localStorage.setItem('pap_portal_me', JSON.stringify(res.user));
      if (!localStorage.getItem('pap_portal_lang')) { lang = res.user.locale || 'cs'; }
      location.hash = '#/';
      route();
    } catch (ex) {
      err.textContent = ex.status === 422 || ex.status === 429 ? errText(ex) || t('loginFailed') : t('loginFailed');
      err.hidden = false;
    }
  };
}

async function logout(expired) {
  if (!expired) await api('POST', '/auth/logout').catch(() => {});
  state.token = null;
  state.me = null;
  state.projects = null;
  techCache.clear();
  localStorage.removeItem('pap_portal_token');
  localStorage.removeItem('pap_portal_me');
  renderLogin();
}

// ================================================================ seznam zakázek

const TAB_STATUS = { new: 'new', active: 'assigned,in_progress,waiting', approve: 'completed', closed: 'closed,cancelled', all: '' };
let listTimer;

async function renderTickets(q) {
  const el = shell('tickets');
  const tab = q.get('tab') || 'active';
  const project = q.get('project') || '';
  const tech = q.get('tech') || '';
  const search = q.get('q') || '';
  const page = Number(q.get('page') || 1);

  const setQ = (changes) => {
    const n = new URLSearchParams(q);
    Object.entries(changes).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)));
    if (!('page' in changes)) n.delete('page');
    location.hash = '#/?' + n.toString();
  };

  let prj, techs, summary;
  try {
    [prj, techs, summary] = await Promise.all([
      projects(),
      has('admin', 'dispecer') ? technicians(project) : Promise.resolve({ list: [] }),
      api('GET', '/tickets/summary' + (project ? `?project_id=${project}` : '')),
    ]);
  } catch (e) {
    el.innerHTML = `<div class="banner err">${esc(t('loadFailed'))} ${esc(errText(e))}</div>`;
    return;
  }
  const cnt = summary.data || {};
  const count = (tabKey) => (TAB_STATUS[tabKey] ? TAB_STATUS[tabKey].split(',') : Object.keys(cnt)).reduce((s, k) => s + (Number(cnt[k]) || 0), 0);

  el.innerHTML = `
    <div class="page-head">
      <h1>${esc(t('navTickets'))}</h1>
      ${canCreate() ? `<a class="btn primary" href="#/new">➕ ${esc(t('navNew'))}</a>` : ''}
    </div>
    <div class="tabs">
      ${Object.keys(TAB_STATUS).map((k) => `<button class="tab ${k === tab ? 'on' : ''} ${k === 'approve' && count(k) ? 'alert' : ''}" data-tab="${k}">${esc(t('tabs.' + k))}<span class="n">${count(k)}</span></button>`).join('')}
    </div>
    <div class="filters">
      <select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
      ${techs.list.length ? `<select id="f-tech"><option value="">${esc(t('allTechs'))}</option>${techs.list.map((u) => `<option value="${u.id}" ${String(u.id) === tech ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select>` : ''}
      <input type="search" id="f-q" placeholder="${esc(t('search'))}" value="${esc(search)}">
    </div>
    <div id="list"><div class="spinner"></div></div>`;

  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => setQ({ tab: b.dataset.tab }); });
  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value, tech: '' });
  el.querySelector('#f-tech')?.addEventListener('change', (e) => setQ({ tech: e.target.value }));
  el.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
  if (search) { const i = el.querySelector('#f-q'); i.focus(); i.setSelectionRange(search.length, search.length); }

  const params = new URLSearchParams({ per_page: '50', page: String(page) });
  if (TAB_STATUS[tab]) params.set('status', TAB_STATUS[tab]);
  if (project) params.set('project_id', project);
  if (tech) params.set('technician_id', tech);
  if (search) params.set('q', search);

  let res;
  try {
    res = await api('GET', '/tickets?' + params.toString());
  } catch (e) {
    el.querySelector('#list').innerHTML = `<div class="banner err">${esc(errText(e))}</div>`;
    return;
  }

  const now = Date.now();
  const rows = res.data.map((tk) => {
    const overdue = tk.due_at && new Date(tk.due_at).getTime() < now && !['completed', 'closed', 'cancelled'].includes(tk.status);
    return `
      <tr class="prio-${esc(tk.priority)}" data-id="${tk.id}">
        <td class="num">${esc(tk.number || '#' + tk.id)}${tk.external_ref ? `<div class="muted small">${esc(tk.external_ref)}</div>` : ''}</td>
        <td class="lead"><strong>${esc(tk.title)}</strong><div class="muted small">${tk.source === 'email' ? '📧 ' : ''}${esc(tk.project?.name || '')} · ${esc(t('stype.' + tk.service_type))}${tk.needs_review ? ` <span class="chip waiting">⚠ ${esc(t('reviewBadge'))}</span>` : ''}</div></td>
        <td>${tk.location ? `${esc(tk.location.code)} · ${esc(tk.location.name)}<div class="muted small">${esc(tk.location.city || '')}</div>` : '<span class="muted">—</span>'}</td>
        <td>${tk.technician ? esc(tk.technician.name) : '<span class="muted">—</span>'}</td>
        <td>${chip(tk.status, t('status.' + tk.status))}</td>
        <td class="${tk.priority === 'normal' ? 'hide-m' : ''}">${tk.priority === 'normal' ? '<span class="muted">' + esc(t('prio.normal')) + '</span>' : esc(t('prio.' + tk.priority))}</td>
        <td class="${overdue ? 'overdue' : ''} ${tk.due_at ? '' : 'hide-m'}">${esc(fmt(tk.due_at))}</td>
        <td class="muted small hide-m">${esc(fmt(tk.created_at))}</td>
      </tr>`;
  }).join('');

  const meta = res.meta || {};
  el.querySelector('#list').innerHTML = res.data.length ? `
    <div class="table-wrap"><table>
      <thead><tr>${['number', 'title', 'location', 'tech', 'status', 'prio', 'due', 'created'].map((c) => `<th>${esc(t('col.' + c))}</th>`).join('')}</tr></thead>
      <tbody>${rows}</tbody>
    </table></div>
    ${meta.last_page > 1 ? `<div class="pager">
      <button class="btn sm" data-p="${page - 1}" ${page <= 1 ? 'disabled' : ''}>${esc(t('prev'))}</button>
      <span class="muted small">${esc(t('page'))} ${meta.current_page} / ${meta.last_page}</span>
      <button class="btn sm" data-p="${page + 1}" ${page >= meta.last_page ? 'disabled' : ''}>${esc(t('next'))}</button>
    </div>` : ''}` : `<div class="card empty">${esc(t('noTickets'))}</div>`;

  el.querySelectorAll('tr[data-id]').forEach((tr) => { tr.onclick = () => { location.hash = '#/t/' + tr.dataset.id; }; });
  el.querySelectorAll('[data-p]').forEach((b) => { b.onclick = () => setQ({ page: b.dataset.p }); });

  clearTimeout(listTimer);
  const myHash = location.hash;
  listTimer = setTimeout(() => { if (location.hash === myHash && !document.getElementById('modal').open) route(); }, 60000);
}

// ================================================================ výběr provozovny

/**
 * Výběr provozovny v rámci projektu. Ukáže seznam hned po kliknutí, hledá podle
 * kódu/názvu/města a když nic nenajde, dovolí rovnou založit novou provozovnu.
 * onPick(location|null)
 */
function locationPicker(box, getProjectId, onPick, initial = null) {
  let picked = initial;

  const quickCreate = (query) => {
    const pid = getProjectId();
    box.innerHTML = `
      <div class="card" style="margin:0;background:#f8fafc">
        <strong>➕ ${esc(t('newLocation'))}</strong>
        <div class="form-grid" style="margin-top:10px">
          <label class="f"><span class="req">${esc(t('code'))}</span><input type="text" data-q="code" placeholder="např. BRNO-001"></label>
          <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" data-q="name" value="${esc(query)}"></label>
          <label class="f full"><span>${esc(t('street'))}</span><input type="text" data-q="street"></label>
          <label class="f"><span>${esc(t('city'))}</span><input type="text" data-q="city" value="${esc(query)}"></label>
          <label class="f"><span>${esc(t('zip'))}</span><input type="text" data-q="zip"></label>
          <label class="f"><span>${esc(t('country'))}</span><select data-q="country">${['CZ', 'PL', 'DE', 'SK'].map((c) => `<option>${c}</option>`).join('')}</select></label>
        </div>
        <div class="banner err small" data-err hidden></div>
        <div class="btn-row"><button type="button" class="btn primary" data-save>${esc(t('save'))}</button><button type="button" class="btn" data-back>${esc(t('cancel'))}</button></div>
      </div>`;
    const val = (k) => box.querySelector(`[data-q=${k}]`).value.trim();
    box.querySelector('[data-q=code]').focus();
    box.querySelector('[data-back]').onclick = () => draw(query);
    box.querySelector('[data-save]').onclick = async () => {
      const err = box.querySelector('[data-err]');
      err.hidden = true;
      if (!val('code') || !val('name')) { err.textContent = `${t('code')} + ${t('name')}`; err.hidden = false; return; }
      try {
        const res = await api('POST', '/locations', {
          project_id: Number(pid), code: val('code'), name: val('name'), street: val('street') || null,
          city: val('city') || null, zip: val('zip') || null, country: val('country'),
        });
        picked = res.data;
        onPick(picked);
        toast(t('saved'));
        draw();
      } catch (e) {
        err.textContent = errText(e);
        err.hidden = false;
      }
    };
  };

  const draw = (prefill = '') => {
    if (picked) {
      box.innerHTML = `<div class="picked"><span class="sp"><strong>${esc(picked.code)}</strong> · ${esc(picked.name)}<br><span class="muted small">${esc(addrOf(picked))}</span></span>
        <button type="button" class="btn sm">${esc(t('change'))}</button></div>`;
      box.querySelector('button').onclick = () => { picked = null; onPick(null); draw(); box.querySelector('input')?.focus(); };
      return;
    }
    box.innerHTML = `<div class="search-box"><input type="search" placeholder="${esc(t('pickLocation'))}" value="${esc(prefill)}"><div class="search-results" hidden></div></div>`;
    const input = box.querySelector('input');
    const results = box.querySelector('.search-results');

    const search = async () => {
      const pid = getProjectId();
      const qv = input.value.trim();
      try {
        const res = await api('GET', `/locations?per_page=30${qv ? `&q=${encodeURIComponent(qv)}` : ''}${pid ? `&project_id=${pid}` : ''}`);
        const canCreate = pid && managesProject(pid);
        results.innerHTML = (res.data.length
          ? res.data.map((l, i) => `<button type="button" data-i="${i}"><strong>${esc(l.code)}</strong> · ${esc(l.name)}<br><span class="muted small">${esc(addrOf(l))}</span></button>`).join('')
          : `<div class="muted small" style="padding:10px">${esc(t('noLocFound'))}${qv ? ` „${esc(qv)}“` : ''}</div>`)
          + (canCreate ? `<button type="button" data-new style="color:var(--brand-2);font-weight:600">➕ ${esc(t('newLocation'))}${qv ? ` „${esc(qv)}“` : ''}</button>` : '');
        results.hidden = false;
        results.querySelectorAll('[data-i]').forEach((b) => {
          b.onmousedown = (e) => e.preventDefault();
          b.onclick = () => { picked = res.data[Number(b.dataset.i)]; onPick(picked); draw(); };
        });
        const nb = results.querySelector('[data-new]');
        if (nb) { nb.onmousedown = (e) => e.preventDefault(); nb.onclick = () => quickCreate(qv); }
      } catch { /* nic */ }
    };
    const searchSoon = debounce(search, 250);
    input.addEventListener('input', searchSoon);
    input.addEventListener('focus', search);
    input.addEventListener('blur', () => setTimeout(() => { results.hidden = true; }, 150));
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') results.hidden = true;
      if (e.key === 'Enter') { e.preventDefault(); results.querySelector('[data-i]')?.click(); }
    });
    if (prefill) input.focus();
  };
  draw();
}

// ================================================================ nová zakázka

async function renderNewTicket() {
  const el = shell('new');
  let prj;
  try { prj = (await projects()).filter((p) => managesProject(p.id)); } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  let loc = null;
  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('navNew'))}</h1></div>
    <form class="card" id="nf" style="max-width:860px">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('project'))}</span>
          <select name="project_id" required>${prj.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type"></select></label>
        <div class="f full"><span class="req" style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('location'))}</span><div id="loc"></div></div>
        <label class="f full"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required maxlength="255"></label>
        <label class="f full"><span>${esc(t('description'))}</span><textarea name="description"></textarea></label>
        <label class="f"><span>${esc(t('priority'))}</span><select name="priority">${['normal', 'high', 'urgent', 'low'].map((p) => `<option value="${p}">${esc(t('prio.' + p))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('due'))}</span><input type="datetime-local" name="due_at"></label>
        <label class="f"><span>${esc(t('reporterName'))}</span><input type="text" name="reporter_name"></label>
        <label class="f"><span>${esc(t('reporterPhone'))}</span><input type="tel" name="reporter_phone"></label>
        <label class="f"><span>${esc(t('reporterEmail'))}</span><input type="email" name="reporter_email"></label>
        <label class="f"><span>${esc(t('assignNow'))}</span><select name="technician_id"></select></label>
      </div>
      <div class="banner err" id="nerr" hidden></div>
      <div class="btn-row"><button class="btn primary" type="submit">${esc(t('create'))}</button><a class="btn" href="#/">${esc(t('cancel'))}</a></div>
    </form>`;

  const f = el.querySelector('#nf');
  const formUuid = uuid();
  const onProject = async () => {
    const p = prj.find((x) => String(x.id) === f.project_id.value);
    f.service_type.innerHTML = (p?.service_types || ['servis']).map((s) => `<option value="${s}">${esc(t('stype.' + s))}</option>`).join('');
    const techs = await technicians(f.project_id.value);
    f.technician_id.innerHTML = `<option value="">${esc(t('none'))}</option>` + techs.list.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    loc = null;
    locationPicker(el.querySelector('#loc'), () => f.project_id.value, (l) => { loc = l; });
  };
  f.project_id.onchange = onProject;
  await onProject();

  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = el.querySelector('#nerr');
    err.hidden = true;
    if (!loc) { err.textContent = t('pickFromList'); err.hidden = false; el.querySelector('#loc input')?.focus(); return; }
    const btn = f.querySelector('[type=submit]');
    btn.disabled = true;
    try {
      const res = await api('POST', '/tickets', {
        uuid: formUuid,
        project_id: Number(f.project_id.value),
        location_id: loc.id,
        title: f.title.value.trim(),
        description: f.description.value.trim() || null,
        service_type: f.service_type.value,
        priority: f.priority.value,
        due_at: fromLocalInput(f.due_at.value),
        reporter_name: f.reporter_name.value.trim() || null,
        reporter_phone: f.reporter_phone.value.trim() || null,
        reporter_email: f.reporter_email.value.trim() || null,
      });
      if (f.technician_id.value) {
        await api('POST', `/tickets/${res.data.id}/assign`, { technician_id: Number(f.technician_id.value) });
      }
      toast(t('created') + ': ' + res.data.number);
      location.hash = '#/t/' + res.data.id;
    } catch (ex) {
      err.textContent = errText(ex);
      err.hidden = false;
    } finally {
      btn.disabled = false;
    }
  };
}

// ================================================================ detail zakázky

async function renderTicket(id) {
  const el = shell('tickets');
  let tk;
  try { tk = (await api('GET', '/tickets/' + id)).data; } catch (e) {
    el.innerHTML = `<div class="banner err">${esc(e.status === 403 || e.status === 404 ? t('loadFailed') : errText(e))}</div>`;
    return;
  }
  const manage = managesProject(tk.project?.id);
  const open = !['closed', 'cancelled'].includes(tk.status);
  const loc = tk.location;

  const visitsHtml = (tk.visits || []).map((v) => {
    const sn = (val, missing) => (val ? esc(val) : missing ? `<strong style="color:var(--bad)">⚠ ${esc(t('missingSn'))}</strong>` : '—');
    const parts = (v.parts || []).map((p) => `<tr><td>${esc(p.name)}${p.part_number ? ` <span class="muted small">(${esc(p.part_number)})</span>` : ''}${p.part_id ? ' <span class="chip draft">📦</span>' : ''}</td><td>${esc(p.quantity)}</td><td>${sn(p.serial_removed, p.missing_serial_removed)}</td><td>${sn(p.serial_installed, p.missing_serial_installed)}</td></tr>`).join('');
    const photos = (v.attachments || []).filter((a) => a.kind === 'photo');
    return `
      <div class="visit" data-visit="${esc(v.uuid)}">
        <div class="visit-head"><strong>${esc(v.technician?.name || '')}</strong>${chip(v.status, t('vstatus.' + v.status))}</div>
        ${v.status === 'returned' && v.return_note ? `<div class="banner warn small">${esc(t('returnedNote'))} ${esc(v.return_note)}</div>` : ''}
        <dl class="kv">
          <dt>${esc(t('arrived'))}</dt><dd>${esc(fmt(v.arrived_at))}</dd>
          <dt>${esc(t('departed'))}</dt><dd>${esc(fmt(v.departed_at))}</dd>
          <dt>${esc(t('minutes'))}</dt><dd>${v.minutes_on_site != null ? esc(v.minutes_on_site) + ' min' : '—'} · ${esc(t('km'))}: ${v.km != null ? esc(v.km) : '—'}</dd>
          <dt>${esc(t('work'))}</dt><dd class="desc">${esc(v.work_description || '—')}</dd>
          <dt>${esc(t('repair'))}</dt><dd class="desc">${esc(v.repair_description || '—')}</dd>
          <dt>${esc(t('testResult'))}</dt><dd>${v.test_result ? esc(t('test.' + v.test_result)) : '—'}${v.test_note ? ' – ' + esc(v.test_note) : ''}</dd>
          ${v.instruction_ref ? `<dt>${esc(t('instruction'))}</dt><dd>${esc(v.instruction_ref)}</dd>` : ''}
          ${v.signature_technician || v.signature_customer ? `<dt>${esc(t('signatures'))}</dt><dd class="sigs">
            ${v.signature_technician ? `<img src="${esc(v.signature_technician)}" alt="" title="${esc(v.technician?.name || '')}">` : '<span class="muted">—</span>'}
            ${v.signature_customer ? `<img src="${esc(v.signature_customer)}" alt="">` : '<span class="muted">—</span>'}
            ${v.signer_name ? `<span class="muted small">${esc(v.signer_name)}</span>` : ''}</dd>` : ''}
        </dl>
        ${(v.checklist || []).length ? `<div class="small" style="margin:10px 0 4px;font-weight:600">☑ ${esc(t('checklist'))}</div>
          <table class="parts no-resp"><tbody>${v.checklist.map((c) => `<tr><td>${esc(c.text)}${c.note ? `<div class="muted small">${esc(c.note)}</div>` : ''}</td>
            <td style="width:80px;font-weight:700;${c.result === 'nok' ? 'color:var(--bad)' : c.result === 'ok' ? 'color:var(--ok)' : ''}">${c.result ? esc(t('res.' + c.result)) : '—'}</td></tr>`).join('')}</tbody></table>` : ''}
        ${parts ? `<table class="parts"><thead><tr><th>${esc(t('partName'))}</th><th>${esc(t('qty'))}</th><th>${esc(t('snOut'))}</th><th>${esc(t('snIn'))}</th></tr></thead><tbody>${parts}</tbody></table>` : ''}
        ${photos.length ? `<div class="photos">${photos.map((a) => `
          <div class="photo"><img data-att="${esc(a.uuid)}" alt="">
            <label>${manage ? `<input type="checkbox" data-vis="${esc(a.uuid)}" ${a.customer_visible ? 'checked' : ''}>` : (a.customer_visible ? '✓' : '')} ${esc(t('forCustomer'))}</label>
          </div>`).join('')}</div>` : ''}
        ${manage && v.status === 'submitted' ? `
          <div class="btn-row" style="margin-top:12px">
            ${tk.project?.protocol_template === 'm4b'
              ? `<button class="btn success" data-proto="pl" data-v="${esc(v.uuid)}">📄 ${esc(t('protocolM4b'))}</button>`
              : `<button class="btn success" data-proto="cs" data-v="${esc(v.uuid)}">📄 ${esc(t('protocolCs'))}</button>
            <button class="btn success" data-proto="pl" data-v="${esc(v.uuid)}">📄 ${esc(t('protocolPl'))}</button>`}
            <button class="btn danger" data-return="${esc(v.uuid)}">↩ ${esc(t('returnVisit'))}</button>
          </div>` : ''}
      </div>`;
  }).join('');

  // fotky přímo u zakázky (např. od zákazníka) – fotky z návštěv se ukazují u návštěv
  const visitAtt = new Set((tk.visits || []).flatMap((v) => (v.attachments || []).map((a) => a.uuid)));
  const ticketPhotos = (tk.attachments || []).filter((a) => a.kind === 'photo' && !visitAtt.has(a.uuid));
  const protocols = (tk.protocols || []).map((p) => `
    <div class="visit-head" style="border-bottom:1px solid var(--line);padding-bottom:8px">
      <strong>${p.template === 'm4b' ? 'M4B' : esc(p.language.toUpperCase())}</strong>${chip(p.status === 'approved' ? 'approved' : 'draft', t('pstatus.' + p.status))}
      <span class="muted small">${esc(fmt(p.approved_at))}</span>
      <button class="btn sm" data-preview="${esc(p.html_url)}">${esc(t('preview'))}</button>
      <button class="btn sm" data-preview="${esc(p.pdf_url)}">PDF</button>
      ${manage && p.status === 'draft' ? `<button class="btn sm success" data-approve="${p.id}">✓ ${esc(t('approve'))}</button>` : ''}
    </div>`).join('');

  const history = (tk.history || []).slice().reverse().map((h) => `<div>${esc(fmt(h.at))} · <strong>${esc(t('status.' + h.to))}</strong>${h.user ? ' · ' + esc(h.user) : ''}${h.note ? `<div class="muted small">${esc(h.note)}</div>` : ''}</div>`).join('');

  el.innerHTML = `
    <div class="page-head">
      <h1>${esc(tk.number || '#' + tk.id)}${tk.external_ref ? ` <span class="muted" style="font-weight:500">(${esc(tk.external_ref)})</span>` : ''} · ${esc(tk.title)}</h1>
      ${chip(tk.status, t('status.' + tk.status))}
    </div>
    ${!manage ? `<div class="banner warn">${esc(t('readOnly'))}</div>` : ''}
    ${tk.needs_review ? `<div class="banner warn"><strong>⚠ ${esc(t('needsReview'))}</strong>
      ${tk.email?.ai?.reason ? `<div class="small" style="margin-top:4px">${esc(t('aiSays'))}: ${esc(tk.email.ai.reason)}</div>` : ''}
      ${(tk.email?.ai?.candidates || []).length ? `<div class="btn-row" style="margin-top:8px">${tk.email.ai.candidates.map((c) => `<button class="btn sm" data-useloc="${c.id}">${esc(c.code)} · ${esc(c.name)}${c.city ? ', ' + esc(c.city) : ''} <span class="muted">(${Math.round(c.score * 100)} %)</span></button>`).join('')}</div>` : `<div class="small" style="margin-top:4px">${esc(t('noCandidates'))}</div>`}
    </div>` : ''}
    <div class="grid-2">
      <div>
        <div class="card">
          <h2>${esc(t('info'))}<span class="sp"></span>${manage && open ? `<button class="btn sm" id="btn-edit">✎ ${esc(t('edit'))}</button>` : ''}</h2>
          <dl class="kv">
            <dt>${esc(t('project'))}</dt><dd>${esc(tk.project?.name || '')} · ${esc(t('stype.' + tk.service_type))}</dd>
            <dt>${esc(t('location'))}</dt><dd>${loc ? `<strong>${esc(loc.code)}</strong> · ${esc(loc.name)}` : '—'}</dd>
            ${loc ? `<dt>${esc(t('address'))}</dt><dd>${esc(addrOf(loc))}${loc.lat && loc.lng ? ` · <a href="https://www.google.com/maps?q=${loc.lat},${loc.lng}" target="_blank" rel="noopener">mapa</a>` : ''}</dd>` : ''}
            <dt>${esc(t('priority'))}</dt><dd>${esc(t('prio.' + tk.priority))}</dd>
            <dt>${esc(t('due'))}</dt><dd>${esc(fmt(tk.due_at))}</dd>
            <dt>📅 ${esc(t('planned'))}</dt><dd>${tk.planned_at ? `<strong>${esc(fmt(tk.planned_at))}</strong>` : '—'}${manage && open ? ` <button class="btn sm" id="btn-plan">${esc(t('plan'))}</button>` : ''}</dd>
            ${tk.device ? `<dt>🔧 ${esc(t('device'))}</dt><dd><a href="#/devices?open=${tk.device.id}">${esc(tk.device.type)}${tk.device.model ? ' · ' + esc(tk.device.model) : ''}</a>${tk.device.serial_number ? ` · S/N <strong>${esc(tk.device.serial_number)}</strong>` : ''}</dd>` : ''}
            ${tk.checklist ? `<dt>☑ ${esc(t('checklist'))}</dt><dd>${esc(tk.checklist.name)} <span class="muted small">(${tk.checklist.items.length})</span></dd>` : ''}
            ${tk.schedule ? `<dt>🔁 ${esc(t('schedule'))}</dt><dd>${esc(tk.schedule.title)} <span class="muted small">(${esc(t('everyN').replace('{n}', tk.schedule.interval_months))})</span></dd>` : ''}
            <dt>${esc(t('createdAt'))}</dt><dd>${esc(fmt(tk.created_at))} · ${esc(t('sources.' + tk.source))}</dd>
            ${tk.reporter ? `<dt>${esc(t('reporter'))}</dt><dd>${esc([tk.reporter.name, tk.reporter.phone, tk.reporter.email].filter(Boolean).join(' · ') || '—')}</dd>` : ''}
            ${tk.closed_at ? `<dt>${esc(t('closedAt'))}</dt><dd>${esc(fmt(tk.closed_at))}</dd>` : ''}
          </dl>
          ${tk.description ? `<div class="desc" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line)">${esc(tk.description)}</div>` : ''}
          ${ticketPhotos.length ? `<div class="photos">${ticketPhotos.map((a) => `<div class="photo"><img data-att="${esc(a.uuid)}" alt="">
            <label>${manage ? `<input type="checkbox" data-vis="${esc(a.uuid)}" ${a.customer_visible ? 'checked' : ''}>` : ''} ${esc(t('forCustomer'))}</label></div>`).join('')}</div>` : ''}
        </div>

        <div class="card">
          <h2>${esc(t('assignment'))}</h2>
          <p style="margin-top:0">${esc(t('technician'))}: <strong>${esc(tk.technician?.name || '—')}</strong>${tk.assigned_at ? ` <span class="muted small">(${esc(fmt(tk.assigned_at))})</span>` : ''}</p>
          ${manage && open ? `
            <div class="btn-row"><select id="as-tech" style="flex:1;min-width:180px"></select><button class="btn primary" id="btn-assign">${esc(tk.technician ? t('reassign') : t('assign'))}</button></div>
            <div class="muted small" id="as-note"></div>
            <div class="btn-row" style="margin-top:14px">
              ${tk.status !== 'waiting' ? `<button class="btn" data-st="waiting">⏸ ${esc(t('setWaiting'))}</button>` : `<button class="btn" data-st="${tk.technician ? 'assigned' : 'new'}">▶ ${esc(t('reopen'))}</button>`}
              <button class="btn danger" data-st="cancelled">✕ ${esc(t('setCancelled'))}</button>
            </div>` : ''}
          ${manage && tk.status === 'cancelled' ? `<button class="btn" data-st="new">▶ ${esc(t('reopen'))}</button>` : ''}
        </div>

        ${tk.email ? `<div class="card"><h2>📧 ${esc(t('emailCard'))}</h2>
          <dl class="kv"><dt>${esc(t('from_'))}</dt><dd>${esc(tk.email.from)}</dd><dt>${esc(t('subject'))}</dt><dd>${esc(tk.email.subject || '—')}</dd>
          <dt>${esc(t('received'))}</dt><dd>${esc(fmt(tk.email.received_at))}</dd></dl>
          <div class="small ${tk.email.confirmation_sent_at ? '' : 'muted'}" style="margin-top:8px">${tk.email.confirmation_sent_at ? '✓ ' + esc(t('confirmSent')) + ' · ' + esc(fmt(tk.email.confirmation_sent_at)) : esc(t('confirmNotSent'))}</div>
          ${tk.client_report_sent_at ? `<div class="small" style="margin-top:4px">📤 ${esc(t('reportSent'))} · ${esc(fmt(tk.client_report_sent_at))}</div>` : ''}
          ${manage ? `<div class="btn-row" style="margin-top:10px"><button class="btn ${tk.client_report_sent_at ? '' : 'primary'}" id="btn-report">📤 ${esc(tk.client_report_sent_at ? t('reportResend') : t('reportBtn'))}</button></div>` : ''}
          <a class="small" href="#/emails?open=${tk.email.id}">${esc(t('navEmails'))} →</a></div>` : ''}
        ${history ? `<div class="card"><h2>${esc(t('history'))}</h2><div class="history">${history}</div></div>` : ''}
      </div>
      <div>
        ${tk.price ? priceCard(tk.price, manage) : ''}
        ${protocols ? `<div class="card"><h2>${esc(t('protocols'))}</h2>${protocols}</div>` : ''}
        <div class="card"><h2>${esc(t('visits'))}</h2>${visitsHtml || `<div class="muted">${esc(t('noVisits'))}</div>`}</div>
      </div>
    </div>`;

  el.querySelector('#btn-report')?.addEventListener('click', () => clientReport(id));
  el.querySelector('#btn-price')?.addEventListener('click', () => editPrice(tk));
  el.querySelector('#btn-plan')?.addEventListener('click', async () => {
    if (await planModal({ id: tk.id, number: tk.number, title: tk.title, project_id: tk.project?.id, technician_id: tk.technician?.id, planned_at: tk.planned_at })) renderTicket(tk.id);
  });

  el.querySelectorAll('[data-useloc]').forEach((b) => {
    b.onclick = async () => {
      try { await api('PATCH', `/tickets/${id}`, { location_id: Number(b.dataset.useloc) }); toast(t('saved')); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
  });

  // fotky
  el.querySelectorAll('img[data-att]').forEach((img) => {
    loadImg(img.dataset.att, img);
    img.onclick = () => openAuthed(`/api/v1/attachments/${img.dataset.att}/download`);
  });
  el.querySelectorAll('[data-vis]').forEach((cb) => {
    cb.onchange = async () => {
      try { await api('PATCH', `/attachments/${cb.dataset.vis}`, { customer_visible: cb.checked }); toast(t('saved')); } catch (e) { cb.checked = !cb.checked; toast(errText(e), true); }
    };
  });

  // přidělení
  if (manage && open) {
    const sel = el.querySelector('#as-tech');
    const techs = await technicians(tk.project?.id);
    sel.innerHTML = techs.list.map((u) => `<option value="${u.id}" ${tk.technician?.id === u.id ? 'selected' : ''}>${esc(u.name)}</option>`).join('');
    if (techs.fallback) el.querySelector('#as-note').textContent = t('noTechInProject');
    el.querySelector('#btn-assign').onclick = async () => {
      try { await api('POST', `/tickets/${id}/assign`, { technician_id: Number(sel.value) }); toast(t('assigned')); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
    el.querySelector('#btn-edit').onclick = () => editTicket(tk);
  }
  el.querySelectorAll('[data-st]').forEach((b) => {
    b.onclick = async () => {
      const to = b.dataset.st;
      let note = null;
      if (to === 'cancelled' && !(await confirmModal(t('confirmCancel')))) return;
      if (to === 'waiting' || to === 'cancelled') {
        note = await promptModal(t('note'));
        if (note === undefined) return;
      }
      try { await api('POST', `/tickets/${id}/status`, { status: to, note: note || null }); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
  });

  // návštěvy a protokoly
  el.querySelectorAll('[data-return]').forEach((b) => {
    b.onclick = async () => {
      const note = await promptModal(t('returnNote'), true);
      if (!note) return;
      try { await api('POST', `/visits/${b.dataset.return}/return`, { note }); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
  });
  el.querySelectorAll('[data-proto]').forEach((b) => {
    b.onclick = async () => {
      try {
        const res = await api('POST', `/visits/${b.dataset.v}/protocol`, { language: b.dataset.proto });
        await renderTicket(id);
        openAuthed(res.data.html_url);
      } catch (e) { toast(errText(e), true); }
    };
  });
  el.querySelectorAll('[data-preview]').forEach((b) => { b.onclick = () => openAuthed(b.dataset.preview); });
  el.querySelectorAll('[data-approve]').forEach((b) => {
    b.onclick = async () => {
      if (!(await confirmModal(t('confirmApprove')))) return;
      try { await api('POST', `/protocols/${b.dataset.approve}/approve`); toast(t('approved')); renderTicket(id); } catch (e) {
        toast(e.status === 409 ? t('conflict') : errText(e), true);
        if (e.status === 409) renderTicket(id);
      }
    };
  });
}

async function clientReport(ticketId) {
  let d;
  try { d = (await api('GET', `/tickets/${ticketId}/client-report`)).data; } catch (e) { toast(errText(e), true); return; }
  const ok = await modal(`
    <h3>📤 ${esc(t('reportBtn'))}</h3>
    ${d.can_send ? '' : `<div class="banner err small">${esc(t('reportNoSmtp'))}</div>`}
    <dl class="kv"><dt>${esc(t('reportTo'))}</dt><dd>${esc(d.to)}</dd><dt>${esc(t('subject'))}</dt><dd>${esc(d.subject)}</dd></dl>
    <label class="f" style="margin-top:10px"><span>${esc(t('reportBody'))}</span><textarea id="rb" style="min-height:260px">${esc(d.body)}</textarea></label>
    ${d.photos.length ? `<div class="f"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('reportPhotos'))}</span>
      <div class="photos">${d.photos.map((p) => `<div class="photo"><img data-att="${esc(p.uuid)}" alt=""><label><input type="checkbox" data-ph="${esc(p.uuid)}" checked> ✓</label></div>`).join('')}</div></div>` : ''}
    <div class="banner err" id="rerr" hidden></div>
    <div class="modal-foot"><button class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary" id="rsend" ${d.can_send ? '' : 'disabled'}>${esc(t('reportSend'))}</button></div>`,
  (dlg, close) => {
    dlg.querySelectorAll('img[data-att]').forEach((img) => loadImg(img.dataset.att, img));
    dlg.querySelector('#rsend').onclick = async (e) => {
      e.target.disabled = true;
      try {
        await api('POST', `/tickets/${ticketId}/client-report`, {
          body: dlg.querySelector('#rb').value,
          photos: [...dlg.querySelectorAll('[data-ph]:checked')].map((c) => c.dataset.ph),
        });
        close(true);
      } catch (ex) {
        const err = dlg.querySelector('#rerr'); err.textContent = errText(ex); err.hidden = false; e.target.disabled = false;
      }
    };
  });
  if (ok) { toast(t('reportSent')); renderTicket(ticketId); }
}

async function editTicket(tk) {
  const prj = (await projects()).find((p) => p.id === tk.project?.id);
  let loc = tk.location;
  await modal(`
    <h3>${esc(t('edit'))} ${esc(tk.number)}</h3>
    <form id="ef">
      <div class="form-grid">
        <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('location'))}</span><div id="eloc"></div></div>
        <label class="f full"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required value="${esc(tk.title)}"></label>
        <label class="f full"><span>${esc(t('description'))}</span><textarea name="description">${esc(tk.description || '')}</textarea></label>
        <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type">${(prj?.service_types || ['servis']).map((s) => `<option value="${s}" ${s === tk.service_type ? 'selected' : ''}>${esc(t('stype.' + s))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('priority'))}</span><select name="priority">${['low', 'normal', 'high', 'urgent'].map((p) => `<option value="${p}" ${p === tk.priority ? 'selected' : ''}>${esc(t('prio.' + p))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('due'))}</span><input type="datetime-local" name="due_at" value="${esc(toLocalInput(tk.due_at))}"></label>
        <label class="f"><span>🔧 ${esc(t('device'))}</span><select name="device_id"><option value="">${esc(t('noDevice'))}</option></select></label>
        <label class="f"><span>☑ ${esc(t('checklist'))}</span><select name="checklist_template_id"><option value="">${esc(t('noChecklist'))}</option></select></label>
      </div>
      <div class="banner err" id="eerr" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#ef');
    const fillDevices = async () => {
      const sel = f.device_id;
      if (!loc) { sel.innerHTML = `<option value="">${esc(t('noDevice'))}</option>`; return; }
      try {
        const r = await api('GET', `/devices?location_id=${loc.id}&status=all`);
        sel.innerHTML = `<option value="">${esc(t('noDevice'))}</option>` + r.data.map((x) => `<option value="${x.id}" ${tk.device?.id === x.id ? 'selected' : ''}>${esc(x.type)}${x.model ? ' · ' + esc(x.model) : ''}${x.serial_number ? ' · ' + esc(x.serial_number) : ''}</option>`).join('');
      } catch { /* bez práv k zařízením */ }
    };
    (async () => {
      try {
        const cls = (await api('GET', '/checklists')).data.filter((c) => c.project_id === tk.project?.id);
        f.checklist_template_id.innerHTML = `<option value="">${esc(t('noChecklist'))}</option>` + cls.map((c) => `<option value="${c.id}" ${tk.checklist?.id === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
      } catch { /* */ }
    })();
    fillDevices();
    locationPicker(d.querySelector('#eloc'), () => tk.project?.id, (l) => { loc = l; fillDevices(); }, loc);
    f.onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api('PATCH', `/tickets/${tk.id}`, {
          title: f.title.value.trim(), description: f.description.value.trim() || null,
          service_type: f.service_type.value, priority: f.priority.value, due_at: fromLocalInput(f.due_at.value),
          device_id: f.device_id.value ? Number(f.device_id.value) : null,
          checklist_template_id: f.checklist_template_id.value ? Number(f.checklist_template_id.value) : null,
          ...(loc ? { location_id: loc.id } : {}),
        });
        close(true);
        toast(t('saved'));
        renderTicket(tk.id);
      } catch (ex) {
        const err = d.querySelector('#eerr');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
}

// ================================================================ provozovny

async function renderLocations(q) {
  const el = shell('locations');
  const project = q.get('project') || '';
  const search = q.get('q') || '';
  const setQ = (c) => {
    const n = new URLSearchParams(q);
    Object.entries(c).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)));
    location.hash = '#/locations?' + n.toString();
  };

  let prj, res;
  try {
    prj = await projects();
    const p = new URLSearchParams({ all: '1', per_page: '200' });
    if (project) p.set('project_id', project);
    if (search) p.set('q', search);
    res = await api('GET', '/locations?' + p.toString());
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  const pname = (id) => prj.find((p) => p.id === id)?.name || '';
  const canAdd = prj.some((p) => managesProject(p.id));

  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('locations'))}</h1>${canAdd ? `<button class="btn primary" id="btn-add">➕ ${esc(t('newLocation'))}</button>` : ''}</div>
    <div class="filters">
      <select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
      <input type="search" id="f-q" placeholder="${esc(t('filter'))}" value="${esc(search)}">
    </div>
    ${res.data.length ? `<div class="table-wrap"><table>
      <thead><tr><th>${esc(t('code'))}</th><th>${esc(t('name'))}</th><th>${esc(t('address'))}</th><th>${esc(t('project'))}</th><th></th></tr></thead>
      <tbody>${res.data.map((l, i) => `<tr data-i="${i}"><td class="num">${esc(l.code)}</td><td>${esc(l.name)}</td><td>${esc(addrOf(l))}</td><td>${esc(pname(l.project_id))}</td>
        <td>${l.is_active ? '' : chip('off', t('inactive'))}</td></tr>`).join('')}</tbody>
    </table></div>
    ${res.meta?.total > res.data.length ? `<p class="muted small">${res.data.length} / ${res.meta.total}</p>` : ''}` : `<div class="card empty">—</div>`}`;

  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value });
  el.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
  if (search) { const i = el.querySelector('#f-q'); i.focus(); i.setSelectionRange(search.length, search.length); }
  el.querySelector('#btn-add')?.addEventListener('click', () => editLocation(null, prj, project));
  el.querySelectorAll('tr[data-i]').forEach((tr) => {
    const l = res.data[Number(tr.dataset.i)];
    tr.onclick = () => (managesProject(l.project_id) ? editLocation(l, prj) : null);
  });
}

async function editLocation(l, prj, defaultProject) {
  const managed = prj.filter((p) => managesProject(p.id));
  const cur = l || { project_id: Number(defaultProject) || managed[0]?.id, country: 'CZ', is_active: true };
  const ok = await modal(`
    <h3>${esc(l ? l.code + ' · ' + l.name : t('newLocation'))}</h3>
    <form id="lf2">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${l ? 'disabled' : ''}>${managed.map((p) => `<option value="${p.id}" ${p.id === cur.project_id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('endCustomer'))}</span><select name="end_customer_id"></select></label>
        <label class="f"><span class="req">${esc(t('code'))}</span><input type="text" name="code" required value="${esc(cur.code || '')}"></label>
        <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
        <label class="f"><span>${esc(t('extId'))}</span><input type="text" name="external_id" value="${esc(cur.external_id || '')}"></label>
        <label class="f full"><span>${esc(t('street'))}</span><input type="text" name="street" value="${esc(cur.street || '')}"></label>
        <label class="f"><span>${esc(t('city'))}</span><input type="text" name="city" value="${esc(cur.city || '')}"></label>
        <label class="f"><span>${esc(t('zip'))}</span><input type="text" name="zip" value="${esc(cur.zip || '')}"></label>
        <label class="f"><span>${esc(t('country'))}</span><select name="country">${['CZ', 'PL', 'DE', 'SK'].map((c) => `<option ${c === cur.country ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1" ${cur.is_active ? 'selected' : ''}>${esc(t('yes'))}</option><option value="0" ${!cur.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
        <label class="f"><span>${esc(t('lat'))}</span><input type="number" step="any" name="lat" value="${cur.lat ?? ''}"></label>
        <label class="f"><span>${esc(t('lng'))}</span><input type="number" step="any" name="lng" value="${cur.lng ?? ''}"></label>
        <label class="f full"><span>${esc(t('note'))}</span><textarea name="note">${esc(cur.note || '')}</textarea></label>
      </div>
      <div class="banner err" id="lerr2" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#lf2');
    const fillEc = () => {
      const p = prj.find((x) => String(x.id) === String(f.project_id.value));
      f.end_customer_id.innerHTML = `<option value="">—</option>` + (p?.end_customers || []).map((ec) => `<option value="${ec.id}" ${ec.id === cur.end_customer_id ? 'selected' : ''}>${esc(ec.name)}</option>`).join('');
    };
    f.project_id.onchange = fillEc;
    fillEc();
    f.onsubmit = async (e) => {
      e.preventDefault();
      const num = (v) => (v === '' ? null : Number(v));
      const body = {
        end_customer_id: num(f.end_customer_id.value), code: f.code.value.trim(), name: f.name.value.trim(), external_id: f.external_id.value.trim() || null,
        street: f.street.value.trim() || null, city: f.city.value.trim() || null, zip: f.zip.value.trim() || null,
        country: f.country.value, is_active: f.is_active.value === '1', lat: num(f.lat.value), lng: num(f.lng.value),
        note: f.note.value.trim() || null,
      };
      try {
        if (l) await api('PATCH', `/locations/${l.id}`, body);
        else await api('POST', '/locations', { ...body, project_id: Number(f.project_id.value) });
        close(true);
      } catch (ex) {
        const err = d.querySelector('#lerr2');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
  if (ok) { toast(t('saved')); route(); }
}

// ================================================================ uživatelé (admin)

async function renderUsers() {
  const el = shell('users');
  let users, prj;
  try {
    [users, prj] = await Promise.all([api('GET', '/admin/users').then((r) => r.data), projects()]);
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('users'))}</h1><button class="btn primary" id="btn-add">➕ ${esc(t('newUser'))}</button></div>
    <div class="table-wrap"><table>
      <thead><tr><th>${esc(t('name'))}</th><th>${esc(t('email'))}</th><th>${esc(t('roles'))}</th><th>${esc(t('projects'))}</th><th></th></tr></thead>
      <tbody>${users.map((u, i) => `<tr data-i="${i}">
        <td><strong>${esc(u.name)}</strong>${u.phone ? `<div class="muted small">${esc(u.phone)}</div>` : ''}</td>
        <td>${esc(u.email)}</td>
        <td>${u.roles.map((r) => chip('role', t('role.' + r))).join('')}</td>
        <td class="small">${u.project_ids.map((id) => esc(prj.find((p) => p.id === id)?.name || id)).join(', ')}${u.location_ids.length ? ` <span class="muted">· ${u.location_ids.length} ${esc(t('locations').toLowerCase())}</span>` : ''}</td>
        <td>${u.is_active ? '' : chip('off', t('inactive'))} <span class="muted small">${esc(u.locale.toUpperCase())}</span></td>
      </tr>`).join('')}</tbody>
    </table></div>`;

  el.querySelector('#btn-add').onclick = () => editUser(null, prj);
  el.querySelectorAll('tr[data-i]').forEach((tr) => { tr.onclick = () => editUser(users[Number(tr.dataset.i)], prj); });
}

async function editUser(u, prj) {
  const cur = u || { roles: ['technik'], project_ids: [], location_ids: [], locale: 'cs', is_active: true };
  let allLocs = [];
  try { allLocs = (await api('GET', '/locations?all=1&per_page=500')).data; } catch { /* nic */ }

  const ok = await modal(`
    <h3>${esc(u ? u.name : t('newUser'))}</h3>
    <form id="uf">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
        <label class="f"><span class="req">${esc(t('email'))}</span><input type="email" name="email" required value="${esc(cur.email || '')}"></label>
        <label class="f"><span>${esc(t('phone'))}</span><input type="tel" name="phone" value="${esc(cur.phone || '')}"></label>
        <label class="f full"><span>🏠 ${esc(t('homeAddress'))}</span><input type="text" name="home_address" value="${esc(cur.home_address || '')}" placeholder="Ulice 1, 742 13 Studénka">
          ${cur.home_address && !cur.home_located ? `<small style="color:var(--bad)">⚠ ${esc(t('homeNotFound'))}</small>` : ''}</label>
        <label class="f"><span>${esc(t('language'))}</span><select name="locale"><option value="cs" ${cur.locale === 'cs' ? 'selected' : ''}>Čeština</option><option value="pl" ${cur.locale === 'pl' ? 'selected' : ''}>Polski</option></select></label>
        <label class="f"><span class="${u ? '' : 'req'}">${esc(u ? t('newPassword') : t('password'))}</span><input type="text" name="password" ${u ? '' : 'required'} minlength="8" autocomplete="new-password"></label>
        <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1" ${cur.is_active ? 'selected' : ''}>${esc(t('yes'))}</option><option value="0" ${!cur.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
        <div class="f full"><span class="req" style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('roles'))}</span>
          <div class="checks">${['technik', 'dispecer', 'admin', 'spolecnik', 'ekonom', 'zakaznik'].map((r) => `<label><input type="checkbox" name="roles" value="${r}" ${cur.roles.includes(r) ? 'checked' : ''}> ${esc(t('role.' + r))}</label>`).join('')}</div></div>
        <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('projects'))}</span>
          <div class="checks">${prj.map((p) => `<label><input type="checkbox" name="projects" value="${p.id}" ${cur.project_ids.includes(p.id) ? 'checked' : ''}> ${esc(p.name)}</label>`).join('')}</div></div>
        <div class="f full" id="cust-box"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('customerLocations'))}</span>
          <input type="search" id="loc-filter" placeholder="${esc(t('filter'))}" style="margin-bottom:6px">
          <div class="check-list">${allLocs.map((l) => `<label data-txt="${esc((l.code + ' ' + l.name + ' ' + (l.city || '')).toLowerCase())}"><input type="checkbox" name="locs" value="${l.id}" ${cur.location_ids.includes(l.id) ? 'checked' : ''}> <span><strong>${esc(l.code)}</strong> · ${esc(l.name)} <span class="muted small">${esc(l.city || '')}</span></span></label>`).join('')}</div></div>
      </div>
      <div class="banner err" id="uerr" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#uf');
    const custBox = d.querySelector('#cust-box');
    const syncCust = () => { custBox.hidden = !d.querySelector('input[name=roles][value=zakaznik]').checked; };
    d.querySelectorAll('input[name=roles]').forEach((c) => { c.onchange = syncCust; });
    syncCust();
    d.querySelector('#loc-filter').oninput = (e) => {
      const v = e.target.value.toLowerCase();
      d.querySelectorAll('.check-list label').forEach((lb) => { lb.hidden = v && !lb.dataset.txt.includes(v); });
    };
    f.onsubmit = async (e) => {
      e.preventDefault();
      const checked = (name) => [...d.querySelectorAll(`input[name=${name}]:checked`)].map((c) => c.value);
      const body = {
        name: f.name.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim() || null, home_address: f.home_address.value.trim() || null,
        locale: f.locale.value, is_active: f.is_active.value === '1',
        roles: checked('roles'), project_ids: checked('projects').map(Number), location_ids: checked('locs').map(Number),
      };
      if (f.password.value) body.password = f.password.value;
      try {
        if (u) await api('PATCH', `/admin/users/${u.id}`, body);
        else await api('POST', '/admin/users', body);
        close(true);
      } catch (ex) {
        const err = d.querySelector('#uerr');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
  if (ok) { techCache.clear(); toast(t('saved')); renderUsers(); }
}


// ================================================================ sklady

const canStock = () => has('admin', 'dispecer');
let partsCache = null;
async function allParts(force = false) {
  if (!partsCache || force) partsCache = (await api('GET', '/parts?all=1&per_page=2000')).data;
  return partsCache;
}
const qtyFmt = (n) => (n == null ? '—' : Number(n).toLocaleString(locale(), { maximumFractionDigits: 2 }));

async function renderStock(q) {
  const el = shell('stock');
  const view = q.get('view') || 'levels';
  const wh = q.get('wh') || '';
  const setQ = (c) => {
    const n = new URLSearchParams(q);
    Object.entries(c).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)));
    location.hash = '#/stock?' + n.toString();
  };

  let whs;
  try { whs = (await api('GET', '/warehouses')).data; } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }
  const whName = (id) => { const w = whs.find((x) => x.id === id); return w ? `${w.code} · ${w.name}` : '—'; };
  const whOptions = (sel, empty) => `${empty ? `<option value="">${esc(empty)}</option>` : ''}${['central', 'project', 'technician'].map((tp) => {
    const list = whs.filter((w) => w.type === tp);
    return list.length ? `<optgroup label="${esc(t('whType.' + tp))}">${list.map((w) => `<option value="${w.id}" ${String(w.id) === String(sel) ? 'selected' : ''}>${esc(w.code)} · ${esc(w.name)}</option>`).join('')}</optgroup>` : '';
  }).join('')}`;

  const tabs = [['levels', t('stockLevels')], ['moves', t('stockMoves')], ['parts', t('stockParts')], ['reorder', t('stockReorder')]];
  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('navStock'))}</h1>
      ${canStock() ? `<button class="btn" data-mv="receipt">⬇ ${esc(t('receipt'))}</button><button class="btn" data-mv="transfer">⇄ ${esc(t('transfer'))}</button><button class="btn" data-mv="adjustment">± ${esc(t('adjustment'))}</button>` : ''}
    </div>
    <div class="tabs">${tabs.map(([k, l]) => `<button class="tab ${k === view ? 'on' : ''}" data-view="${k}">${esc(l)}</button>`).join('')}</div>
    <div id="stock-body"><div class="spinner"></div></div>`;
  el.querySelectorAll('[data-view]').forEach((b) => { b.onclick = () => setQ({ view: b.dataset.view }); });
  el.querySelectorAll('[data-mv]').forEach((b) => { b.onclick = () => movementForm(b.dataset.mv, whs, whOptions, wh); });
  const body = el.querySelector('#stock-body');

  try {
    if (view === 'levels') {
      const params = new URLSearchParams({ nonzero: q.get('all') ? '0' : '1' });
      if (wh) params.set('warehouse_id', wh);
      if (q.get('q')) params.set('q', q.get('q'));
      const res = await api('GET', '/stock?' + params.toString());
      body.innerHTML = `
        <div class="filters">
          <select id="f-wh">${whOptions(wh, t('allWarehouses'))}</select>
          <input type="search" id="f-q" placeholder="${esc(t('filter'))}" value="${esc(q.get('q') || '')}">
        </div>
        ${res.data.length ? `<div class="table-wrap"><table>
          <thead><tr><th>${esc(t('partNo'))}</th><th>${esc(t('name'))}</th>${wh ? '' : `<th>${esc(t('warehouse'))}</th>`}<th style="text-align:right">${esc(t('qtyCol'))}</th><th style="text-align:right">${esc(t('minCol'))}</th></tr></thead>
          <tbody>${res.data.map((r, i) => `<tr data-i="${i}" ${r.below_min || r.qty < 0 ? 'style="background:#fff4f2"' : ''}>
            <td class="num">${esc(r.part.part_number)}</td><td>${esc(r.part.name)}${r.part.serialized ? ' <span class="chip draft">S/N</span>' : ''}</td>
            ${wh ? '' : `<td>${esc(whName(r.warehouse_id))}</td>`}
            <td style="text-align:right;font-weight:700;${r.qty < 0 ? 'color:var(--bad)' : ''}">${qtyFmt(r.qty)} ${esc(r.part.unit || '')}</td>
            <td style="text-align:right">${r.min_qty != null ? qtyFmt(r.min_qty) : '<span class="muted">—</span>'}${r.below_min ? ' ⚠' : ''}</td></tr>`).join('')}</tbody>
        </table></div>` : '<div class="card empty">—</div>'}`;
      body.querySelector('#f-wh').onchange = (e) => setQ({ wh: e.target.value });
      body.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
      if (canStock()) {
        body.querySelectorAll('tr[data-i]').forEach((tr) => {
          tr.onclick = async () => {
            const r = res.data[Number(tr.dataset.i)];
            const v = await promptModal(`${t('setMin')} – ${r.part.name} @ ${whName(r.warehouse_id)}`);
            if (v === undefined || v === null || v === '') return;
            try { await api('PUT', '/stock/minimums', { warehouse_id: r.warehouse_id, part_id: r.part.id, min_qty: Number(String(v).replace(',', '.')) || 0 }); route(); } catch (e) { toast(errText(e), true); }
          };
        });
      }
    }

    if (view === 'moves') {
      const params = new URLSearchParams({ per_page: '100' });
      if (wh) params.set('warehouse_id', wh);
      const res = await api('GET', '/stock/movements?' + params.toString());
      body.innerHTML = `
        <div class="filters"><select id="f-wh">${whOptions(wh, t('allWarehouses'))}</select></div>
        ${res.data.length ? `<div class="table-wrap"><table>
          <thead><tr><th>${esc(t('when'))}</th><th>${esc(t('mvType'))}</th><th>${esc(t('name'))}</th><th style="text-align:right">${esc(t('quantity'))}</th><th>${esc(t('from'))}</th><th>${esc(t('to'))}</th><th>${esc(t('ticketCol'))}</th><th>${esc(t('who'))}</th></tr></thead>
          <tbody>${res.data.map((m) => `<tr>
            <td class="small">${esc(fmt(m.created_at))}</td><td>${esc(t(m.type))}</td>
            <td>${esc(m.part?.name || '')} <span class="muted small">${esc(m.part?.part_number || '')}</span>${m.serial_number ? `<div class="muted small">S/N ${esc(m.serial_number)}</div>` : ''}${m.note ? `<div class="muted small">${esc(m.note)}</div>` : ''}</td>
            <td style="text-align:right;font-weight:600">${qtyFmt(m.quantity)}</td>
            <td class="small">${m.from ? esc(m.from.code) : '—'}</td><td class="small">${m.to ? esc(m.to.code) : '—'}</td>
            <td>${m.ticket ? `<a href="#/t/${m.ticket.id}">${esc(m.ticket.number)}</a>` : ''}</td><td class="small">${esc(m.user?.name || '')}</td></tr>`).join('')}</tbody>
        </table></div>` : '<div class="card empty">—</div>'}`;
      body.querySelector('#f-wh').onchange = (e) => setQ({ wh: e.target.value });
    }

    if (view === 'parts') {
      const parts = await allParts(true);
      const prj = await projects();
      body.innerHTML = `
        ${canStock() ? `<div class="btn-row" style="margin-bottom:12px"><button class="btn primary" id="btn-part">➕ ${esc(t('newPart'))}</button></div>` : ''}
        ${parts.length ? `<div class="table-wrap"><table>
          <thead><tr><th>${esc(t('partNo'))}</th><th>${esc(t('name'))}</th><th>${esc(t('unit'))}</th><th>${esc(t('serialized'))}</th><th>${esc(t('project'))}</th><th></th></tr></thead>
          <tbody>${parts.map((p, i) => `<tr data-i="${i}"><td class="num">${esc(p.part_number)}</td><td>${esc(p.name)}</td><td>${esc(p.unit)}</td><td>${p.serialized ? '✓' : ''}</td>
            <td>${esc(prj.find((x) => x.id === p.project_id)?.name || '')}</td><td>${p.is_active ? '' : chip('off', t('inactive'))}</td></tr>`).join('')}</tbody>
        </table></div>` : '<div class="card empty">—</div>'}`;
      if (canStock()) {
        body.querySelector('#btn-part').onclick = () => partForm(null, prj);
        body.querySelectorAll('tr[data-i]').forEach((tr) => { tr.onclick = () => partForm(parts[Number(tr.dataset.i)], prj); });
      }
    }

    if (view === 'reorder') {
      const res = await api('GET', '/stock/reorder');
      body.innerHTML = res.data.length ? `<div class="table-wrap"><table>
        <thead><tr><th>${esc(t('warehouse'))}</th><th>${esc(t('name'))}</th><th style="text-align:right">${esc(t('qtyCol'))}</th><th style="text-align:right">${esc(t('minCol'))}</th><th style="text-align:right">${esc(t('suggested'))}</th></tr></thead>
        <tbody>${res.data.map((r) => `<tr><td>${esc(r.warehouse.code)} · ${esc(r.warehouse.name)}</td><td>${esc(r.part.name)} <span class="muted small">${esc(r.part.part_number)}</span></td>
          <td style="text-align:right">${qtyFmt(r.qty)}</td><td style="text-align:right">${qtyFmt(r.min_qty)}</td><td style="text-align:right;font-weight:700">${qtyFmt(r.suggested)}</td></tr>`).join('')}</tbody>
      </table></div>` : `<div class="card empty">${esc(t('noReorder'))}</div>`;
    }
  } catch (e) {
    body.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`;
  }
}

async function movementForm(type, whs, whOptions, defaultWh) {
  let parts;
  try { parts = (await allParts()).filter((p) => p.is_active); } catch (e) { toast(errText(e), true); return; }
  const ok = await modal(`
    <h3>${esc(t(type))}</h3>
    <form id="mf">
      <div class="form-grid">
        <label class="f full"><span class="req">${esc(t('name'))}</span>
          <input type="search" id="pf" placeholder="${esc(t('filter'))}" style="margin-bottom:6px">
          <select name="part_id" required size="6">${parts.map((p) => `<option value="${p.id}" data-txt="${esc((p.part_number + ' ' + p.name).toLowerCase())}">${esc(p.part_number)} · ${esc(p.name)}</option>`).join('')}</select></label>
        ${type !== 'receipt' ? `<label class="f"><span class="${type === 'transfer' ? 'req' : ''}">${esc(t('from'))}</span><select name="from_warehouse_id">${whOptions(type === 'transfer' ? defaultWh : '', '—')}</select></label>` : ''}
        <label class="f"><span class="${type === 'adjustment' ? '' : 'req'}">${esc(t('to'))}</span><select name="to_warehouse_id">${whOptions(type === 'receipt' ? defaultWh : '', '—')}</select></label>
        <label class="f"><span class="req">${esc(t('quantity'))}</span><input type="number" name="quantity" min="0.01" step="0.01" value="1" required></label>
        <div class="f muted small" id="avail" style="align-self:end"></div>
        <label class="f"><span>${esc(t('serial'))}</span><input type="text" name="serial_number"></label>
        <label class="f"><span>${esc(t('note'))}</span><input type="text" name="note"></label>
      </div>
      ${type === 'adjustment' ? `<div class="banner warn small">${esc(t('adjustHint'))}</div>` : ''}
      <div class="banner err" id="merr" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#mf');
    const formUuid = uuid();
    d.querySelector('#pf').oninput = (e) => {
      const v = e.target.value.toLowerCase();
      [...f.part_id.options].forEach((o) => { o.hidden = v && !o.dataset.txt.includes(v); });
    };
    const showAvail = async () => {
      const box = d.querySelector('#avail');
      const from = f.from_warehouse_id?.value;
      if (!from || !f.part_id.value) { box.textContent = ''; return; }
      try {
        const r = await api('GET', `/stock?warehouse_id=${from}&part_id=${f.part_id.value}`);
        box.textContent = `${t('available')}: ${qtyFmt(r.data[0]?.qty ?? 0)}`;
      } catch { box.textContent = ''; }
    };
    f.part_id.onchange = showAvail;
    if (f.from_warehouse_id) f.from_warehouse_id.onchange = showAvail;
    f.onsubmit = async (e) => {
      e.preventDefault();
      const num = (v) => (v ? Number(v) : null);
      try {
        await api('POST', '/stock/movements', {
          uuid: formUuid, type, part_id: Number(f.part_id.value), quantity: Number(f.quantity.value),
          from_warehouse_id: num(f.from_warehouse_id?.value), to_warehouse_id: num(f.to_warehouse_id.value),
          serial_number: f.serial_number.value.trim() || null, note: f.note.value.trim() || null,
        });
        close(true);
      } catch (ex) {
        const err = d.querySelector('#merr');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
  if (ok) { toast(t('moved')); route(); }
}

async function partForm(p, prj) {
  const cur = p || { unit: 'ks', serialized: false, is_active: true };
  const ok = await modal(`
    <h3>${esc(p ? p.part_number : t('newPart'))}</h3>
    <form id="pf2"><div class="form-grid">
      <label class="f"><span class="req">${esc(t('partNo'))}</span><input type="text" name="part_number" required value="${esc(cur.part_number || '')}"></label>
      <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
      <label class="f"><span>${esc(t('unit'))}</span><input type="text" name="unit" value="${esc(cur.unit || 'ks')}"></label>
      <label class="f"><span>${esc(t('project'))}</span><select name="project_id"><option value="">—</option>${prj.map((x) => `<option value="${x.id}" ${x.id === cur.project_id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select></label>
      <label class="f"><span>${esc(t('serialized'))}</span><select name="serialized"><option value="0">${esc(t('no'))}</option><option value="1" ${cur.serialized ? 'selected' : ''}>${esc(t('yes'))}</option></select></label>
      <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1">${esc(t('yes'))}</option><option value="0" ${cur.is_active ? '' : 'selected'}>${esc(t('no'))}</option></select></label>
      <label class="f full"><span>${esc(t('note'))}</span><textarea name="note">${esc(cur.note || '')}</textarea></label>
    </div>
    <div class="banner err" id="perr" hidden></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`, (d, close) => {
    const f = d.querySelector('#pf2');
    f.onsubmit = async (e) => {
      e.preventDefault();
      const body = {
        part_number: f.part_number.value.trim(), name: f.name.value.trim(), unit: f.unit.value.trim() || 'ks',
        project_id: f.project_id.value ? Number(f.project_id.value) : null, serialized: f.serialized.value === '1',
        is_active: f.is_active.value === '1', note: f.note.value.trim() || null,
      };
      try {
        if (p) await api('PATCH', `/parts/${p.id}`, body); else await api('POST', '/parts', body);
        close(true);
      } catch (ex) { const err = d.querySelector('#perr'); err.textContent = errText(ex); err.hidden = false; }
    };
  });
  if (ok) { partsCache = null; toast(t('saved')); route(); }
}


// ================================================================ e-maily a nastavení

const EMAIL_TABS = { todo: 'review,error,ignored,new', all: '' };

async function renderEmails(q) {
  const el = shell('emails');
  const tab = q.get('tab') || 'todo';
  let res;
  try {
    res = await api('GET', '/emails?per_page=100' + (EMAIL_TABS[tab] ? '&status=' + EMAIL_TABS[tab] : ''));
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  const stCls = { new: 'assigned', ticket: 'completed', appended: 'closed', review: 'waiting', ignored: 'closed', error: 'cancelled' };
  el.innerHTML = `
    <div class="page-head"><h1>📧 ${esc(t('navEmails'))}</h1></div>
    <div class="tabs">${Object.keys(EMAIL_TABS).map((k) => `<button class="tab ${k === tab ? 'on' : ''}" data-tab="${k}">${esc(t('etab.' + k))}</button>`).join('')}</div>
    ${res.data.length ? `<div class="table-wrap"><table>
      <thead><tr><th>${esc(t('received'))}</th><th>${esc(t('from_'))}</th><th>${esc(t('subject'))}</th><th>${esc(t('col.status'))}</th><th>${esc(t('ticketCol'))}</th></tr></thead>
      <tbody>${res.data.map((m, i) => `<tr data-i="${i}">
        <td class="small">${esc(fmt(m.received_at))}<div class="muted">${esc(m.mailbox?.name || '')}</div></td>
        <td>${esc(m.from_name || '')}<div class="muted small">${esc(m.from_email || '')}</div></td>
        <td><strong>${esc(m.subject || '—')}</strong>${m.attachments_count ? ` <span class="muted small">📎 ${m.attachments_count}</span>` : ''}
          ${m.ai?.summary ? `<div class="muted small">${esc(m.ai.summary)}</div>` : ''}${m.error ? `<div class="small" style="color:var(--bad)">${esc(m.error)}</div>` : ''}</td>
        <td>${chip(stCls[m.status] || '', t('est.' + m.status))}</td>
        <td>${m.ticket ? `<a href="#/t/${m.ticket.id}">${esc(m.ticket.number)}</a>` : '—'}</td></tr>`).join('')}</tbody>
    </table></div>` : `<div class="card empty">${esc(t('noEmails'))}</div>`}`;

  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => { location.hash = '#/emails?tab=' + b.dataset.tab; }; });
  el.querySelectorAll('tr[data-i]').forEach((tr) => { tr.onclick = () => openEmail(res.data[Number(tr.dataset.i)].id); });
  if (q.get('open')) openEmail(q.get('open'));
}

async function openEmail(id) {
  let m;
  try { m = (await api('GET', '/emails/' + id)).data; } catch (e) { toast(errText(e), true); return; }
  const res = await modal(`
    <h3>${esc(m.subject || '—')}</h3>
    <dl class="kv">
      <dt>${esc(t('from_'))}</dt><dd>${esc(m.from_name || '')} &lt;${esc(m.from_email || '')}&gt;</dd>
      <dt>${esc(t('received'))}</dt><dd>${esc(fmt(m.received_at))} · ${esc(m.mailbox?.name || '')}</dd>
      <dt>${esc(t('col.status'))}</dt><dd>${esc(t('est.' + m.status))}${m.ticket ? ` · <a href="#/t/${m.ticket.id}" data-close>${esc(m.ticket.number)}</a>` : ''}</dd>
      ${m.attachments?.length ? `<dt>${esc(t('attachments'))}</dt><dd>${m.attachments.map((a) => esc(a.name)).join(', ')}</dd>` : ''}
      ${m.ai ? `<dt>${esc(t('aiSays'))}</dt><dd class="small">${esc(m.ai.engine === 'claude' ? 'Claude' : 'heuristika')}${m.ai.reason ? ' – ' + esc(m.ai.reason) : ''}</dd>` : ''}
    </dl>
    ${m.error ? `<div class="banner err small" style="margin-top:10px">${esc(m.error)}</div>` : ''}
    <div class="card desc small" style="margin-top:12px;max-height:40vh;overflow:auto">${esc(m.body_text || '')}</div>
    <div class="modal-foot">
      ${!m.ticket ? `<button class="btn" data-act="ignore">${esc(t('ignore'))}</button><button class="btn primary" data-act="reprocess">${esc(t('makeTicket'))}</button>` : ''}
      <button class="btn" data-close>${esc(t('close'))}</button>
    </div>`, (d, close) => {
    d.querySelectorAll('[data-act]').forEach((b) => {
      b.onclick = async () => {
        try {
          const r = await api('POST', `/emails/${id}/action`, { action: b.dataset.act });
          close(r.data);
        } catch (e) { toast(errText(e), true); }
      };
    });
  });
  if (res?.ticket) { location.hash = '#/t/' + res.ticket.id; return; }
  if (res) route();
}

async function renderSettings() {
  const el = shell('settings');
  let ai, mbs, prj, tariffs, allProjects, checklists;
  try {
    [ai, mbs, prj, tariffs, allProjects, checklists] = await Promise.all([api('GET', '/admin/ai'), api('GET', '/admin/mailboxes').then((r) => r.data), projects(),
      api('GET', '/tariffs').then((r) => r.data), api('GET', '/admin/projects').then((r) => r.data), api('GET', '/checklists').then((r) => r.data)]);
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  el.innerHTML = `
    <div class="page-head"><h1>⚙️ ${esc(t('navSettings'))}</h1></div>
    <div class="card" style="max-width:860px">
      <h2>🗂 ${esc(t('projects'))}<span class="sp"></span><button class="btn sm primary" id="pj-add">➕ ${esc(t('newProject'))}</button></h2>
      <table class="tbl"><thead><tr><th>${esc(t('name'))}</th><th>${esc(t('serviceTypes'))}</th><th>SLA</th><th>${esc(t('protocolTpl'))}</th></tr></thead><tbody>
        ${allProjects.map((x, i) => `<tr data-pj="${i}" style="cursor:pointer"><td><strong>${esc(x.name)}</strong> <span class="muted small">${esc(x.code)} · ${esc((x.countries || []).join(', '))}</span>${x.is_active ? '' : ' ' + chip('off', t('inactiveP'))}
          ${x.end_customers.length ? `<div class="muted small">${x.end_customers.map((e) => esc(e.name)).join(' · ')}</div>` : ''}</td>
          <td>${(x.service_types || []).map((s) => esc(t('stype.' + s))).join(', ')}</td>
          <td class="small">${Object.keys(x.sla_hours || {}).length ? Object.entries(x.sla_hours).map(([k, v]) => `${esc(t('prio.' + k))} ${v} h`).join('<br>') : '—'}</td>
          <td>${x.protocol_template === 'm4b' ? 'M4B' : 'Standard'}</td></tr>`).join('')}
      </tbody></table>
    </div>

    <div class="card" style="max-width:860px">
      <h2>☑ ${esc(t('checklists'))}<span class="sp"></span><button class="btn sm primary" id="cl-add">➕ ${esc(t('newChecklist'))}</button></h2>
      ${checklists.length ? `<table class="tbl"><thead><tr><th>${esc(t('name'))}</th><th>${esc(t('project'))}</th><th>${esc(t('serviceType'))}</th><th class="num">#</th></tr></thead><tbody>
        ${checklists.map((c, i) => `<tr data-cl="${i}" style="cursor:pointer"><td><strong>${esc(c.name)}</strong>${c.is_active ? '' : ' ' + chip('off', t('inactiveP'))}</td><td>${esc(c.project?.name || '')}</td>
          <td>${esc(c.service_type ? t('stype.' + c.service_type) : t('allTypes'))}</td><td class="num">${c.items.length}</td></tr>`).join('')}
      </tbody></table>` : '<p class="muted">—</p>'}
    </div>

    <div class="card" style="max-width:860px">
      <h2>💰 ${esc(t('tariffs'))}<span class="sp"></span><button class="btn sm primary" id="tf-add">➕ ${esc(t('newTariff'))}</button></h2>
      ${tariffs.length ? `<table class="tbl"><thead><tr><th>${esc(t('project'))}</th><th>${esc(t('serviceType'))}</th><th class="num">${esc(t('tariffBase'))}</th><th class="num">${esc(t('tariffKm'))}</th><th>${esc(t('tariffNote'))}</th></tr></thead><tbody>
        ${tariffs.map((x, i) => `<tr data-tf="${i}" style="cursor:pointer"><td><strong>${esc(x.project?.name || '')}</strong></td><td>${esc(x.service_type ? t('stype.' + x.service_type) : t('allTypes'))}</td>
          <td class="num">${esc(money(x.base_price, x.currency))}</td><td class="num">${esc(money(x.km_rate, x.currency))}</td><td class="muted small">${esc(x.note || '')}</td></tr>`).join('')}
      </tbody></table>` : `<p class="muted">—</p>`}
    </div>

    <div class="card" style="max-width:860px">
      <h2>🤖 ${esc(t('aiTitle'))}</h2>
      <p class="small">${esc(t('apiKey'))}: <strong>${ai.has_key ? esc(t('apiKeySet')) + ' (' + esc(ai.key_hint) + ')' : esc(t('apiKeyNone'))}</strong></p>
      <div class="form-grid">
        <label class="f"><span>${esc(t('apiKey'))}</span><input type="password" id="ai-key" placeholder="sk-ant-…" autocomplete="off"></label>
        <label class="f"><span>${esc(t('model'))}</span><input type="text" id="ai-model" value="${esc(ai.model)}"></label>
      </div>
      <div class="btn-row">
        <button class="btn primary" id="ai-save">${esc(t('save'))}</button>
        ${ai.has_key ? `<button class="btn" id="ai-test">${esc(t('testAi'))}</button><button class="btn danger" id="ai-remove">${esc(t('removeKey'))}</button>` : ''}
      </div>
      <div class="small" id="ai-out" style="margin-top:8px"></div>
    </div>

    <div class="card" style="max-width:860px">
      <h2>📧 ${esc(t('mailboxes'))}<span class="sp"></span><button class="btn sm primary" id="mb-add">➕ ${esc(t('newMailbox'))}</button></h2>
      ${mbs.length ? mbs.map((m, i) => `
        <div class="visit" data-mb="${i}" style="cursor:pointer">
          <div class="visit-head"><strong>${esc(m.name)}</strong>${m.is_active ? '' : chip('off', t('inactive'))}<span class="muted small">${esc(m.project?.name || '')}</span></div>
          <div class="small muted">${esc(m.imap_username)} @ ${esc(m.imap_host)} · ${esc(t('lastCheck'))}: ${esc(fmt(m.last_checked_at))} · ${m.emails_count} ✉</div>
          ${m.last_error ? `<div class="small" style="color:var(--bad);margin-top:4px">${esc(m.last_error)}</div>` : ''}
        </div>`).join('') : '<p class="muted">—</p>'}
    </div>`;

  const out = el.querySelector('#ai-out');
  el.querySelector('#ai-save').onclick = async () => {
    try {
      await api('PUT', '/admin/ai', { api_key: el.querySelector('#ai-key').value.trim() || null, model: el.querySelector('#ai-model').value.trim() || null });
      toast(t('saved')); renderSettings();
    } catch (e) { toast(errText(e), true); }
  };
  el.querySelector('#ai-test')?.addEventListener('click', async () => {
    out.textContent = '…';
    try { const r = await api('POST', '/admin/ai/test'); out.innerHTML = r.ok ? `✅ ${esc(r.reply)}` : `<span style="color:var(--bad)">❌ ${esc(r.error)}</span>`; } catch (e) { out.textContent = errText(e); }
  });
  el.querySelector('#ai-remove')?.addEventListener('click', async () => {
    if (!(await confirmModal(t('removeKey') + '?'))) return;
    await api('PUT', '/admin/ai', { remove_key: true }); renderSettings();
  });
  el.querySelector('#mb-add').onclick = () => mailboxForm(null, prj);
  el.querySelector('#tf-add').onclick = () => tariffForm(null, prj);
  el.querySelector('#pj-add').onclick = () => projectForm(null);
  el.querySelectorAll('[data-pj]').forEach((r) => { r.onclick = () => projectForm(allProjects[Number(r.dataset.pj)]); });
  el.querySelector('#cl-add').onclick = () => checklistForm(null, prj);
  el.querySelectorAll('[data-cl]').forEach((r) => { r.onclick = () => checklistForm(checklists[Number(r.dataset.cl)], prj); });
  el.querySelectorAll('[data-tf]').forEach((r) => { r.onclick = () => tariffForm(tariffs[Number(r.dataset.tf)], prj); });
  el.querySelectorAll('[data-mb]').forEach((d) => { d.onclick = () => mailboxForm(mbs[Number(d.dataset.mb)], prj); });
}

async function mailboxForm(m, prj) {
  const cur = m || { imap_port: 993, imap_encryption: 'ssl', imap_folder: 'INBOX', smtp_port: 465, smtp_encryption: 'ssl', dedicated: true, auto_reply: true, is_active: true };
  const enc = (name, val) => `<select name="${name}">${['ssl', 'tls', 'none'].map((e) => `<option ${e === val ? 'selected' : ''}>${e}</option>`).join('')}</select>`;
  const yn = (name, val) => `<select name="${name}"><option value="1" ${val ? 'selected' : ''}>${esc(t('yes'))}</option><option value="0" ${val ? '' : 'selected'}>${esc(t('no'))}</option></select>`;
  await modal(`
    <h3>${esc(m ? m.name : t('newMailbox'))}</h3>
    <form id="mbf">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
        <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id">${prj.map((p) => `<option value="${p.id}" ${p.id === cur.project_id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f full"><span>${esc(t('parser'))}</span><select name="parser">${['ai', 'zasilkovna_ritm'].map((x) => `<option value="${x}" ${x === (cur.parser || 'ai') ? 'selected' : ''}>${esc(t('parsers.' + x))}</option>`).join('')}</select><span class="muted small">${esc(t('parserHint'))}</span></label>
        <div class="f full"><strong>${esc(t('imap'))}</strong></div>
        <label class="f"><span class="req">${esc(t('host'))}</span><input type="text" name="imap_host" required value="${esc(cur.imap_host || '')}" placeholder="imap.webglobe.cz"></label>
        <div class="form-grid" style="gap:0 8px"><label class="f"><span>${esc(t('port'))}</span><input type="number" name="imap_port" value="${cur.imap_port || 993}"></label><label class="f"><span>${esc(t('encryption'))}</span>${enc('imap_encryption', cur.imap_encryption)}</label></div>
        <label class="f"><span class="req">${esc(t('username'))}</span><input type="text" name="imap_username" required value="${esc(cur.imap_username || '')}" autocomplete="off"></label>
        <label class="f"><span class="${m ? '' : 'req'}">${esc(t('password'))}${m ? ` <span class="muted">(${esc(t('pwKeep'))})</span>` : ''}</span><input type="password" name="imap_password" ${m ? '' : 'required'} autocomplete="new-password"></label>
        <label class="f"><span>${esc(t('folder'))}</span><input type="text" name="imap_folder" value="${esc(cur.imap_folder || 'INBOX')}"></label>
        <label class="f"><span>${esc(t('sentFolder'))}</span><input type="text" name="sent_folder" value="${esc(cur.sent_folder || '')}" placeholder="Sent / INBOX.Sent"><span class="muted small">${esc(t('sentHint'))}</span></label>
        <label class="f"><span>${esc(t('lookback'))}</span><input type="number" name="lookback_days" min="0" max="120" placeholder="${m ? '' : '1'}"><span class="muted small">${esc(t('lookbackHint'))}${m?.import_since ? ' (' + esc(fmt(m.import_since, false)) + ')' : ''}</span></label>
        <label class="f"><span>${esc(t('dedicated'))}</span>${yn('dedicated', cur.dedicated)}<span class="muted small">${esc(t('dedicatedHint'))}</span></label>
        <div class="f full"><strong>${esc(t('smtp'))}</strong></div>
        <label class="f"><span>${esc(t('host'))}</span><input type="text" name="smtp_host" value="${esc(cur.smtp_host || '')}" placeholder="smtp.webglobe.cz"></label>
        <div class="form-grid" style="gap:0 8px"><label class="f"><span>${esc(t('port'))}</span><input type="number" name="smtp_port" value="${cur.smtp_port || 465}"></label><label class="f"><span>${esc(t('encryption'))}</span>${enc('smtp_encryption', cur.smtp_encryption)}</label></div>
        <label class="f"><span>${esc(t('username'))}</span><input type="text" name="smtp_username" value="${esc(cur.smtp_username || '')}" autocomplete="off"></label>
        <label class="f"><span>${esc(t('password'))}${m ? ` <span class="muted">(${esc(t('pwKeep'))})</span>` : ''}</span><input type="password" name="smtp_password" autocomplete="new-password"></label>
        <label class="f"><span>${esc(t('fromAddress'))}</span><input type="email" name="from_address" value="${esc(cur.from_address || '')}"></label>
        <label class="f"><span>${esc(t('fromName'))}</span><input type="text" name="from_name" value="${esc(cur.from_name || 'PAP Service')}"></label>
        <label class="f"><span>${esc(t('autoReply'))}</span>${yn('auto_reply', cur.auto_reply)}</label>
        <label class="f"><span>${esc(t('active'))}</span>${yn('is_active', cur.is_active)}</label>
      </div>
      <div class="small" id="mb-out"></div>
      <div class="banner err" id="mberr" hidden></div>
      <div class="modal-foot">
        ${m ? `<button type="button" class="btn" id="mb-test">${esc(t('testConn'))}</button><button type="button" class="btn" id="mb-fetch">${esc(t('fetchNow'))}</button><button type="button" class="btn danger" id="mb-reproc">↻ ${esc(t('reprocess'))}</button>` : ''}
        <button type="button" class="btn" data-close>${esc(t('close'))}</button><button class="btn primary">${esc(t('save'))}</button>
      </div>
    </form>`, (d, close) => {
    const f = d.querySelector('#mbf');
    const out = d.querySelector('#mb-out');
    if (!m) f.parser.onchange = () => { f.auto_reply.value = f.parser.value === 'zasilkovna_ritm' ? '0' : '1'; };
    const body = () => {
      const n = (v) => (v ? Number(v) : null);
      const b = {
        name: f.name.value.trim(), project_id: Number(f.project_id.value), parser: f.parser.value,
        imap_host: f.imap_host.value.trim(), imap_port: n(f.imap_port.value), imap_encryption: f.imap_encryption.value,
        imap_username: f.imap_username.value.trim(), imap_folder: f.imap_folder.value.trim() || 'INBOX',
        sent_folder: f.sent_folder.value.trim() || null,
        smtp_host: f.smtp_host.value.trim() || null, smtp_port: n(f.smtp_port.value), smtp_encryption: f.smtp_encryption.value,
        smtp_username: f.smtp_username.value.trim() || null, from_address: f.from_address.value.trim() || null,
        from_name: f.from_name.value.trim() || null, dedicated: f.dedicated.value === '1', auto_reply: f.auto_reply.value === '1',
        is_active: f.is_active.value === '1',
      };
      if (f.lookback_days.value) b.lookback_days = Number(f.lookback_days.value);
      if (f.imap_password.value) b.imap_password = f.imap_password.value;
      if (f.smtp_password.value) b.smtp_password = f.smtp_password.value;
      return b;
    };
    f.onsubmit = async (e) => {
      e.preventDefault();
      try {
        if (m) await api('PATCH', `/admin/mailboxes/${m.id}`, body()); else await api('POST', '/admin/mailboxes', body());
        close(true); toast(t('saved')); renderSettings();
      } catch (ex) { const err = d.querySelector('#mberr'); err.textContent = errText(ex); err.hidden = false; }
    };
    d.querySelector('#mb-test')?.addEventListener('click', async () => {
      out.textContent = '…';
      try {
        const r = await api('POST', `/admin/mailboxes/${m.id}/test`);
        const line = (label, x) => (x ? `${label}: ${x.ok ? '✅ ' + esc(t('connOk')) + (x.messages != null ? ` (${x.messages} ✉)` : '') : '❌ ' + esc(x.error)}` : '');
        out.innerHTML = [line('IMAP', r.imap), line('SMTP', r.smtp),
          r.imap?.folders ? `${esc(t('folders'))}: ${r.imap.folders.map((x) => `<code>${esc(x)}</code>`).join(', ')}` : '',
          r.sent ? `${esc(t('sentRitm'))}: ${r.sent.ok ? '✅ ' + r.sent.ritm : '❌ ' + esc(r.sent.error)}` : ''].filter(Boolean).join('<br>');
      } catch (ex) { out.textContent = errText(ex); }
    });
    d.querySelector('#mb-reproc')?.addEventListener('click', async () => {
      if (!window.confirm(t('reprocessConfirm'))) return;
      out.textContent = '…';
      try {
        const r = await api('POST', `/admin/mailboxes/${m.id}/reprocess`);
        out.innerHTML = `✅ ${r.reprocessed} ✉ · 🗑 ${r.deleted_tickets} · ⏸ ${r.kept}`;
      } catch (ex) { out.textContent = errText(ex); }
    });
    d.querySelector('#mb-fetch')?.addEventListener('click', async () => {
      out.textContent = '…';
      try {
        const r = await api('POST', `/admin/mailboxes/${m.id}/fetch`);
        const info = `${esc(r.folder || '')} · ${r.since ? 'od ' + esc(r.since) : 'UID > ' + r.after_uid} · nalezeno ${r.found ?? '?'}`;
        out.innerHTML = r.ok ? `✅ +${r.new} ✉ <span class="muted">(${info})</span>` : `❌ ${esc(r.error)} <span class="muted">(${info})</span>`;
      } catch (ex) { out.textContent = errText(ex); }
    });
  });
}

// ================================================================ start

window.addEventListener('hashchange', route);
route();


// ================================================================ cena, ceník, vyúčtování

function priceCard(p, manage) {
  const tag = (ov) => (ov === null || ov === undefined ? `<span class="muted small"> · ${esc(t('priceFromTariff'))}</span>` : `<span class="small" style="color:var(--warn,#b26a00)"> · ${esc(t('priceManual'))}</span>`);
  return `<div class="card">
    <h2>💰 ${esc(t('price'))}<span class="sp"></span>${manage ? `<button class="btn sm" id="btn-price">✏️ ${esc(t('priceEdit'))}</button>` : ''}</h2>
    ${!p.tariff && p.overrides.base === null && p.overrides.total === null ? `<div class="banner warn small">${esc(t('noTariff'))}</div>` : ''}
    <dl class="kv">
      <dt>${esc(t('priceBase'))}</dt><dd>${esc(money(p.base, p.currency))}${tag(p.overrides.base)}</dd>
      <dt>${esc(t('priceKm'))}</dt><dd>${esc(qtyFmtNum(p.km))} km × ${esc(money(p.km_rate, p.currency))}${tag(p.overrides.km_rate)} = <strong>${esc(money(p.km_amount, p.currency))}</strong></dd>
      ${p.extra ? `<dt>${esc(t('priceExtra'))}</dt><dd>${esc(money(p.extra, p.currency))}</dd>` : ''}
      ${p.manual_total ? `<dt class="muted">${esc(t('priceTotal'))} (${esc(t('priceFromTariff'))})</dt><dd class="muted"><s>${esc(money(p.computed, p.currency))}</s></dd>` : ''}
      <dt>${esc(t('priceTotal'))}</dt><dd><strong style="font-size:18px">${esc(money(p.total, p.currency))}</strong>${p.manual_total ? ` <span class="small" style="color:var(--warn,#b26a00)">(${esc(t('priceManual'))})</span>` : ''}</dd>
      ${p.note ? `<dt>${esc(t('priceNote'))}</dt><dd>${esc(p.note)}</dd>` : ''}
    </dl>
    ${p.locked_at ? `<div class="muted small">🔒 ${esc(t('priceLocked'))} · ${esc(fmt(p.locked_at))}</div>` : ''}
  </div>`;
}
const qtyFmtNum = (n) => Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 });

async function editPrice(tk) {
  const p = tk.price;
  const ph = (v, cur) => (v === null || v === undefined ? '' : String(v));
  const num = (v) => (v === '' || v === null ? null : Number(String(v).replace(',', '.')));
  await modal(`
    <h3>💰 ${esc(t('priceEdit'))} · ${esc(tk.number)}</h3>
    <form id="pf">
      <div class="form-grid">
        <label class="f"><span>${esc(t('priceBase'))}</span><input type="text" inputmode="decimal" name="price_base" value="${esc(ph(p.overrides.base))}" placeholder="${esc(p.tariff ? p.tariff.base_price + ' (' + t('priceFromTariff') + ')' : '0')}"></label>
        <label class="f"><span>${esc(t('priceKmRate'))}</span><input type="text" inputmode="decimal" name="price_km_rate" value="${esc(ph(p.overrides.km_rate))}" placeholder="${esc(p.tariff ? p.tariff.km_rate + ' (' + t('priceFromTariff') + ')' : '0')}"></label>
        <label class="f"><span>${esc(t('priceExtra'))}</span><input type="text" inputmode="decimal" name="price_extra" value="${esc(ph(p.overrides.extra))}" placeholder="0"></label>
        <label class="f"><span>${esc(t('currency'))}</span><select name="price_currency">
          <option value="">${esc(p.tariff ? p.tariff.currency + ' (' + t('priceFromTariff') + ')' : 'CZK')}</option>
          ${['CZK', 'EUR', 'PLN'].map((c) => `<option ${p.overrides.currency === c ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
        <label class="f full"><span>${esc(t('priceTotalOverride'))}</span><input type="text" inputmode="decimal" name="price_total" value="${esc(ph(p.overrides.total))}" placeholder="${esc(t('priceFromTariff'))}: ${esc(money(p.computed, p.currency))}">
          <small class="muted">${esc(t('priceTotalHint'))}</small></label>
        <label class="f full"><span>${esc(t('priceNote'))}</span><input type="text" name="price_note" maxlength="255" value="${esc(p.note || '')}"></label>
      </div>
      <div class="banner err" id="perr" hidden></div>
      <div class="modal-foot">
        <button type="button" class="btn" id="p-reset">↺ ${esc(t('priceReset'))}</button><span class="sp"></span>
        <button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button>
      </div>
    </form>`, (d, close) => {
    const f = d.querySelector('#pf');
    const send = async (body) => {
      try {
        await api('PATCH', `/tickets/${tk.id}/price`, body);
        close(true); toast(t('saved')); renderTicket(tk.id);
      } catch (ex) { const e = d.querySelector('#perr'); e.textContent = errText(ex); e.hidden = false; }
    };
    d.querySelector('#p-reset').onclick = () => send({ price_base: null, price_km_rate: null, price_extra: null, price_total: null, price_currency: null, price_note: f.price_note.value.trim() || null });
    f.onsubmit = (e) => {
      e.preventDefault();
      send({
        price_base: num(f.price_base.value.trim()), price_km_rate: num(f.price_km_rate.value.trim()),
        price_extra: num(f.price_extra.value.trim()), price_total: num(f.price_total.value.trim()),
        price_currency: f.price_currency.value || null, price_note: f.price_note.value.trim() || null,
      });
    };
  });
}

async function tariffForm(x, prj) {
  const cur = x || { currency: 'CZK', base_price: 0, km_rate: 0, service_type: 'servis' };
  const types = ['servis', 'profilaxe', 'udrzba', 'instalace'];
  await modal(`
    <h3>💰 ${esc(x ? t('tariffs') + ' · ' + (x.project?.name || '') : t('newTariff'))}</h3>
    <form id="tf">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${x ? 'disabled' : ''}>${prj.map((p) => `<option value="${p.id}" ${x?.project?.id === p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type"><option value="">${esc(t('allTypes'))}</option>${types.map((s) => `<option value="${s}" ${cur.service_type === s ? 'selected' : ''}>${esc(t('stype.' + s))}</option>`).join('')}</select></label>
        <label class="f"><span class="req">${esc(t('tariffBase'))}</span><input type="text" inputmode="decimal" name="base_price" required value="${esc(cur.base_price)}"></label>
        <label class="f"><span class="req">${esc(t('tariffKm'))}</span><input type="text" inputmode="decimal" name="km_rate" required value="${esc(cur.km_rate)}"></label>
        <label class="f"><span>${esc(t('currency'))}</span><select name="currency">${['CZK', 'EUR', 'PLN'].map((c) => `<option ${cur.currency === c ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('tariffNote'))}</span><input type="text" name="note" maxlength="255" value="${esc(cur.note || '')}"></label>
      </div>
      <div class="banner err" id="terr" hidden></div>
      <div class="modal-foot">
        ${x ? `<button type="button" class="btn danger" id="tf-del">${esc(t('del'))}</button>` : ''}<span class="sp"></span>
        <button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button>
      </div>
    </form>`, (d, close) => {
    const f = d.querySelector('#tf');
    const err = (ex) => { const e = d.querySelector('#terr'); e.textContent = errText(ex); e.hidden = false; };
    d.querySelector('#tf-del')?.addEventListener('click', async () => {
      if (!(await confirmModal(t('del') + '?'))) { renderSettings(); return; }
      try { await api('DELETE', `/tariffs/${x.id}`); toast(t('saved')); renderSettings(); } catch (ex) { toast(errText(ex), true); }
    });
    f.onsubmit = async (e) => {
      e.preventDefault();
      const body = {
        service_type: f.service_type.value || null, currency: f.currency.value, note: f.note.value.trim() || null,
        base_price: Number(f.base_price.value.replace(',', '.')), km_rate: Number(f.km_rate.value.replace(',', '.')),
      };
      try {
        if (x) await api('PATCH', `/tariffs/${x.id}`, body);
        else await api('POST', '/tariffs', { ...body, project_id: Number(f.project_id.value) });
        close(true); toast(t('saved')); renderSettings();
      } catch (ex) { err(ex); }
    };
  });
}

async function renderBilling(q) {
  const el = shell('billing');
  const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const now = new Date();
  const from = q.get('from') || ymd(new Date(now.getFullYear(), now.getMonth(), 1));
  const to = q.get('to') || ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0));
  const params = new URLSearchParams({ from, to, status: q.get('status') || 'done' });
  if (q.get('project_id')) params.set('project_id', q.get('project_id'));

  let r, prj;
  try { [r, prj] = await Promise.all([api('GET', '/billing?' + params), projects()]); } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  const go = (over) => { const n = new URLSearchParams(params); Object.entries(over).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); location.hash = '#/billing?' + n; };
  const sumTable = (rows, key, label) => rows.length ? `<table class="tbl"><thead><tr><th>${esc(label)}</th><th class="num">${esc(t('billCount'))}</th><th class="num">km</th><th class="num">${esc(t('priceTotal'))}</th></tr></thead><tbody>
    ${rows.map((x) => `<tr><td>${esc(x[key])}</td><td class="num">${x.count}</td><td class="num">${esc(qtyFmtNum(x.km))}</td><td class="num"><strong>${esc(money(x.total, x.currency))}</strong></td></tr>`).join('')}</tbody></table>` : '';

  el.innerHTML = `
    <div class="page-head"><h1>💰 ${esc(t('navBilling'))}</h1></div>
    <div class="card">
      <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));align-items:end">
        <label class="f"><span>${esc(t('billFrom'))}</span><input type="date" id="b-from" value="${esc(from)}"></label>
        <label class="f"><span>${esc(t('billTo'))}</span><input type="date" id="b-to" value="${esc(to)}"></label>
        <label class="f"><span>${esc(t('project'))}</span><select id="b-prj"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === q.get('project_id') ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('billStatus'))}</span><select id="b-st">${[['done', 'billDone'], ['closed', 'billClosed'], ['all', 'billAll']].map(([v, k]) => `<option value="${v}" ${params.get('status') === v ? 'selected' : ''}>${esc(t(k))}</option>`).join('')}</select></label>
      </div>
      <div class="btn-row" style="margin-top:10px">
        <button class="btn sm" id="b-this">${esc(t('thisMonth'))}</button>
        <button class="btn sm" id="b-last">${esc(t('lastMonth'))}</button>
        <span class="sp"></span>
        <button class="btn primary" id="b-csv">⬇ ${esc(t('billExport'))}</button>
      </div>
    </div>

    ${r.totals.length ? `<div class="card"><div class="btn-row" style="gap:24px">${r.totals.map((x) => `<div><div class="muted small">${x.count}× · ${esc(qtyFmtNum(x.km))} km</div><div style="font-size:24px;font-weight:700">${esc(money(x.total, x.currency))}</div></div>`).join('')}</div></div>` : ''}

    <div class="grid-2" style="grid-template-columns:1fr 1fr">
      <div class="card"><h2>${esc(t('billByProject'))}</h2>${sumTable(r.by_project, 'project', t('project')) || '<p class="muted">—</p>'}</div>
      <div class="card"><h2>${esc(t('billByTech'))}</h2>${sumTable(r.by_technician, 'technician', t('col.tech')) || '<p class="muted">—</p>'}</div>
    </div>

    <div class="card" style="overflow-x:auto">
      ${r.data.length ? `<table class="tbl"><thead><tr>
        <th>${esc(t('col.number'))}</th><th>${esc(t('col.location'))}</th><th>${esc(t('col.tech'))}</th><th>${esc(t('billDate'))}</th><th>${esc(t('col.status'))}</th>
        <th class="num">${esc(t('priceBase'))}</th><th class="num">km</th><th class="num">${esc(t('priceKmAmount'))}</th><th class="num">${esc(t('priceExtra'))}</th><th class="num">${esc(t('priceTotal'))}</th></tr></thead><tbody>
        ${r.data.map((x) => `<tr data-open="${x.id}" style="cursor:pointer">
          <td><strong>${esc(x.number || '#' + x.id)}</strong>${x.external_ref ? `<div class="muted small">${esc(x.external_ref)}</div>` : ''}</td>
          <td>${esc(x.location || '')}<div class="muted small">${esc(x.project || '')}${x.city ? ' · ' + esc(x.city) : ''}</div></td>
          <td>${esc(x.technician)}</td><td>${esc(fmt(x.date, false))}</td><td>${chip(x.status, t('status.' + x.status))}</td>
          <td class="num">${esc(money(x.base, x.currency))}</td><td class="num">${esc(qtyFmtNum(x.km))}</td><td class="num">${esc(money(x.km_amount, x.currency))}</td>
          <td class="num">${x.extra ? esc(money(x.extra, x.currency)) : ''}</td>
          <td class="num"><strong>${esc(money(x.total, x.currency))}</strong>${x.manual_total ? `<div class="small" style="color:var(--warn,#b26a00)">${esc(t('priceManual'))}</div>` : ''}</td></tr>`).join('')}
      </tbody></table>` : `<p class="muted">${esc(t('billEmpty'))}</p>`}
    </div>`;

  el.querySelector('#b-from').onchange = (e) => go({ from: e.target.value });
  el.querySelector('#b-to').onchange = (e) => go({ to: e.target.value });
  el.querySelector('#b-prj').onchange = (e) => go({ project_id: e.target.value });
  el.querySelector('#b-st').onchange = (e) => go({ status: e.target.value });
  el.querySelector('#b-this').onclick = () => go({ from: ymd(new Date(now.getFullYear(), now.getMonth(), 1)), to: ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0)) });
  el.querySelector('#b-last').onclick = () => go({ from: ymd(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: ymd(new Date(now.getFullYear(), now.getMonth(), 0)) });
  el.querySelectorAll('[data-open]').forEach((tr) => { tr.onclick = () => { location.hash = '#/t/' + tr.dataset.open; }; });
  el.querySelector('#b-csv').onclick = async () => {
    try {
      const res = await api('GET', '/billing/export?' + params, undefined, { raw: true });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(await res.blob());
      a.download = `vyuctovani_${from}_${to}.csv`;
      document.body.appendChild(a); a.click(); a.remove();
    } catch (e) { toast(errText(e), true); }
  };
}


// ================================================================ mobil: tabulky jako karty
// Každé tabulce v obsahu doplní k buňkám popisek ze záhlaví – na úzkém displeji se z řádků
// stanou karty (CSS table.resp), bez úprav jednotlivých stránek.
function labelTables(root) {
  root.querySelectorAll('table:not(.resp):not(.no-resp)').forEach((tbl) => {
    if (tbl.closest('.modal')) return;
    const heads = [...tbl.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (!heads.length) return;
    tbl.classList.add('resp');
    tbl.querySelectorAll('tbody tr').forEach((tr) => {
      [...tr.children].forEach((td, i) => {
        if (heads[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i]);
        if (i > 0 && !td.textContent.trim() && !td.querySelector('input,select,button,img')) td.classList.add('empty-m');
      });
    });
  });
}
new MutationObserver(() => labelTables($root)).observe($root, { childList: true, subtree: true });

// ================================================================ plánování (M4)

/** Modal: technik + den/čas. tk = {id, number, title, project_id, technician_id, planned_at}; preset = {technician_id, date} */
async function planModal(tk, preset = {}) {
  const techs = await technicians(tk.project_id);
  const techId = preset.technician_id ?? tk.technician_id;
  let when = tk.planned_at ? toLocalInput(tk.planned_at) : '';
  if (preset.date) when = preset.date + 'T' + (tk.planned_at ? toLocalInput(tk.planned_at).slice(11, 16) : '08:00');
  return modal(`
    <h3>📅 ${esc(t('plan'))} · ${esc(tk.number || '#' + tk.id)}</h3>
    <p class="muted small" style="margin-top:-8px">${esc(tk.title || '')}${tk.location ? ' · ' + esc(tk.location) : ''}</p>
    <form id="plf">
      <div class="form-grid">
        <label class="f"><span>${esc(t('technician'))}</span><select name="technician_id">${tk.technician_id ? '' : `<option value="">—</option>`}${techs.list.map((u) => `<option value="${u.id}" ${u.id === techId ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('plannedAt'))}</span><input type="datetime-local" name="planned_at" value="${esc(when)}" required></label>
      </div>
      <div class="banner err" id="plerr" hidden></div>
      <div class="modal-foot">
        ${tk.planned_at ? `<button type="button" class="btn danger" id="pl-un">${esc(t('unplan'))}</button>` : ''}<span class="sp"></span>
        <button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button>
      </div>
    </form>`, (d, close) => {
    const f = d.querySelector('#plf');
    const send = async (body) => {
      try { await api('POST', `/tickets/${tk.id}/plan`, body); toast(t('saved')); close(true); } catch (ex) { const e = d.querySelector('#plerr'); e.textContent = errText(ex); e.hidden = false; }
    };
    d.querySelector('#pl-un')?.addEventListener('click', () => send({ planned_at: null }));
    f.onsubmit = (e) => {
      e.preventDefault();
      send({ technician_id: f.technician_id.value ? Number(f.technician_id.value) : null, planned_at: fromLocalInput(f.planned_at.value) });
    };
  });
}

function weekStart(d) {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const wd = (x.getDay() + 6) % 7; // pondělí = 0
  x.setDate(x.getDate() - wd);
  return x;
}

async function renderPlanning(q) {
  const el = shell('planning');
  const tab = q.get('tab') || 'cal';
  const setQ = (ch) => { const n = new URLSearchParams(q); Object.entries(ch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); location.hash = '#/planning?' + n; };
  const prj = (await projects()).filter((p) => isAdmin() || managesProject(p.id) || has('ekonom', 'spolecnik'));
  const project = q.get('project') || '';

  const head = `
    <div class="page-head"><h1>📅 ${esc(t('navPlanning'))}</h1>
      ${tab === 'sch' ? `<button class="btn primary" id="sch-add">➕ ${esc(t('newSchedule'))}</button>` : `<button class="btn" id="abs-add">${esc(t('addAbsence'))}</button>`}
    </div>
    <div class="tabs">
      <button class="tab ${tab === 'cal' ? 'on' : ''}" data-tab="cal">🗓 ${esc(t('calendar'))}</button>
      <button class="tab ${tab === 'sch' ? 'on' : ''}" data-tab="sch">🔁 ${esc(t('schedules'))}</button>
    </div>
    <div class="filters"><select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></div>`;

  if (tab === 'sch') return renderSchedules(el, head, setQ, project, prj);

  const start = q.get('w') ? weekStart(new Date(q.get('w') + 'T12:00')) : weekStart(new Date());
  const days = [...Array(7)].map((_, i) => { const d = new Date(start); d.setDate(d.getDate() + i); return d; });
  const from = ymdLocal(days[0]);
  const to = ymdLocal(days[6]);

  let r;
  try { r = await api('GET', `/planning?from=${from}&to=${to}${project ? '&project_id=' + project : ''}`); } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  const todayYmd = ymdLocal(new Date());
  const dayLabel = (d) => d.toLocaleDateString(locale(), { weekday: 'short', day: 'numeric', month: 'numeric' });
  const hm = (iso) => new Date(iso).toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' });
  const all = [...r.planned, ...r.unplanned];
  const byId = new Map(all.map((x) => [x.id, x]));
  const card = (x, withTime) => `<div class="pl-item st-${esc(x.status)} prio-${esc(x.priority)}" draggable="true" data-tk="${x.id}" title="${esc(x.title)}">
      ${withTime && x.planned_at ? `<span class="pl-time">${esc(hm(x.planned_at))}</span>` : ''}<strong>${esc((x.location || x.title).split(' · ')[0])}</strong>
      <div class="pl-loc">${x.city ? esc(x.city) + ' · ' : ''}${esc(x.title)}</div>
      <div class="pl-due">${esc((x.number || '').replace(/^PAP-\d{4}-0*/, '#'))}${x.external_ref ? ' · ' + esc(x.external_ref) : ''}</div>
      ${!withTime && x.due_at ? `<div class="pl-due ${new Date(x.due_at) < new Date() ? 'overdue' : ''}">⏱ ${esc(fmt(x.due_at))}</div>` : ''}
      ${!withTime && x.technician ? `<div class="pl-due">👷 ${esc(x.technician)}</div>` : ''}
    </div>`;

  const absenceOn = (uid, ymd) => r.absences.find((a) => a.user_id === uid && a.from <= ymd && a.to >= ymd);
  const rows = r.technicians.map((u) => `<tr><td class="pl-tech"><strong>${esc(u.name)}</strong></td>${days.map((d) => {
    const ymd = ymdLocal(d);
    const items = r.planned.filter((x) => x.technician_id === u.id && ymdLocal(new Date(x.planned_at)) === ymd);
    const ab = absenceOn(u.id, ymd);
    return `<td class="pl-cell ${ymd === todayYmd ? 'today' : ''} ${ab ? 'absent' : ''}" data-cell="${u.id}|${ymd}">${items.length ? `<button class="pl-route" data-route="${u.id}|${ymd}" title="${esc(t('route'))}">🗺 ${items.length}</button>` : ''}${ab ? `<div class="pl-abs" data-abs="${ab.id}">🏖 ${esc(ab.note || t('absence'))}</div>` : ''}${items.map((x) => card(x, true)).join('')}</td>`;
  }).join('')}</tr>`).join('');
  // naplánované bez technika (nebo u technika mimo seznam)
  const orphan = r.planned.filter((x) => !r.technicians.some((u) => u.id === x.technician_id));

  el.innerHTML = head + `
    <div class="card pl-nav">
      <button class="btn sm" id="w-prev">◀</button>
      <button class="btn sm" id="w-today">${esc(t('today'))}</button>
      <button class="btn sm" id="w-next">▶</button>
      <strong style="margin-left:8px">${esc(days[0].toLocaleDateString(locale()))} – ${esc(days[6].toLocaleDateString(locale()))}</strong>
      <span class="sp"></span><span class="muted small hide-mobile">${esc(t('planHint'))}</span>
    </div>
    <div class="pl-wrap">
      <div class="card pl-unplanned" data-drop-unplan>
        <h2>${esc(t('unplanned'))} <span class="chip draft">${r.unplanned.length}</span></h2>
        ${r.unplanned.length ? r.unplanned.map((x) => card(x, false)).join('') : `<p class="muted">${esc(t('noUnplanned'))}</p>`}
        ${orphan.length ? `<h2 style="margin-top:14px">${esc(t('planned'))} – ${esc(t('technician'))} ?</h2>${orphan.map((x) => card(x, true)).join('')}` : ''}
      </div>
      <div class="card pl-grid-card">
        <table class="pl-grid"><thead><tr><th>${esc(t('technician'))}</th>${days.map((d) => `<th class="${ymdLocal(d) === todayYmd ? 'today' : ''}">${esc(dayLabel(d))}</th>`).join('')}</tr></thead>
        <tbody>${rows || `<tr><td colspan="8" class="muted">—</td></tr>`}</tbody></table>
      </div>
    </div>`;

  const shiftWeek = (n) => { const d = new Date(start); d.setDate(d.getDate() + n * 7); setQ({ w: ymdLocal(d) }); };
  el.querySelector('#w-prev').onclick = () => shiftWeek(-1);
  el.querySelector('#w-next').onclick = () => shiftWeek(1);
  el.querySelector('#w-today').onclick = () => setQ({ w: '' });
  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value });
  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => setQ({ tab: b.dataset.tab === 'cal' ? '' : b.dataset.tab }); });
  el.querySelector('#abs-add').onclick = () => absenceModal(r.technicians).then((ok) => ok && route());

  const reload = () => route();
  // klik na zakázku = plán; dvojklik = otevřít detail
  el.querySelectorAll('[data-tk]').forEach((c) => {
    c.onclick = async (e) => { e.stopPropagation(); const x = byId.get(Number(c.dataset.tk)); if (await planModal(x)) reload(); };
    c.ondblclick = (e) => { e.stopPropagation(); location.hash = '#/t/' + c.dataset.tk; };
    c.ondragstart = (e) => { e.dataTransfer.setData('text/plain', c.dataset.tk); e.dataTransfer.effectAllowed = 'move'; };
  });
  el.querySelectorAll('[data-route]').forEach((b) => {
    b.onclick = async (e) => {
      e.stopPropagation();
      const [uid, ymd] = b.dataset.route.split('|');
      if (await routeModal(Number(uid), ymd)) reload();
    };
  });
  el.querySelectorAll('[data-abs]').forEach((a) => {
    a.onclick = async (e) => {
      e.stopPropagation();
      if (!(await confirmModal(t('delete') + ' – ' + t('absence') + '?'))) return;
      try { await api('DELETE', `/absences/${a.dataset.abs}`); reload(); } catch (ex) { toast(errText(ex), true); }
    };
  });
  el.querySelectorAll('[data-cell]').forEach((cell) => {
    const [uid, ymd] = cell.dataset.cell.split('|');
    cell.onclick = async () => {
      if (!r.unplanned.length) return;
      const id = await modal(`<h3>${esc(t('pickTicket'))} · ${esc(r.technicians.find((u) => String(u.id) === uid)?.name || '')} · ${esc(new Date(ymd + 'T12:00').toLocaleDateString(locale()))}</h3>
        <div class="pl-pick">${r.unplanned.map((x) => card(x, false)).join('')}</div>
        <div class="modal-foot"><button class="btn" data-close>${esc(t('cancel'))}</button></div>`,
      (d, close) => { d.querySelectorAll('[data-tk]').forEach((c) => { c.draggable = false; c.onclick = () => close(Number(c.dataset.tk)); }); });
      if (id && await planModal(byId.get(id), { technician_id: Number(uid), date: ymd })) reload();
    };
    cell.ondragover = (e) => { e.preventDefault(); cell.classList.add('drop'); };
    cell.ondragleave = () => cell.classList.remove('drop');
    cell.ondrop = async (e) => {
      e.preventDefault(); cell.classList.remove('drop');
      const x = byId.get(Number(e.dataTransfer.getData('text/plain')));
      if (!x) return;
      const time = x.planned_at ? toLocalInput(x.planned_at).slice(11, 16) : '08:00';
      try {
        await api('POST', `/tickets/${x.id}/plan`, { technician_id: Number(uid), planned_at: fromLocalInput(ymd + 'T' + time) });
        toast(t('saved')); reload();
      } catch (ex) { toast(errText(ex), true); }
    };
  });
  const un = el.querySelector('[data-drop-unplan]');
  un.ondragover = (e) => e.preventDefault();
  un.ondrop = async (e) => {
    e.preventDefault();
    const x = byId.get(Number(e.dataTransfer.getData('text/plain')));
    if (!x || !x.planned_at) return;
    try { await api('POST', `/tickets/${x.id}/plan`, { planned_at: null }); reload(); } catch (ex) { toast(errText(ex), true); }
  };
}

async function absenceModal(techs) {
  const today = ymdLocal(new Date());
  return modal(`
    <h3>🏖 ${esc(t('absence'))}</h3>
    <form id="abf"><div class="form-grid">
      <label class="f full"><span>${esc(t('technician'))}</span><select name="user_id">${techs.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}</select></label>
      <label class="f"><span>${esc(t('dateFrom'))}</span><input type="date" name="date_from" value="${today}" required></label>
      <label class="f"><span>${esc(t('dateTo'))}</span><input type="date" name="date_to" value="${today}" required></label>
      <label class="f full"><span>${esc(t('absenceNote'))}</span><input type="text" name="note" maxlength="255"></label>
    </div>
    <div class="banner err" id="aberr" hidden></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`,
  (d, close) => {
    const f = d.querySelector('#abf');
    f.onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api('POST', '/absences', { user_id: Number(f.user_id.value), date_from: f.date_from.value, date_to: f.date_to.value, note: f.note.value.trim() || null });
        toast(t('saved')); close(true);
      } catch (ex) { const er = d.querySelector('#aberr'); er.textContent = errText(ex); er.hidden = false; }
    };
  });
}

async function renderSchedules(el, head, setQ, project, prj) {
  let list;
  try { list = (await api('GET', '/schedules' + (project ? '?project_id=' + project : ''))).data; } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }
  el.innerHTML = head + `
    <div class="table-wrap">${list.length ? `<table><thead><tr>
      <th>${esc(t('schedule'))}</th><th>${esc(t('location'))}</th><th>${esc(t('interval'))}</th><th>${esc(t('nextDue'))}</th><th>${esc(t('technician'))}</th><th>${esc(t('checklist'))}</th><th>${esc(t('active'))}</th></tr></thead><tbody>
      ${list.map((s, i) => `<tr data-s="${i}">
        <td><strong>${esc(s.title)}</strong><div class="muted small">${esc(s.project?.name || '')} · ${esc(t('stype.' + s.service_type))}</div></td>
        <td>${s.location ? `${esc(s.location.code)} · ${esc(s.location.name)}<div class="muted small">${esc(s.location.city || '')}${s.device ? ' · 🔧 ' + esc(s.device.type) : ''}</div>` : '—'}</td>
        <td>${esc(t('everyN').replace('{n}', s.interval_months))}</td>
        <td><strong>${esc(fmt(s.next_due_on, false))}</strong><div class="muted small">−${s.lead_days} d</div></td>
        <td>${esc(s.technician?.name || '—')}</td>
        <td>${esc(s.checklist?.name || '—')}</td>
        <td>${s.is_active ? '✓' : chip('off', t('inactive'))}</td></tr>`).join('')}
    </tbody></table>` : `<div class="card empty">—</div>`}</div>`;
  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value });
  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => setQ({ tab: b.dataset.tab === 'cal' ? '' : b.dataset.tab }); });
  el.querySelector('#sch-add').onclick = () => scheduleForm(null, prj, project);
  el.querySelectorAll('[data-s]').forEach((r) => { r.onclick = () => scheduleForm(list[Number(r.dataset.s)], prj, project); });
}

async function scheduleForm(s, prj, defaultProject) {
  let loc = s?.location || null;
  let projectId = s?.project?.id || Number(defaultProject) || prj[0]?.id;
  const nextDefault = ymdLocal(new Date(Date.now() + 30 * 864e5));
  let checklists = [];
  try { checklists = (await api('GET', '/checklists')).data; } catch { /* */ }
  await modal(`
    <h3>🔁 ${esc(s ? s.title : t('newSchedule'))}</h3>
    <form id="sf"><div class="form-grid">
      <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${s ? 'disabled' : ''}>${prj.map((p) => `<option value="${p.id}" ${p.id === projectId ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
      <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type">${SERVICE_TYPES.map((x) => `<option value="${x}" ${(s?.service_type || 'profilaxe') === x ? 'selected' : ''}>${esc(t('stype.' + x))}</option>`).join('')}</select></label>
      <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px" class="req">${esc(t('location'))}</span><div id="sloc"></div></div>
      <label class="f full"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required value="${esc(s?.title || '')}" placeholder="např. Profilaxe Z-BOX"></label>
      <label class="f"><span>🔧 ${esc(t('device'))}</span><select name="device_id"><option value="">${esc(t('noDevice'))}</option></select></label>
      <label class="f"><span>☑ ${esc(t('checklist'))}</span><select name="checklist_template_id"></select></label>
      <label class="f"><span class="req">${esc(t('interval'))}</span><input type="number" name="interval_months" min="1" max="60" required value="${s?.interval_months ?? 3}"></label>
      <label class="f"><span class="req">${esc(t('nextDue'))}</span><input type="date" name="next_due_on" required value="${esc(s?.next_due_on || nextDefault)}"></label>
      <label class="f"><span>${esc(t('leadDays'))}</span><input type="number" name="lead_days" min="0" max="120" value="${s?.lead_days ?? 14}"></label>
      <label class="f"><span>${esc(t('autoAssign'))}</span><select name="technician_id"><option value="">—</option></select></label>
      <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1">${esc(t('yes'))}</option><option value="0" ${s && !s.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
    </div>
    <div class="banner err" id="serr" hidden></div>
    <div class="modal-foot">
      ${s ? `<button type="button" class="btn danger" id="s-del">${esc(t('delete'))}</button><button type="button" class="btn" id="s-run">▶ ${esc(t('runNow'))}</button>` : ''}<span class="sp"></span>
      <button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button>
    </div></form>`, (d, close) => {
    const f = d.querySelector('#sf');
    const err = (ex) => { const e = d.querySelector('#serr'); e.textContent = errText(ex); e.hidden = false; };
    const fillProjectBound = async () => {
      f.checklist_template_id.innerHTML = `<option value="">${esc(t('noChecklist'))}</option>` + checklists.filter((c) => c.project_id === projectId)
        .map((c) => `<option value="${c.id}" ${s?.checklist?.id === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
      const techs = await technicians(projectId);
      f.technician_id.innerHTML = `<option value="">—</option>` + techs.list.map((u) => `<option value="${u.id}" ${s?.technician?.id === u.id ? 'selected' : ''}>${esc(u.name)}</option>`).join('');
    };
    const fillDevices = async () => {
      if (!loc) { f.device_id.innerHTML = `<option value="">${esc(t('noDevice'))}</option>`; return; }
      const r = await api('GET', `/devices?location_id=${loc.id}`);
      f.device_id.innerHTML = `<option value="">${esc(t('noDevice'))}</option>` + r.data.map((x) => `<option value="${x.id}" ${s?.device?.id === x.id ? 'selected' : ''}>${esc(x.type)}${x.serial_number ? ' · ' + esc(x.serial_number) : ''}</option>`).join('');
    };
    const picker = () => locationPicker(d.querySelector('#sloc'), () => projectId, (l) => { loc = l; fillDevices(); }, loc);
    f.project_id.onchange = () => { projectId = Number(f.project_id.value); loc = null; picker(); fillProjectBound(); fillDevices(); };
    picker(); fillProjectBound(); fillDevices();

    d.querySelector('#s-del')?.addEventListener('click', async () => {
      if (!(await confirmModal(t('delete') + '?'))) { route(); return; }
      try { await api('DELETE', `/schedules/${s.id}`); toast(t('saved')); route(); } catch (ex) { toast(errText(ex), true); }
    });
    d.querySelector('#s-run')?.addEventListener('click', async () => {
      try { const r = await api('POST', `/schedules/${s.id}/run`); close(true); toast(t('created') + ': ' + r.data.number); location.hash = '#/t/' + r.data.ticket_id; } catch (ex) { err(ex); }
    });
    f.onsubmit = async (e) => {
      e.preventDefault();
      if (!loc) { err({ message: t('location') + '?' }); return; }
      const body = {
        location_id: loc.id, title: f.title.value.trim(), service_type: f.service_type.value,
        device_id: f.device_id.value ? Number(f.device_id.value) : null,
        checklist_template_id: f.checklist_template_id.value ? Number(f.checklist_template_id.value) : null,
        technician_id: f.technician_id.value ? Number(f.technician_id.value) : null,
        interval_months: Number(f.interval_months.value), next_due_on: f.next_due_on.value,
        lead_days: Number(f.lead_days.value || 0), is_active: f.is_active.value === '1',
      };
      try {
        if (s) await api('PATCH', `/schedules/${s.id}`, body); else await api('POST', '/schedules', body);
        close(true); toast(t('saved')); route();
      } catch (ex) { err(ex); }
    };
  });
}

// ================================================================ zařízení (M4)

async function renderDevices(q) {
  const el = shell('devices');
  const setQ = (ch) => { const n = new URLSearchParams(q); Object.entries(ch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); n.delete('open'); location.hash = '#/devices?' + n; };
  const project = q.get('project') || '';
  const status = q.get('status') || 'active';
  const search = q.get('q') || '';
  const prj = await projects();
  const canEdit = has('admin', 'dispecer');

  const params = new URLSearchParams({ status, per_page: '300' });
  if (project) params.set('project_id', project);
  if (search) params.set('q', search);
  let r;
  try { r = await api('GET', '/devices?' + params); } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  el.innerHTML = `
    <div class="page-head"><h1>🔧 ${esc(t('devices'))}</h1>
      <button class="btn" id="d-csv">${esc(t('exportCsv'))}</button>
      ${canEdit ? `<button class="btn primary" id="d-add">➕ ${esc(t('newDevice'))}</button>` : ''}
    </div>
    <div class="filters">
      <select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
      <select id="f-status">${['active', 'removed', 'all'].map((x) => `<option value="${x}" ${x === status ? 'selected' : ''}>${esc(t('devStatus.' + x))}</option>`).join('')}</select>
      <input type="search" id="f-q" placeholder="${esc(t('serial'))}, ${esc(t('location'))}…" value="${esc(search)}">
    </div>
    <div class="table-wrap">${r.data.length ? `<table><thead><tr>
      <th>${esc(t('serial'))}</th><th>${esc(t('deviceType'))}</th><th>${esc(t('location'))}</th><th>${esc(t('installedAt'))}</th><th>${esc(t('handoverAt'))}</th><th>${esc(t('project'))}</th></tr></thead><tbody>
      ${r.data.map((x) => `<tr data-d="${x.id}">
        <td><strong>${esc(x.serial_number || '—')}</strong>${x.status === 'removed' ? ' ' + chip('off', t('devStatus.removed')) : ''}</td>
        <td>${esc(x.type)}${x.model ? `<div class="muted small">${esc(x.model)}</div>` : ''}</td>
        <td>${x.location ? `${esc(x.location.code)} · ${esc(x.location.name)}<div class="muted small">${esc(x.location.city || '')}</div>` : '—'}</td>
        <td>${esc(fmt(x.installed_at, false))}</td>
        <td>${x.handover_at ? esc(fmt(x.handover_at, false)) + (x.handover_to ? `<div class="muted small">${esc(x.handover_to)}</div>` : '') : '—'}</td>
        <td>${esc(x.project?.name || '')}</td></tr>`).join('')}
    </tbody></table>` : `<div class="card empty">${esc(t('noDevices'))}</div>`}</div>`;

  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value });
  el.querySelector('#f-status').onchange = (e) => setQ({ status: e.target.value });
  el.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
  if (search) { const i = el.querySelector('#f-q'); i.focus(); i.setSelectionRange(search.length, search.length); }
  el.querySelector('#d-add')?.addEventListener('click', () => deviceForm(null, prj, project));
  el.querySelectorAll('[data-d]').forEach((tr) => { tr.onclick = () => deviceModal(Number(tr.dataset.d), prj); });
  el.querySelector('#d-csv').onclick = async () => {
    try {
      const res = await api('GET', '/devices/export?' + params, undefined, { raw: true });
      const a = document.createElement('a'); a.href = URL.createObjectURL(await res.blob()); a.download = `zarizeni_${ymdLocal(new Date())}.csv`;
      document.body.appendChild(a); a.click(); a.remove();
    } catch (e) { toast(errText(e), true); }
  };
  if (q.get('open')) deviceModal(Number(q.get('open')), prj);
}

async function deviceModal(id, prj) {
  let x;
  try { x = (await api('GET', `/devices/${id}`)).data; } catch (e) { toast(errText(e), true); return; }
  const canEdit = isAdmin() || managesProject(x.project_id);
  const res = await modal(`
    <h3>🔧 ${esc(x.type)}${x.model ? ' · ' + esc(x.model) : ''}</h3>
    <dl class="kv">
      <dt>${esc(t('serial'))}</dt><dd><strong>${esc(x.serial_number || '—')}</strong></dd>
      <dt>${esc(t('location'))}</dt><dd>${x.location ? `${esc(x.location.code)} · ${esc(x.location.name)}${x.location.city ? ', ' + esc(x.location.city) : ''}` : '—'}</dd>
      <dt>${esc(t('project'))}</dt><dd>${esc(x.project?.name || '')}</dd>
      <dt>${esc(t('installedAt'))}</dt><dd>${esc(fmt(x.installed_at, false))}</dd>
      <dt>${esc(t('handoverAt'))}</dt><dd>${x.handover_at ? esc(fmt(x.handover_at, false)) + (x.handover_to ? ' · ' + esc(x.handover_to) : '') : '—'}</dd>
      ${x.note ? `<dt>${esc(t('tariffNote'))}</dt><dd class="desc">${esc(x.note)}</dd>` : ''}
    </dl>
    <h3 style="margin:16px 0 8px;font-size:15px">${esc(t('history'))}</h3>
    <div class="history">${x.events.map((e) => `<div><strong>${esc(t('evt.' + e.type))}</strong> · ${esc(fmt(e.happened_at))}${e.user ? ' · ' + esc(e.user) : ''}
      ${e.ticket ? ` · <a href="#/t/${e.ticket.id}">${esc(e.ticket.number)}</a>` : ''}
      ${e.old_serial || e.new_serial ? `<div class="small">S/N ${esc(e.old_serial || '—')} → <strong>${esc(e.new_serial || '—')}</strong></div>` : ''}
      ${e.note ? `<div class="muted small desc">${esc(e.note)}</div>` : ''}</div>`).join('') || '<p class="muted">—</p>'}</div>
    ${x.tickets.length ? `<h3 style="margin:16px 0 8px;font-size:15px">${esc(t('navTickets'))}</h3>
      <div class="history">${x.tickets.map((tk) => `<div><a href="#/t/${tk.id}">${esc(tk.number)}</a> · ${esc(tk.title)} ${chip(tk.status, t('status.' + tk.status))} <span class="muted small">${esc(fmt(tk.created_at, false))}${tk.technician ? ' · ' + esc(tk.technician) : ''}</span></div>`).join('')}</div>` : ''}
    <div class="modal-foot">
      ${canEdit ? `<button class="btn" id="dv-evt">${esc(t('addEvent'))}</button><button class="btn" id="dv-edit">✎ ${esc(t('edit'))}</button>` : ''}<span class="sp"></span>
      <button class="btn" data-close>${esc(t('cancel'))}</button>
    </div>`, (d, close) => {
    d.querySelector('#dv-edit')?.addEventListener('click', () => close('edit'));
    d.querySelector('#dv-evt')?.addEventListener('click', () => close('evt'));
    d.querySelectorAll('a[href^="#/t/"]').forEach((a) => a.addEventListener('click', () => close()));
  });
  if (res === 'edit') { if (await deviceForm(x, prj)) deviceModal(id, prj); }
  if (res === 'evt') { if (await deviceEventForm(x)) deviceModal(id, prj); }
}

async function deviceEventForm(x) {
  return modal(`
    <h3>${esc(t('addEvent'))} · ${esc(x.type)} ${esc(x.serial_number || '')}</h3>
    <form id="evf"><div class="form-grid">
      <label class="f"><span>Typ</span><select name="type">${['note', 'service', 'replace', 'handover', 'install', 'remove'].map((k) => `<option value="${k}">${esc(t('evt.' + k))}</option>`).join('')}</select></label>
      <label class="f"><span>${esc(t('plannedAt'))}</span><input type="datetime-local" name="happened_at" value="${esc(toLocalInput(new Date().toISOString()))}"></label>
      <label class="f"><span>${esc(t('oldSerial'))}</span><input type="text" name="old_serial" value="${esc(x.serial_number || '')}"></label>
      <label class="f"><span>${esc(t('newSerial'))}</span><input type="text" name="new_serial"></label>
      <label class="f full"><span>${esc(t('tariffNote'))}</span><textarea name="note"></textarea></label>
    </div>
    <div class="banner err" id="everr" hidden></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`,
  (d, close) => {
    const f = d.querySelector('#evf');
    f.onsubmit = async (e) => {
      e.preventDefault();
      const isSn = f.type.value === 'replace';
      try {
        await api('POST', `/devices/${x.id}/events`, {
          type: f.type.value, note: f.note.value.trim() || null, happened_at: fromLocalInput(f.happened_at.value),
          old_serial: isSn ? f.old_serial.value.trim() || null : null, new_serial: isSn ? f.new_serial.value.trim() || null : null,
        });
        toast(t('saved')); close(true);
      } catch (ex) { const er = d.querySelector('#everr'); er.textContent = errText(ex); er.hidden = false; }
    };
  });
}

async function deviceForm(x, prj, defaultProject) {
  const editable = prj.filter((p) => managesProject(p.id));
  let projectId = x?.project_id || Number(defaultProject) || editable[0]?.id;
  let loc = x?.location || null;
  const types = ['Z-BOX', 'Paczkomat', 'Kiosek', 'Pokladna', 'Terminál', 'Tiskárna', 'Displej', 'Router'];
  return modal(`
    <h3>🔧 ${esc(x ? t('edit') : t('newDevice'))}</h3>
    <form id="dvf"><div class="form-grid">
      <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${x ? 'disabled' : ''}>${editable.map((p) => `<option value="${p.id}" ${p.id === projectId ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
      <label class="f"><span class="req">${esc(t('deviceType'))}</span><input type="text" name="type" list="dv-types" required value="${esc(x?.type || '')}"><datalist id="dv-types">${types.map((v) => `<option value="${v}">`).join('')}</datalist></label>
      <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('location'))}</span><div id="dvloc"></div></div>
      <label class="f"><span>${esc(t('model'))}</span><input type="text" name="model" value="${esc(x?.model || '')}"></label>
      <label class="f"><span>${esc(t('serial'))}</span><input type="text" name="serial_number" value="${esc(x?.serial_number || '')}"></label>
      <label class="f"><span>${esc(t('installedAt'))}</span><input type="date" name="installed_at" value="${esc(x?.installed_at || '')}"></label>
      <label class="f"><span>${esc(t('handoverAt'))}</span><input type="date" name="handover_at" value="${esc(x?.handover_at || '')}"></label>
      <label class="f"><span>${esc(t('handoverTo'))}</span><input type="text" name="handover_to" value="${esc(x?.handover_to || '')}"></label>
      ${x ? `<label class="f"><span>Stav</span><select name="status">${['active', 'removed'].map((k) => `<option value="${k}" ${x.status === k ? 'selected' : ''}>${esc(t('devStatus.' + k))}</option>`).join('')}</select></label>` : ''}
      <label class="f full"><span>${esc(t('tariffNote'))}</span><textarea name="note">${esc(x?.note || '')}</textarea></label>
    </div>
    <div class="banner err" id="dverr" hidden></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`,
  (d, close) => {
    const f = d.querySelector('#dvf');
    const picker = () => locationPicker(d.querySelector('#dvloc'), () => projectId, (l) => { loc = l; }, loc);
    f.project_id.onchange = () => { projectId = Number(f.project_id.value); loc = null; picker(); };
    picker();
    f.onsubmit = async (e) => {
      e.preventDefault();
      const body = {
        location_id: loc ? loc.id : null, type: f.type.value.trim(), model: f.model.value.trim() || null, serial_number: f.serial_number.value.trim() || null,
        installed_at: f.installed_at.value || null, handover_at: f.handover_at.value || null, handover_to: f.handover_to.value.trim() || null, note: f.note.value.trim() || null,
        ...(x ? { status: f.status.value } : { project_id: projectId }),
      };
      try {
        if (x) await api('PATCH', `/devices/${x.id}`, body); else await api('POST', '/devices', body);
        toast(t('saved')); close(true);
        if (!x) route();
      } catch (ex) { const er = d.querySelector('#dverr'); er.textContent = errText(ex); er.hidden = false; }
    };
  });
}

// ================================================================ nastavení: projekty a kontrolní seznamy (M4)

async function projectForm(p) {
  const slaKeys = ['urgent', 'high', 'normal', 'low'];
  const ec = (p?.end_customers || []).map((e) => `${e.code};${e.name}`).join('\n');
  await modal(`
    <h3>🗂 ${esc(p ? p.name : t('newProject'))}</h3>
    <form id="pjf"><div class="form-grid">
      <label class="f"><span class="req">Kód</span><input type="text" name="code" required pattern="[A-Za-z0-9_-]+" value="${esc(p?.code || '')}" style="text-transform:uppercase"></label>
      <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(p?.name || '')}"></label>
      <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('serviceTypes'))}</span>
        <div class="checks">${SERVICE_TYPES.map((x) => `<label><input type="checkbox" name="st" value="${x}" ${(p?.service_types || ['servis']).includes(x) ? 'checked' : ''}> ${esc(t('stype.' + x))}</label>`).join('')}</div></div>
      <label class="f"><span>${esc(t('countries'))}</span><input type="text" name="countries" value="${esc((p?.countries || ['CZ']).join(', '))}"></label>
      <label class="f"><span>${esc(t('protocolTpl'))}</span><select name="protocol_template"><option value="standard">Standard (CZ/PL)</option><option value="m4b" ${p?.protocol_template === 'm4b' ? 'selected' : ''}>M4B – Wykonanych prac</option></select></label>
      <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('sla'))}</span>
        <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">${slaKeys.map((k) => `<label class="f" style="margin:0"><span>${esc(t('prio.' + k))}</span><input type="number" min="1" name="sla_${k}" value="${esc(p?.sla_hours?.[k] ?? '')}" placeholder="—"></label>`).join('')}</div></div>
      <label class="f full"><span>${esc(t('endCustomers'))}</span><textarea name="ec" placeholder="AMREST;KFC · SBX · BK AmRest">${esc(ec)}</textarea></label>
      <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1">${esc(t('yes'))}</option><option value="0" ${p && !p.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
    </div>
    <div class="banner err" id="pjerr" hidden></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`,
  (d, close) => {
    const f = d.querySelector('#pjf');
    f.onsubmit = async (e) => {
      e.preventDefault();
      const sla = {};
      slaKeys.forEach((k) => { const v = f['sla_' + k].value; if (v) sla[k] = Number(v); });
      const existing = new Map((p?.end_customers || []).map((x) => [x.code, x.id]));
      const body = {
        code: f.code.value.trim().toUpperCase(), name: f.name.value.trim(),
        service_types: [...f.querySelectorAll('[name=st]:checked')].map((c) => c.value),
        countries: f.countries.value.split(/[\s,;]+/).map((x) => x.trim()).filter(Boolean),
        protocol_template: f.protocol_template.value, sla_hours: Object.keys(sla).length ? sla : null, is_active: f.is_active.value === '1',
        end_customers: f.ec.value.split('\n').map((l) => l.split(';').map((x) => x.trim())).filter(([c, n]) => c && n)
          .map(([c, n]) => ({ id: existing.get(c.toUpperCase()) || null, code: c.toUpperCase(), name: n })),
      };
      try {
        if (p) await api('PATCH', `/admin/projects/${p.id}`, body); else await api('POST', '/admin/projects', body);
        state.projects = null; close(true); toast(t('saved')); renderSettings();
      } catch (ex) { const er = d.querySelector('#pjerr'); er.textContent = errText(ex); er.hidden = false; }
    };
  });
}

async function checklistForm(c, prj) {
  const lines = (c?.items || []).map((i) => (i.required ? '* ' : '') + i.text).join('\n');
  await modal(`
    <h3>☑ ${esc(c ? c.name : t('newChecklist'))}</h3>
    <form id="clf"><div class="form-grid">
      <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${c ? 'disabled' : ''}>${prj.map((p) => `<option value="${p.id}" ${c?.project_id === p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
      <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type"><option value="">${esc(t('allTypes'))}</option>${SERVICE_TYPES.map((x) => `<option value="${x}" ${c?.service_type === x ? 'selected' : ''}>${esc(t('stype.' + x))}</option>`).join('')}</select></label>
      <label class="f full"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(c?.name || '')}"></label>
      <label class="f full"><span class="req">${esc(t('checklistItems'))}</span><textarea name="items" rows="10" required placeholder="* Kontrola napájení&#10;* Test dveří schránek&#10;Čištění displeje">${esc(lines)}</textarea></label>
      <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1">${esc(t('yes'))}</option><option value="0" ${c && !c.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
    </div>
    <div class="banner err" id="clerr" hidden></div>
    <div class="modal-foot">${c ? `<button type="button" class="btn danger" id="cl-del">${esc(t('delete'))}</button>` : ''}<span class="sp"></span>
      <button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div></form>`,
  (d, close) => {
    const f = d.querySelector('#clf');
    d.querySelector('#cl-del')?.addEventListener('click', async () => {
      if (!(await confirmModal(t('delete') + '?'))) { renderSettings(); return; }
      try { await api('DELETE', `/checklists/${c.id}`); toast(t('saved')); renderSettings(); } catch (ex) { toast(errText(ex), true); }
    });
    f.onsubmit = async (e) => {
      e.preventDefault();
      // zachovat id položek se stejným textem (vyplněné seznamy zůstanou spárované)
      const old = new Map((c?.items || []).map((i) => [i.text, i.id]));
      const items = f.items.value.split('\n').map((l) => l.trim()).filter(Boolean).map((l) => {
        const required = l.startsWith('*');
        const text = l.replace(/^\*\s*/, '');
        return { id: old.get(text) || null, text, required };
      });
      const body = { name: f.name.value.trim(), service_type: f.service_type.value || null, items, is_active: f.is_active.value === '1' };
      try {
        if (c) await api('PATCH', `/checklists/${c.id}`, body); else await api('POST', '/checklists', { ...body, project_id: Number(f.project_id.value) });
        close(true); toast(t('saved')); renderSettings();
      } catch (ex) { const er = d.querySelector('#clerr'); er.textContent = errText(ex); er.hidden = false; }
    };
  });
}


// ================================================================ trasa technika (mapa)

let leafletLoading;
function loadLeaflet() {
  if (window.L) return Promise.resolve(window.L);
  leafletLoading ??= new Promise((resolve, reject) => {
    const css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = '/vendor/leaflet/leaflet.css';
    document.head.appendChild(css);
    const js = document.createElement('script');
    js.src = '/vendor/leaflet/leaflet.js';
    js.onload = () => resolve(window.L);
    js.onerror = () => { leafletLoading = null; reject(new Error('Mapu se nepodařilo načíst')); };
    document.head.appendChild(js);
  });
  return leafletLoading;
}

function gmapsRouteUrl(r) {
  const pts = r.stops.map((s) => (s.location?.lat != null ? `${s.location.lat},${s.location.lng}` : s.location?.address)).filter(Boolean);
  if (!pts.length) return null;
  const origin = r.home?.lat != null ? `${r.home.lat},${r.home.lng}` : r.home?.address || '';
  const dest = origin && r.home ? origin : pts[pts.length - 1];
  const wps = (origin && r.home ? pts : pts.slice(0, -1)).slice(0, 9);
  return 'https://www.google.com/maps/dir/?api=1&travelmode=driving' + (origin ? '&origin=' + encodeURIComponent(origin) : '')
    + '&destination=' + encodeURIComponent(dest) + (wps.length ? '&waypoints=' + encodeURIComponent(wps.join('|')) : '');
}

/** Trasa technika na den: mapa, pořadí, km, optimalizace. Vrací true, když se změnil plán. */
async function routeModal(techId, date) {
  let r;
  try { r = (await api('GET', `/routes?technician_id=${techId}&date=${date}`)).data; } catch (e) { toast(errText(e), true); return false; }
  let changed = false;
  let departure = '08:00';
  const hm = (iso) => new Date(iso).toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' });

  await modal(`<div id="rt"></div>`, (d, close) => {
    d.classList.add('modal-wide');
    const box = d.querySelector('#rt');
    let map;
    const draw = async () => {
      const gm = gmapsRouteUrl(r);
      box.innerHTML = `
        <h3>🗺 ${esc(t('route'))} · ${esc(r.technician.name)} · ${esc(new Date(date + 'T12:00').toLocaleDateString(locale(), { weekday: 'short', day: 'numeric', month: 'numeric' }))}</h3>
        ${!r.home?.lat ? `<div class="banner warn small">${esc(t('routeNoHome'))}</div>` : ''}
        ${r.missing_coords ? `<div class="banner warn small">${esc(t('routeMissing').replace('{n}', r.missing_coords))} <button class="btn sm" id="rt-geo">📍 ${esc(t('routeGeocode'))}</button></div>` : ''}
        <div class="rt-wrap">
          <div id="rt-map" class="rt-map"></div>
          <div class="rt-list">
            <div class="rt-sum"><strong>${esc(t('routeTotal'))}: ≈ ${r.total_km} km</strong> · ${Math.floor(r.drive_minutes / 60)} h ${r.drive_minutes % 60} min ${esc(t('routeDrive'))}</div>
            ${r.home ? `<div class="rt-stop home">🏠 ${esc(r.home.address || '')}</div>` : ''}
            ${r.stops.map((s, i) => `
              ${s.leg_km != null ? `<div class="rt-leg">↓ ${s.leg_km} km</div>` : ''}
              <div class="rt-stop"><span class="rt-no">${i + 1}</span><div>
                <strong>${s.planned_at ? esc(hm(s.planned_at)) + ' · ' : ''}${esc(s.location ? s.location.code : s.title)}</strong>${s.location?.lat == null ? ' <span title="bez polohy">⚠</span>' : ''}
                <div class="muted small">${esc(s.location?.address || '')}</div>
                <div class="small"><a href="#/t/${s.ticket_id}" data-close>${esc(s.number || '')}</a> · ${esc(s.title)}</div></div></div>`).join('')}
            ${r.return_km != null ? `<div class="rt-leg">↓ ${r.return_km} km · 🏠 ${esc(t('routeBack'))}</div>` : ''}
          </div>
        </div>
        <div class="rt-opt">
          <label class="f" style="margin:0"><span>${esc(t('routeStart'))}</span><input type="time" id="rt-start" value="${esc(departure)}"></label>
          <label class="f" style="margin:0"><span>${esc(t('routeService'))}</span><input type="number" id="rt-svc" min="5" max="480" value="45"></label>
          <button class="btn primary" id="rt-opt">⚡ ${esc(t('routeOptimize'))}</button>
          <span class="sp"></span>
          ${gm ? `<a class="btn" href="${esc(gm)}" target="_blank" rel="noopener">🧭 ${esc(t('routeOpenGmaps'))}</a>` : ''}
          <button class="btn" data-close>${esc(t('cancel'))}</button>
        </div>`;
      box.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => close()));
      box.querySelector('#rt-opt').onclick = async (e) => {
        e.target.disabled = true;
        try {
          departure = box.querySelector('#rt-start').value || '08:00';
          r = (await api('POST', '/routes/optimize', { technician_id: techId, date, start: departure, service_minutes: Number(box.querySelector('#rt-svc').value || 45) })).data;
          changed = true; toast(t('saved')); draw();
        } catch (ex) { toast(errText(ex), true); e.target.disabled = false; }
      };
      box.querySelector('#rt-geo')?.addEventListener('click', async (e) => {
        e.target.disabled = true; e.target.textContent = '…';
        try { r = (await api('POST', '/routes/geocode', { technician_id: techId, date })).data; draw(); } catch (ex) { toast(errText(ex), true); }
      });

      try {
        const L = await loadLeaflet();
        if (map) map.remove();
        map = L.map(box.querySelector('#rt-map'), { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
        const pts = [];
        if (r.home?.lat != null) {
          pts.push([r.home.lat, r.home.lng]);
          L.marker([r.home.lat, r.home.lng], { icon: L.divIcon({ className: 'rt-pin home', html: '🏠', iconSize: [28, 28] }) }).addTo(map).bindPopup(esc(r.home.address || ''));
        }
        r.stops.forEach((s, i) => {
          if (s.location?.lat == null) return;
          pts.push([s.location.lat, s.location.lng]);
          L.marker([s.location.lat, s.location.lng], { icon: L.divIcon({ className: 'rt-pin', html: String(i + 1), iconSize: [28, 28] }) })
            .addTo(map).bindPopup(`<strong>${i + 1}. ${esc(s.location.code)}</strong><br>${esc(s.location.name)}<br>${esc(s.number || '')} · ${esc(s.title)}`);
        });
        if (r.home?.lat != null && pts.length > 1) pts.push([r.home.lat, r.home.lng]);
        if (pts.length > 1) {
          L.polyline(pts, { color: '#1667ab', weight: 3, opacity: 0.8, dashArray: '6 6' }).addTo(map);
          map.fitBounds(pts, { padding: [30, 30] });
        } else if (pts.length === 1) map.setView(pts[0], 12);
        else map.setView([49.8, 18.2], 8);
        setTimeout(() => map.invalidateSize(), 50);
      } catch (ex) {
        box.querySelector('#rt-map').innerHTML = `<div class="muted small" style="padding:20px">${esc(ex.message)}</div>`;
      }
    };
    draw();
    d.addEventListener('close', () => { d.classList.remove('modal-wide'); map?.remove(); }, { once: true });
  });
  return changed;
}
