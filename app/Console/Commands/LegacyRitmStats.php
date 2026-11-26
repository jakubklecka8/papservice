<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Přehled tabulky service_calls ve staré aplikaci – podle čeho poznat vyřízené RITM */
class LegacyRitmStats extends Command
{
    protected $signature = 'pap:legacy-ritm-stats';

    protected $description = 'Statistika RITM ve staré DB (service_calls)';

    public function handle(): int
    {
        try {
            $db = DB::connection('legacy');
            $cols = Schema::connection('legacy')->getColumnListing('service_calls');
            $this->line('Sloupce: '.implode(', ', $cols));
            $this->line('Celkem: '.$db->table('service_calls')->count());
            foreach (['status', 'assignment_status', 'email_sent_to_zasilkovna'] as $c) {
                if (in_array($c, $cols, true)) {
                    $rows = $db->table('service_calls')->selectRaw("$c as v, count(*) as n")->groupBy($c)->get();
                    $this->line("$c: ".$rows->map(fn ($r) => var_export($r->v, true).'='.$r->n)->implode(', '));
                }
            }
            $since = now()->subDays(30)->toDateTimeString();
            $recent = $db->table('service_calls')->where('created_at', '>=', $since);
            $this->line('Za 30 dní: '.(clone $recent)->count());
            if (in_array('status', $cols, true)) {
                $this->line('Za 30 dní podle status: '.(clone $recent)->selectRaw('status as v, count(*) as n')->groupBy('status')->get()
                    ->map(fn ($r) => $r->v.'='.$r->n)->implode(', '));
            }
            $sample = $db->table('service_calls')->orderByDesc('id')->limit(8)
                ->get(array_values(array_intersect(['id', 'ritm_number', 'status', 'assignment_status', 'technician', 'completed_at', 'email_sent_to_zasilkovna', 'created_at'], $cols)));
            foreach ($sample as $r) {
                $this->line(json_encode($r, JSON_UNESCAPED_UNICODE));
            }
        } catch (Throwable $e) {
            $this->error('Chyba: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
