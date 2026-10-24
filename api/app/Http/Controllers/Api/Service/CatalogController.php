<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use App\Models\ServiceTechnician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Technicians and the price list. Route: /app/service/{kind}, kind ∈ technicians|catalog */
class CatalogController extends Controller
{
    private const MAP = [
        'technicians' => [ServiceTechnician::class, ['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'specialty' => 'nullable|string|max:150', 'user_id' => 'nullable|integer', 'is_active' => 'boolean']],
        'catalog' => [ServiceCatalog::class, ['name' => 'required|string|max:200', 'kind' => 'required|in:labor,part', 'price' => 'nullable|numeric|min:0', 'cost' => 'nullable|numeric|min:0', 'unit' => 'nullable|string|max:30', 'is_active' => 'boolean']],
    ];

    public function index(Request $request, string $kind): JsonResponse
    {
        [$model] = self::MAP[$kind];
        $q = $model::query()->orderBy('name');
        if ($kind === 'technicians') {
            $q->withCount(['orders as open_orders' => fn ($o) => $o->whereNotIn('status', ['delivered', 'cancelled'])])
                ->withCount(['orders as done_month' => fn ($o) => $o->where('status', 'delivered')->where('delivered_at', '>=', now()->startOfMonth())]);
        }
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }

        return response()->json($q->get());
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        [$model, $rules] = self::MAP[$kind];

        return response()->json($model::create($this->clean($request->validate($rules))), 201);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        [$model, $rules] = self::MAP[$kind];
        $row = $model::findOrFail($id);
        $row->update($this->clean($request->validate($rules)));

        return response()->json($row);
    }

    public function destroy(string $kind, int $id): JsonResponse
    {
        [$model] = self::MAP[$kind];
        $row = $model::findOrFail($id);
        if ($kind === 'technicians' && $row->orders()->exists()) {
            $row->update(['is_active' => false]);

            return response()->json(['deactivated' => true]);
        }
        $row->delete();

        return response()->json(['ok' => true]);
    }

    private function clean(array $d): array
    {
        foreach (['price', 'cost'] as $k) {
            if (array_key_exists($k, $d)) {
                $d[$k] = $d[$k] ?? 0;
            }
        }

        return $d;
    }
}
