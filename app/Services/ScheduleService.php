<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Schedule;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Harmonogram pravidelné údržby: když se blíží termín (next_due_on − lead_days),
 * založí zakázku s termínem a kontrolním seznamem, případně ji rovnou přidělí,
 * a posune další termín o interval. Spouští se z pap:tick.
 */
class ScheduleService
{
    /** @return int počet založených zakázek */
    public function run(?Carbon $today = null): int
    {
        $today = ($today ?? now('Europe/Prague'))->copy()->startOfDay();
        $created = 0;

        $due = Schedule::where('is_active', true)
            ->get()
            ->filter(fn (Schedule $s) => $s->next_due_on->copy()->subDays($s->lead_days)->lte($today));

        foreach ($due as $schedule) {
            try {
                $this->generate($schedule, $today);
                $created++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $created;
    }

    public function generate(Schedule $schedule, ?Carbon $today = null): Ticket
    {
        $today = ($today ?? now('Europe/Prague'))->copy()->startOfDay();

        return DB::transaction(function () use ($schedule, $today) {
            $schedule->loadMissing(['location', 'technician']);
            $dueOn = $schedule->next_due_on->copy();

            $ticket = Ticket::create([
                'project_id' => $schedule->project_id,
                'end_customer_id' => $schedule->location?->end_customer_id,
                'location_id' => $schedule->location_id,
                'device_id' => $schedule->device_id,
                'schedule_id' => $schedule->id,
                'checklist_template_id' => $schedule->checklist_template_id,
                'service_type' => $schedule->service_type,
                'priority' => 'normal',
                'status' => TicketStatus::New,
                'source' => 'schedule',
                'title' => $schedule->title,
                'description' => 'Plánovaná '.$schedule->service_type.' podle harmonogramu (každé '.$schedule->interval_months.' měs.).',
                // termín = den plánované údržby (konec dne, Praha)
                'due_at' => Carbon::parse($dueOn->toDateString().' 23:59', 'Europe/Prague')->utc(),
            ]);
            $ticket->history()->create(['to_status' => TicketStatus::New->value, 'note' => 'Založeno harmonogramem']);

            // další termín – přeskočí intervaly, které už jsou celé v minulosti
            $next = $dueOn->copy()->addMonthsNoOverflow($schedule->interval_months);
            while ($next->copy()->subDays($schedule->lead_days)->lte($today)) {
                $next->addMonthsNoOverflow($schedule->interval_months);
            }
            $schedule->update(['next_due_on' => $next->toDateString(), 'last_generated_at' => now()]);

            if ($schedule->technician && $schedule->technician->is_active) {
                app(TicketAssigner::class)->assign($ticket, $schedule->technician, null, 'Přiděleno harmonogramem');
            }

            return $ticket;
        });
    }
}
