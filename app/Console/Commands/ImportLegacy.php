<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import ze stávající databáze servicepap (M1, krok 03).
 *
 * Zatím umí jen inventuru zdroje (tabulky a počty záznamů) – samotné mapování
 * doplníme podle exportu struktury od Piotra. Všechny importované záznamy
 * si nesou legacy_id, takže import jde pouštět opakovaně (upsert) a porovnat
 * počty se zdrojem (kontrolní bod 1.2).
 */
class ImportLegacy extends Command
{
    protected $signature = 'pap:import-legacy {--inventory : Jen vypsat tabulky a počty záznamů ve zdroji}';

    protected $description = 'Import dat z původní databáze servicepap';

    public function handle(): int
    {
        try {
            $tables = collect(DB::connection('legacy')->select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0]);
        } catch (Throwable $e) {
            $this->error('Nelze se připojit k LEGACY_DB_*: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = $tables->map(fn ($t) => [$t, DB::connection('legacy')->table($t)->count()]);
        $this->table(['Tabulka', 'Záznamů'], $rows->all());

        if (! $this->option('inventory')) {
            $this->warn('Mapování tabulek se doplní podle exportu struktury stávající DB. Zatím jen --inventory.');
        }

        return self::SUCCESS;
    }
}
