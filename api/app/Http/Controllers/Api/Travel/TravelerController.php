<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripPayment;
use App\Models\TripTraveler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TravelerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = TripTraveler::with('trip:id,name,departure_date,type', 'package:id,name')->orderByDesc('id');
        if ($t = $request->query('trip_id')) {
            $q->where('trip_id', $t);
        }
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('passport_no', 'like', "%$s%"));
        }
        if ($request->boolean('unpaid')) {
            $q->where('status', '!=', 'cancelled')->whereColumn('paid', '<', 'price');
        }

        return response()->json($q->paginate(50));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(TripTraveler::with('trip:id,name,departure_date,return_date', 'package:id,name', 'payments')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $trip = Trip::findOrFail($data['trip_id']);
        $active = $trip->travelers()->where('status', '!=', 'cancelled')->count();
        if ($trip->capacity > 0 && $active >= $trip->capacity) {
            return response()->json(['message' => 'الرحلة مكتملة العدد ('.$trip->capacity.' مقعد).'], 422);
        }
        $packageId = $data['package_id'] ?? $trip->package_id;
        $data['package_id'] = $data['package_id'] ?? null;
        $data['price'] = $data['price'] ?? ($packageId ? \App\Models\TravelPackage::find($packageId)?->price : null) ?? $trip->price;

        return response()->json(TripTraveler::create($data)->load('trip:id,name', 'package:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = TripTraveler::findOrFail($id);
        $t->update($this->validated($request, $t));

        return response()->json($t->fresh()->load('trip:id,name', 'package:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $t = TripTraveler::findOrFail($id);
        if ((float) $t->paid > 0) {
            $t->update(['status' => 'cancelled']);

            return response()->json(['ok' => true, 'cancelled' => true]);
        }
        $t->delete();

        return response()->json(['ok' => true]);
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $t = TripTraveler::findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'date' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $remaining = (float) $t->price - (float) $t->paid;
        if ($data['amount'] > $remaining + 0.0001) {
            return response()->json(['message' => 'المبلغ أكبر من المتبقي ('.number_format($remaining, 3).').'], 422);
        }
        DB::transaction(function () use ($t, $data, $request) {
            TripPayment::create($data + ['traveler_id' => $t->id, 'date' => $data['date'] ?? now()->toDateString(), 'user_id' => $request->user()->id]);
            $t->update(['paid' => (float) $t->paid + (float) $data['amount'], 'status' => $t->status === 'registered' ? 'confirmed' : $t->status]);
        });

        return response()->json($t->fresh()->load('payments'));
    }

    private function validated(Request $request, ?TripTraveler $t = null): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'trip_id' => [$t ? 'sometimes' : 'required', Rule::exists('trips', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'passport_no' => ['nullable', 'string', 'max:40'],
            'passport_expiry' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date'],
            'package_id' => ['nullable', Rule::exists('travel_packages', 'id')->where('tenant_id', $tenantId)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['registered', 'confirmed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
