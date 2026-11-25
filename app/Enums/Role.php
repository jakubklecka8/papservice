<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Ekonom = 'ekonom';
    case Spolecnik = 'spolecnik';
    case Dispecer = 'dispecer';
    case Technik = 'technik';
    case Zakaznik = 'zakaznik';

    public function label(string $locale = 'cs'): string
    {
        return match ($this) {
            self::Admin => $locale === 'pl' ? 'Administrator' : 'Administrátor',
            self::Ekonom => $locale === 'pl' ? 'Ekonomista' : 'Ekonom',
            self::Spolecnik => $locale === 'pl' ? 'Wspólnik' : 'Společník',
            self::Dispecer => $locale === 'pl' ? 'Dyspozytor' : 'Dispečer',
            self::Technik => $locale === 'pl' ? 'Technik' : 'Technik',
            self::Zakaznik => $locale === 'pl' ? 'Klient' : 'Zákazník',
        };
    }
}
