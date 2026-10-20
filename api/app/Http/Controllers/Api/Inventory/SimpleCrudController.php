<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Categories, units, warehouses and suppliers share the same tiny CRUD shape.
 * Route: /app/inventory/{kind}  where kind ∈ categories|units|warehouses|suppliers
 */
class SimpleCrudController extends Controller
{
    private const KINDS = [
        'categories' => [Category::class, ['name' => 'required|string|max:100', 'parent_id' => 'nullable|integer', 'sort' => 'integer']],
        'units' => [Unit::class, ['name' => 'required|string|max:50', 'symbol' => 'nullable|string|max:20']],
        'warehouses' => [Warehouse::class, ['name' => 'required|string|max:100', 'location' => 'nullable|string|max:150', 'is_default' => 'boolean', 'is_active' => 'boolean']],
        'suppliers' => [Supplier::class, ['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email', 'tax_number' => 'nullable|string|max:50', 'address' => 'nullable|string|max:255', 'notes' => 'nullable|string', 'is_active' => 'boolean']],
    ];

    public function index(string $kind): JsonResponse
    {
        [$model] = $this->kind($kind);
        $q = $model::query()->orderBy('name');
        if ($kind === 'warehouses') {
            $q->withCount('stockLevels')->orderByDesc('is_default');
        }
        if ($kind === 'suppliers') {
            $q->withCount('purchases');
        }

        return response()->json($q->get());
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        [$model, $rules] = $this->kind($kind);
        $row = $model::create($request->validate($rules));
        $this->afterSave($kind, $row);

        return response()->json($row->fresh(), 201);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        [$model, $rules] = $this->kind($kind);
        $row = $model::findOrFail($id);
        $row->update($request->validate($rules));
        $this->afterSave($kind, $row);

        return response()->json($row->fresh());
    }

    public function destroy(string $kind, int $id): JsonResponse
    {
        [$model] = $this->kind($kind);
        $row = $model::findOrFail($id);

        if ($kind === 'warehouses' && ($row->is_default || $row->stockLevels()->where('qty', '!=', 0)->exists())) {
            return response()->json(['message' => 'لا يمكن حذف المستودع الافتراضي أو مستودع فيه رصيد.'], 422);
        }
        if ($kind === 'suppliers' && $row->purchases()->exists()) {
            $row->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $row->delete();

        return response()->json(['ok' => true]);
    }

    private function afterSave(string $kind, Model $row): void
    {
        if ($kind === 'warehouses' && $row->is_default) {
            Warehouse::where('id', '!=', $row->id)->update(['is_default' => false]);
        }
    }

    private function kind(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }
}
