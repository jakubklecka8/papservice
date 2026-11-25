<?php

return [
    // Max. velikost přílohy v KB (fotky z telefonu se zmenšují už v aplikaci)
    'upload_max_kb' => (int) env('PAP_UPLOAD_MAX_KB', 15360),

    // Tajný token pro spouštění cronu přes URL (když hosting neumí PHP CLI)
    'cron_token' => env('PAP_CRON_TOKEN'),

    // Tajný token pro údržbu bez SSH (migrace, cache). Prázdný = vypnuto.
    'maintenance_token' => env('PAP_MAINTENANCE_TOKEN'),

    // Kolik sekund smí jeden běh cronu zpracovávat frontu
    'tick_seconds' => (int) env('PAP_TICK_SECONDS', 45),

    // Admin účet vytvořený seederem
    // Testovací účty (DemoSeeder) – jen mimo produkci
    'seed_demo' => (bool) env('PAP_SEED_DEMO', false),

    'admin_email' => env('PAP_ADMIN_EMAIL'),
    'admin_password' => env('PAP_ADMIN_PASSWORD'),
];
