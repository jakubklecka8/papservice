<?php

namespace App\Services\Mail;

/**
 * Čistý text z e-mailu: HTML → text bez stylů/skriptů, bez prázdných řádků navíc,
 * bez patiček (ServiceNow, Unsubscribe…). Pro popis zakázky navíc bez šablonových
 * pravidel Zásilkovny („PRAVIDLA PŘI ODEPISOVÁNÍ…“, TO-DO LIST).
 */
class MailText
{
    public static function fromHtml(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', ' ', $html);
        $html = preg_replace('/<(style|script|head|title|xml)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $html = preg_replace('/<(br|\/p|\/div|\/tr|\/li|\/h\d|\/table)[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::clean($text);
    }

    /** Odstraní zbytky CSS, patičky a nadbytečné mezery */
    public static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\xC2\xA0"], ["\n", "\n", ' '], $text);
        $lines = array_map(fn ($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), explode("\n", $text));

        $out = [];
        $depth = 0; // uvnitř CSS bloku { … }
        foreach ($lines as $line) {
            // patička ServiceNow – dál už nic užitečného
            if ($depth === 0 && preg_match('/^(Unsubscribe \| Notification Preferences|©\s*\d{4} ServiceNow|Ref:\s*MSG\d+)/i', $line)) {
                break;
            }

            // zbytky <style>: „selektor, selektor {“ … „}“ (i přes víc řádků, i vnořené @media)
            if ($depth > 0 || str_contains($line, '{')) {
                if ($depth === 0) {
                    // odebrat předchozí řádky se selektory („body,“ „table,“ „* “)
                    while ($out && (end($out) === '' || preg_match('/^[#.*\w\-:>\[\]="\'() ]{1,80},?$/u', end($out)) && preg_match('/(^[#.*@]|,$|^[a-z0-9\-]+$)/', end($out)))) {
                        array_pop($out);
                    }
                }
                $depth += substr_count($line, '{') - substr_count($line, '}');
                if ($depth < 0) {
                    $depth = 0;
                }

                continue;
            }

            if ($line === '' && (end($out) === '' || $out === [])) {
                continue; // max. jeden prázdný řádek za sebou
            }
            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }

    /** Pro popis zakázky: bez šablonových pravidel a to-do listu Zásilkovny */
    public static function forDescription(string $text): string
    {
        $text = self::clean($text);
        $cut = preg_split('/^\s*(PRAVIDLA|PRAVDILA) PŘI ODEPISOVÁNÍ|^\s*TO-DO LIST\s*$/mu', $text);

        return trim($cut[0] ?? $text);
    }
}
