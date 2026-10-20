<?php

namespace App\Http\Controllers\Api\Bookings;

use App\Http\Controllers\Controller;
use App\Models\BookingService;
use App\Models\BookingUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Units (halls/chalets/rooms) and extra services. Route: /app/bookings/{kind} kind ∈ units|services */
class CatalogController extends Controller
{
    private const RULES = [
        'units' => ['name' => 'required|string|max:120', 'name_en' => 'nullable|string|max:120', 'type' => 'required|in:hall,chalet,room,other', 'capacity' => 'nullable|integer|min:0',
            'price_day' => 'nullable|numeric|min:0', 'price_night' => 'nullable|numeric|min:0', 'price_hour' => 'nullable|numeric|min:0', 'deposit' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:150', 'color' => 'nullable|string|max:10', 'description' => 'nullable|string', 'is_active' => 'boolean'],
        'services' => ['name' => 'required|string|max:120', 'name_en' => 'nullable|string|max:120', 'price' => 'required|numeric|min:0', 'unit_id' => 'nullable|integer', 'is_active' => 'boolean'],
    ];

    public function index(string $kind): JsonResponse
    {
        if ($kind === 'units') {
            return response()->json(BookingUnit::withCount(['bookings as upcoming_count' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])->where('end_at', '>=', now())])->orderBy('name')->get());
        }

        return response()->json(BookingService::with('unit:id,name')->orderBy('name')->get());
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        $model = $this->model($kind);
        $row = $model::create($request->validate(self::RULES[$kind]));

        return response()->json($row, 201);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        $row = $this->model($kind)::findOrFail($id);
        $row->update($request->validate(self::RULES[$kind]));

        return response()->json($row->fresh());
    }

    public function destroy(string $kind, int $id): JsonResponse
    {
        $row = $this->model($kind)::findOrFail($id);
        if ($kind === 'units' && $row->bookings()->exists()) {
            $row->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $row->delete();

        return response()->json(['ok' => true]);
    }

    private function model(string $kind): string
    {
        abort_unless(isset(self::RULES[$kind]), 404);

        return $kind === 'units' ? BookingUnit::class : BookingService::class;
    }
}
