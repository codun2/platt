<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\BomLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Recipes / bills of materials with live batch cost. */
class BomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Bom::with('item:id,name,sale_price,unit_id', 'item.unit:id,name,symbol', 'lines.item:id,name,cost_price')->withCount('orders')->orderBy('name')->get()
            ->map(fn (Bom $b) => $b->setAttribute('batch_cost', $this->cost($b)));

        return response()->json($rows);
    }

    public function show(int $id): JsonResponse
    {
        $b = Bom::with('item.unit:id,name,symbol', 'lines.item.unit:id,name,symbol')->findOrFail($id);
        $b->setAttribute('batch_cost', $this->cost($b));
        $b->setAttribute('recent_orders', $b->orders()->latest('id')->limit(10)->get(['id', 'number', 'planned_qty', 'actual_qty', 'status', 'unit_cost', 'completed_at']));

        return response()->json($b);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $this->validated($request);
        $b = DB::transaction(function () use ($d) {
            $b = Bom::create(collect($d)->except('lines')->all());
            $this->syncLines($b, $d['lines']);

            return $b;
        });

        return response()->json($b, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $b = Bom::findOrFail($id);
        $d = $this->validated($request);
        DB::transaction(function () use ($b, $d) {
            $b->update(collect($d)->except('lines')->all());
            $b->lines()->delete();
            $this->syncLines($b, $d['lines']);
        });

        return response()->json($b->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $b = Bom::withCount('orders')->findOrFail($id);
        if ($b->orders_count) {
            $b->update(['is_active' => false]);

            return response()->json(['deactivated' => true]);
        }
        $b->delete();

        return response()->json(['ok' => true]);
    }

    /** Material cost (incl. waste) + extra cost for one batch. */
    public static function cost(Bom $b): float
    {
        return round($b->lines->sum(fn ($l) => (float) $l->qty * (1 + (float) $l->waste_pct / 100) * (float) ($l->item->cost_price ?? 0)) + (float) $b->extra_cost, 3);
    }

    private function syncLines(Bom $b, array $lines): void
    {
        foreach (array_values($lines) as $i => $l) {
            BomLine::create(['bom_id' => $b->id, 'item_id' => $l['item_id'], 'qty' => $l['qty'], 'waste_pct' => $l['waste_pct'] ?? 0, 'sort' => $i]);
        }
    }

    private function validated(Request $request): array
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $t)], 'name' => 'required|string|max:150',
            'output_qty' => 'required|numeric|gt:0', 'extra_cost' => 'nullable|numeric|min:0', 'instructions' => 'nullable|string', 'is_active' => 'boolean',
            'lines' => 'required|array|min:1', 'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $t)],
            'lines.*.qty' => 'required|numeric|gt:0', 'lines.*.waste_pct' => 'nullable|numeric|min:0|max:100',
        ]);
        abort_if(collect($d['lines'])->contains('item_id', $d['item_id']), 422, 'لا يمكن أن يكون المنتج مكوّناً في وصفته نفسها.');
        $d['extra_cost'] = $d['extra_cost'] ?? 0;

        return $d;
    }
}
