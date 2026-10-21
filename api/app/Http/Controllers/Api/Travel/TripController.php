<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\TravelPackage;
use App\Models\Trip;
use App\Models\TripExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Trip::with('package:id,name')
            ->withCount(['travelers as travelers_count' => fn ($x) => $x->where('status', '!=', 'cancelled')])
            ->withSum(['travelers as revenue' => fn ($x) => $x->where('status', '!=', 'cancelled')], 'price')
            ->withSum(['travelers as collected' => fn ($x) => $x->where('status', '!=', 'cancelled')], 'paid')
            ->withSum('expenses as expenses_total', 'amount')
            ->orderByDesc('departure_date');
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('destination', 'like', "%$s%"));
        }

        return response()->json($request->boolean('all') ? $q->get() : $q->paginate(30));
    }

    public function show(int $id): JsonResponse
    {
        $trip = Trip::with('package:id,name,price', 'travelers.package:id,name', 'expenses')->findOrFail($id);
        $active = $trip->travelers->where('status', '!=', 'cancelled');

        return response()->json([
            'trip' => $trip,
            'stats' => [
                'travelers' => $active->count(),
                'seats_left' => max(0, (int) $trip->capacity - $active->count()),
                'revenue' => (float) $active->sum('price'),
                'collected' => (float) $active->sum('paid'),
                'expenses' => (float) $trip->expenses->sum('amount'),
                'profit' => (float) $active->sum('price') - (float) $trip->expenses->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Trip::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $trip->update($this->validated($request));

        return response()->json($trip->fresh()->load('package:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $trip = Trip::withCount('travelers')->findOrFail($id);
        if ($trip->travelers_count > 0) {
            return response()->json(['message' => 'لا يمكن حذف رحلة فيها مسافرون — ألغِها بدلاً من ذلك.'], 422);
        }
        $trip->delete();

        return response()->json(['ok' => true]);
    }

    public function addExpense(Request $request, int $id): JsonResponse
    {
        $trip = Trip::findOrFail($id);
        $data = $request->validate([
            'category' => ['required', Rule::in(['flights', 'hotel', 'transport', 'visa', 'food', 'guide', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0'], 'date' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $e = TripExpense::create($data + ['trip_id' => $trip->id, 'date' => $data['date'] ?? now()->toDateString()]);

        return response()->json($e, 201);
    }

    public function deleteExpense(int $id, int $expenseId): JsonResponse
    {
        TripExpense::where('trip_id', $id)->findOrFail($expenseId)->delete();

        return response()->json(['ok' => true]);
    }

    /* ---- packages ---- */
    public function packages(): JsonResponse
    {
        return response()->json(TravelPackage::withCount('trips')->orderBy('name')->get());
    }

    public function storePackage(Request $request): JsonResponse
    {
        return response()->json(TravelPackage::create($this->packageRules($request)), 201);
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $p = TravelPackage::findOrFail($id);
        $p->update($this->packageRules($request));

        return response()->json($p->fresh());
    }

    public function destroyPackage(int $id): JsonResponse
    {
        $p = TravelPackage::withCount('trips')->findOrFail($id);
        if ($p->trips_count > 0) {
            $p->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $p->delete();

        return response()->json(['ok' => true]);
    }

    private function packageRules(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:120', 'type' => ['required', Rule::in(['umrah', 'hajj', 'tourism'])], 'price' => 'required|numeric|min:0', 'includes' => 'nullable|string', 'is_active' => 'boolean']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::in(['umrah', 'hajj', 'tourism'])],
            'destination' => ['nullable', 'string', 'max:120'],
            'transport' => ['nullable', Rule::in(['flight', 'bus', 'private', 'mixed'])],
            'airline' => ['nullable', 'string', 'max:80'],
            'flight_no' => ['nullable', 'string', 'max:40'],
            'boarding_point' => ['nullable', 'string', 'max:120'],
            'hotel_makkah' => ['nullable', 'string', 'max:120'],
            'nights_makkah' => ['nullable', 'integer', 'min:0', 'max:365'],
            'hotel_madinah' => ['nullable', 'string', 'max:120'],
            'nights_madinah' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supervisor' => ['nullable', 'string', 'max:120'],
            'supervisor_phone' => ['nullable', 'string', 'max:30'],
            'departure_date' => ['required', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'package_id' => ['nullable', Rule::exists('travel_packages', 'id')->where('tenant_id', app('currentTenantId'))],
            'status' => ['nullable', Rule::in(['scheduled', 'ongoing', 'completed', 'cancelled'])],
            'note' => ['nullable', 'string'],
        ]);
    }
}
