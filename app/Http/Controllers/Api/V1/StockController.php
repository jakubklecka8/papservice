<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\StockMinimum;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Sklady, katalog dílů, stav zásob a pohyby (M4) */
class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    // ------------------------------------------------------------ sklady

    public function warehouses(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasRole(Role::Technik)) {
            Warehouse::forTechnician($user); // technik má sklad vždy
        }

        $list = Warehouse::query()->visibleTo($user)->with('technician:id,name')
            ->orderByRaw("case type when 'central' then 0 when 'project' then 1 else 2 end")->orderBy('name')
            ->get();

        return response()->json(['data' => $list]);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['central', 'project', 'technician'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'end_customer_id' => ['nullable', 'integer', 'exists:end_customers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:warehouses,user_id'],
        ]);

        return response()->json(['data' => Warehouse::create($data)], 201);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'end_customer_id' => ['nullable', 'integer', 'exists:end_customers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $warehouse->update($data);

        return response()->json(['data' => $warehouse]);
    }

    // ------------------------------------------------------------ katalog dílů

    public function parts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer'],
            'all' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $q = Part::query()->orderBy('name');
        if (empty($data['all'])) {
            $q->where('is_active', true);
        }
        if (! empty($data['project_id'])) {
            $q->where(fn ($w) => $w->where('project_id', $data['project_id'])->orWhereNull('project_id'));
        }
        if (! empty($data['q'])) {
            $like = '%'.$data['q'].'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('part_number', 'like', $like));
        }

        return response()->json($q->paginate($data['per_page'] ?? 200));
    }

    public function storePart(Request $request): JsonResponse
    {
        $this->ensureStockManager($request->user());

        return response()->json(['data' => Part::create($this->validatePart($request, null))], 201);
    }

    public function updatePart(Request $request, Part $part): JsonResponse
    {
        $this->ensureStockManager($request->user());
        $part->update($this->validatePart($request, $part));

        return response()->json(['data' => $part]);
    }

    private function validatePart(Request $request, ?Part $part): array
    {
        return $request->validate([
            'part_number' => [$part ? 'sometimes' : 'required', 'string', 'max:64', Rule::unique('parts')->ignore($part?->id)],
            'name' => [$part ? 'sometimes' : 'required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:8'],
            'serialized' => ['sometimes', 'boolean'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    // ------------------------------------------------------------ stav zásob

    /** Stav zásob. Technik vidí jen svůj sklad (warehouse=mine). */
    public function levels(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'warehouse_id' => ['nullable'],               // číslo nebo "mine"
            'part_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'nonzero' => ['nullable', 'boolean'],
        ]);

        $visible = Warehouse::query()->visibleTo($user)->pluck('id')->all();
        if (($data['warehouse_id'] ?? null) === 'mine') {
            $ids = [Warehouse::forTechnician($user)->id];
        } elseif (! empty($data['warehouse_id'])) {
            abort_unless(in_array((int) $data['warehouse_id'], $visible, true), 403);
            $ids = [(int) $data['warehouse_id']];
        } else {
            $ids = $visible;
        }

        $levels = $this->stock->levels($ids, ! empty($data['part_id']) ? [(int) $data['part_id']] : null);
        $mins = StockMinimum::whereIn('warehouse_id', $ids)->get()->keyBy(fn ($m) => $m->warehouse_id.'-'.$m->part_id);

        // díly s minimem, ale bez pohybu, mají stav 0
        foreach ($mins as $key => $m) {
            if (! $levels->contains(fn ($l) => $l->warehouse_id.'-'.$l->part_id === $key)) {
                $levels->push((object) ['part_id' => $m->part_id, 'warehouse_id' => $m->warehouse_id, 'qty' => 0.0]);
            }
        }

        $parts = Part::whereIn('id', $levels->pluck('part_id')->unique())->get()->keyBy('id');
        $q = mb_strtolower($data['q'] ?? '');

        $rows = $levels
            ->filter(fn ($l) => empty($data['nonzero']) || abs($l->qty) > 1e-9 || isset($mins[$l->warehouse_id.'-'.$l->part_id]))
            ->filter(fn ($l) => $q === '' || str_contains(mb_strtolower(($parts[$l->part_id]->name ?? '').' '.($parts[$l->part_id]->part_number ?? '')), $q))
            ->map(function ($l) use ($parts, $mins) {
                $min = $mins[$l->warehouse_id.'-'.$l->part_id]->min_qty ?? null;
                $p = $parts[$l->part_id] ?? null;

                return [
                    'warehouse_id' => $l->warehouse_id,
                    'part' => $p ? $p->only(['id', 'part_number', 'name', 'unit', 'serialized']) : ['id' => $l->part_id],
                    'qty' => $l->qty,
                    'min_qty' => $min,
                    'below_min' => $min !== null && $l->qty < $min,
                ];
            })
            ->sortBy(fn ($r) => $r['part']['name'] ?? '')
            ->values();

        return response()->json(['data' => $rows, 'warehouse_ids' => $ids]);
    }

    public function movements(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'part_id' => ['nullable', 'integer'],
            'ticket_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $visible = Warehouse::query()->visibleTo($user)->pluck('id')->all();
        $q = StockMovement::query()->with(['part:id,part_number,name,unit', 'from:id,code,name', 'to:id,code,name', 'user:id,name', 'ticket:id,number'])
            ->where(fn ($w) => $w->whereIn('from_warehouse_id', $visible)->orWhereIn('to_warehouse_id', $visible))
            ->orderByDesc('id');

        if (! empty($data['warehouse_id'])) {
            $wid = (int) $data['warehouse_id'];
            $q->where(fn ($w) => $w->where('from_warehouse_id', $wid)->orWhere('to_warehouse_id', $wid));
        }
        foreach (['part_id', 'ticket_id'] as $f) {
            if (! empty($data[$f])) {
                $q->where($f, $data[$f]);
            }
        }

        return response()->json($q->paginate($data['per_page'] ?? 50));
    }

    /** Příjem, převod nebo korekce (admin, dispečer) */
    public function storeMovement(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureStockManager($user);

        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'type' => ['required', Rule::in(['receipt', 'transfer', 'adjustment'])],
            'part_id' => ['required', 'integer', 'exists:parts,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'from_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $from = $data['from_warehouse_id'] ?? null;
        $to = $data['to_warehouse_id'] ?? null;
        $ok = match ($data['type']) {
            'receipt' => $to && ! $from,
            'transfer' => $to && $from,
            'adjustment' => ($to xor $from),
        };
        abort_unless($ok, 422, match ($data['type']) {
            'receipt' => 'Příjem musí mít jen cílový sklad.',
            'transfer' => 'Převod musí mít zdrojový i cílový sklad.',
            default => 'Korekce musí mít buď zdrojový (úbytek), nebo cílový (přírůstek) sklad.',
        });

        $movement = $this->stock->move($data, $user);

        return response()->json(['data' => $movement->load(['part', 'from', 'to'])], 201);
    }

    public function setMinimum(Request $request): JsonResponse
    {
        $this->ensureStockManager($request->user());
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'part_id' => ['required', 'integer', 'exists:parts,id'],
            'min_qty' => ['required', 'numeric', 'min:0'],
        ]);

        if ((float) $data['min_qty'] <= 0) {
            StockMinimum::where('warehouse_id', $data['warehouse_id'])->where('part_id', $data['part_id'])->delete();
        } else {
            StockMinimum::updateOrCreate(
                ['warehouse_id' => $data['warehouse_id'], 'part_id' => $data['part_id']],
                ['min_qty' => $data['min_qty']],
            );
        }

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $visible = Warehouse::query()->visibleTo($request->user())->pluck('id')->all();

        return response()->json(['data' => $this->stock->reorder($visible)]);
    }

    private function ensureStockManager(User $user): void
    {
        abort_unless($user->hasRole(Role::Admin, Role::Dispecer), 403);
    }
}
