<?php

namespace App\Services;

use App\Models\Part;
use App\Models\StockMinimum;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Visit;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stav zásob = příjmy do skladu − výdeje ze skladu (součet pohybů).
 */
class StockService
{
    /**
     * @param  list<int>|null  $warehouseIds
     * @return Collection<int, object{part_id:int, warehouse_id:int, qty:float}>
     */
    public function levels(?array $warehouseIds = null, ?array $partIds = null): Collection
    {
        $in = DB::table('stock_movements')->whereNotNull('to_warehouse_id')
            ->selectRaw('part_id, to_warehouse_id as warehouse_id, quantity as q');
        $out = DB::table('stock_movements')->whereNotNull('from_warehouse_id')
            ->selectRaw('part_id, from_warehouse_id as warehouse_id, -quantity as q');

        $rows = DB::query()->fromSub($in->unionAll($out), 'm')
            ->selectRaw('part_id, warehouse_id, SUM(q) as qty')
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($partIds !== null, fn ($q) => $q->whereIn('part_id', $partIds))
            ->groupBy('part_id', 'warehouse_id')
            ->get();

        return $rows->map(fn ($r) => (object) [
            'part_id' => (int) $r->part_id,
            'warehouse_id' => (int) $r->warehouse_id,
            'qty' => round((float) $r->qty, 2),
        ]);
    }

    public function qty(int $warehouseId, int $partId): float
    {
        return (float) ($this->levels([$warehouseId], [$partId])->first()->qty ?? 0);
    }

    /**
     * Ruční pohyb (příjem, převod, korekce). Ze skladu nejde vydat víc, než v něm je.
     *
     * @param  array{uuid?:string, type:string, part_id:int, quantity:float, from_warehouse_id?:?int, to_warehouse_id?:?int, serial_number?:?string, note?:?string, ticket_id?:?int}  $data
     */
    public function move(array $data, User $user): StockMovement
    {
        return DB::transaction(function () use ($data, $user) {
            if (! empty($data['uuid']) && ($existing = StockMovement::where('uuid', $data['uuid'])->first())) {
                return $existing; // opakované odeslání formuláře
            }

            if (! empty($data['from_warehouse_id'])) {
                // zamknout pohyby dílu, aby dva souběžné výdeje nepřečerpaly sklad
                StockMovement::where('part_id', $data['part_id'])->lockForUpdate()->get(['id']);
                $available = $this->qty((int) $data['from_warehouse_id'], (int) $data['part_id']);
                if ($available + 1e-9 < (float) $data['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Na skladě je jen %s.', rtrim(rtrim(number_format($available, 2, ',', ''), '0'), ',')),
                    ]);
                }
            }

            return StockMovement::create($data + ['user_id' => $user->id]);
        });
    }

    /**
     * Spotřeba dílů z návštěvy ze skladu technika. Volá se při odeslání návštěvy;
     * při opakovaném odeslání (po vrácení) se spotřeba přepočítá – nic se nezdvojí.
     * Technik může jít do mínusu (díl vzal jinde) – v přehledu je to pak vidět.
     */
    public function syncConsumption(Visit $visit): void
    {
        $visit->loadMissing(['parts.part', 'technician']);
        $warehouse = Warehouse::forTechnician($visit->technician);

        DB::transaction(function () use ($visit, $warehouse) {
            StockMovement::where('visit_id', $visit->id)->where('type', 'consumption')->delete();

            foreach ($visit->parts as $vp) {
                if (! $vp->part_id || $vp->quantity <= 0) {
                    continue;
                }
                StockMovement::create([
                    'type' => 'consumption',
                    'part_id' => $vp->part_id,
                    'quantity' => $vp->quantity,
                    'from_warehouse_id' => $warehouse->id,
                    'serial_number' => $vp->serial_installed,
                    'ticket_id' => $visit->ticket_id,
                    'visit_id' => $visit->id,
                    'visit_part_id' => $vp->id,
                    'user_id' => $visit->technician_id,
                    'note' => 'Spotřeba na zakázce',
                ]);
            }
        });
    }

    /** Návrhy doplnění: díly pod nastaveným minimem */
    public function reorder(?array $warehouseIds = null): Collection
    {
        $mins = StockMinimum::query()
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->get();
        if ($mins->isEmpty()) {
            return collect();
        }

        $levels = $this->levels($mins->pluck('warehouse_id')->unique()->values()->all(), $mins->pluck('part_id')->unique()->values()->all())
            ->keyBy(fn ($r) => $r->warehouse_id.'-'.$r->part_id);
        $parts = Part::whereIn('id', $mins->pluck('part_id'))->get()->keyBy('id');
        $whs = Warehouse::whereIn('id', $mins->pluck('warehouse_id'))->get()->keyBy('id');

        return $mins->map(function ($m) use ($levels, $parts, $whs) {
            $qty = (float) ($levels[$m->warehouse_id.'-'.$m->part_id]->qty ?? 0);

            return [
                'warehouse' => ['id' => $m->warehouse_id, 'code' => $whs[$m->warehouse_id]->code ?? null, 'name' => $whs[$m->warehouse_id]->name ?? null],
                'part' => $parts[$m->part_id]?->only(['id', 'part_number', 'name', 'unit']),
                'qty' => $qty,
                'min_qty' => $m->min_qty,
                'suggested' => max(0, round($m->min_qty - $qty, 2)),
            ];
        })->filter(fn ($r) => $r['qty'] < $r['min_qty'])->values();
    }
}
