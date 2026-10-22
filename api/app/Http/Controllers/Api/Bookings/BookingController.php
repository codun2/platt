<?php

namespace App\Http\Controllers\Api\Bookings;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\BookingPayment;
use App\Models\BookingService;
use App\Models\BookingUnit;
use App\Models\Sequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Booking::with('unit:id,name,color,type', 'customer:id,name,phone')->orderByDesc('start_at');
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($u = $request->query('unit_id')) {
            $q->where('unit_id', $u);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('customer_phone', 'like', "%$s%")
                ->orWhereHas('customer', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        if ($from = $request->query('from')) {
            $q->where('end_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('start_at', '<=', Carbon::parse($to)->endOfDay());
        }
        if ($request->boolean('upcoming')) {
            $q->whereIn('status', ['pending', 'confirmed'])->where('end_at', '>=', now())->reorder('start_at');
        }

        return response()->json($q->paginate(30));
    }

    /** Events for the calendar grid. */
    public function calendar(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->endOfMonth()))->endOfDay();

        $rows = Booking::with('unit:id,name,color', 'customer:id,name')
            ->where('status', '!=', 'cancelled')
            ->where('start_at', '<=', $to)->where('end_at', '>=', $from)
            ->when($request->query('unit_id'), fn ($q, $u) => $q->where('unit_id', $u))
            ->orderBy('start_at')->get();

        return response()->json($rows->map(fn (Booking $b) => [
            'id' => $b->id, 'number' => $b->number, 'status' => $b->status,
            'unit' => $b->unit?->name, 'color' => $b->unit?->color,
            'customer' => $b->customer?->name ?? $b->customer_name,
            'start_at' => $b->start_at->toDateTimeString(), 'end_at' => $b->end_at->toDateTimeString(),
            'total' => (float) $b->total, 'paid' => (float) $b->paid,
        ]));
    }

    /** Availability check used by the form before saving. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate(['unit_id' => 'required|integer', 'start_at' => 'required|date', 'end_at' => 'required|date|after:start_at', 'ignore_id' => 'nullable|integer']);
        $conflicts = $this->conflicts((int) $data['unit_id'], Carbon::parse($data['start_at']), Carbon::parse($data['end_at']), $data['ignore_id'] ?? null);

        return response()->json(['available' => $conflicts->isEmpty(), 'conflicts' => $conflicts]);
    }

    public function show(int $id): JsonResponse
    {
        $b = Booking::with('unit', 'customer', 'lines', 'payments', 'creator:id,name')->findOrFail($id);
        $t = app('currentTenant');

        return response()->json(['booking' => $b, 'company' => ['name' => $t->name, 'phone' => $t->phone, 'email' => $t->email, 'currency' => $t->currency]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $booking = DB::transaction(function () use ($data, $request) {
            $b = new Booking(['number' => Sequence::next('booking', 'BKG'), 'status' => $data['status'] ?? 'pending', 'created_by' => $request->user()->id]);
            $this->fill($b, $data);
            $b->save();
            $this->syncLines($b, $data['lines'] ?? []);
            $this->recalc($b);

            return $b;
        });

        return response()->json($booking->load('unit:id,name,color', 'customer:id,name,phone', 'lines'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $b = Booking::findOrFail($id);
        if (in_array($b->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'لا يمكن تعديل حجز مكتمل أو ملغى.'], 422);
        }
        $data = $this->validated($request, $b);
        DB::transaction(function () use ($b, $data) {
            $this->fill($b, $data);
            $b->save();
            if (array_key_exists('lines', $data)) {
                $b->lines()->delete();
                $this->syncLines($b, $data['lines']);
            }
            $this->recalc($b);
        });

        return response()->json($b->fresh()->load('unit:id,name,color', 'customer:id,name,phone', 'lines'));
    }

    /** confirm | complete | cancel */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $b = Booking::findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'completed', 'cancelled'])], 'deposit_status' => ['nullable', Rule::in(['pending', 'held', 'returned', 'forfeited'])]]);

        if ($data['status'] === 'confirmed' && $b->status === 'pending') {
            $c = $this->conflicts($b->unit_id, $b->start_at, $b->end_at, $b->id, true);
            if ($c->isNotEmpty()) {
                return response()->json(['message' => 'يتعارض مع حجز مؤكد آخر: '.$c->first()['number']], 422);
            }
        }
        $b->update(array_filter(['status' => $data['status'], 'deposit_status' => $data['deposit_status'] ?? null]));

        return response()->json($b->fresh());
    }

    /** Payment, deposit, refund or deposit return. */
    public function pay(Request $request, int $id): JsonResponse
    {
        $b = Booking::findOrFail($id);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['payment', 'deposit', 'refund', 'deposit_return'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($b, $data, $request) {
            BookingPayment::create($data + ['booking_id' => $b->id, 'date' => $data['date'] ?? now()->toDateString(), 'user_id' => $request->user()->id]);
            $amt = (float) $data['amount'];
            match ($data['kind']) {
                'payment' => $b->paid = (float) $b->paid + $amt,
                'refund' => $b->paid = max(0, (float) $b->paid - $amt),
                'deposit' => [$b->deposit_paid = (float) $b->deposit_paid + $amt, $b->deposit_status = 'held'],
                'deposit_return' => [$b->deposit_paid = max(0, (float) $b->deposit_paid - $amt), $b->deposit_status = 'returned'],
            };
            if ($data['kind'] === 'payment' && $b->status === 'pending') {
                $b->status = 'confirmed';   // first payment confirms the booking
            }
            $b->save();
        });

        return response()->json($b->fresh()->load('payments'));
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request, ?Booking $b = null): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'unit_id' => ['required', Rule::exists('booking_units', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'source' => ['nullable', Rule::in(['walk_in', 'phone', 'whatsapp', 'online', 'agent'])],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'special_requests' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string'],
            'period' => ['required', Rule::in(['day', 'night', 'hour'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'qty' => ['nullable', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'event_type' => ['nullable', 'string', 'max:80'],
            'guests' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed'])],
            'lines' => ['nullable', 'array'],
            'lines.*.service_id' => ['nullable', 'integer'],
            'lines.*.name' => ['nullable', 'string', 'max:120'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function fill(Booking $b, array $data): void
    {
        $unit = BookingUnit::findOrFail($data['unit_id']);
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        $conflicts = $this->conflicts($unit->id, $start, $end, $b->id, true);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['start_at' => 'الوحدة محجوزة (مؤكدة) في هذا الوقت: '.$conflicts->first()['number']]);
        }

        $period = $data['period'];
        $qty = (float) ($data['qty'] ?? match ($period) {
            'hour' => max(1, ceil($start->diffInMinutes($end) / 60)),
            default => max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) ?: 1),
        });
        $defaultPrice = match ($period) { 'night' => $unit->price_night, 'hour' => $unit->price_hour, default => $unit->price_day };

        $b->fill([
            'unit_id' => $unit->id,
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'period' => $period, 'start_at' => $start, 'end_at' => $end, 'qty' => $qty,
            'unit_price' => $data['unit_price'] ?? $defaultPrice,
            'discount' => $data['discount'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? app('currentTenant')->tax_rate ?? 0,
            'deposit' => $data['deposit'] ?? $unit->deposit,
            'event_type' => $data['event_type'] ?? null, 'guests' => $data['guests'] ?? null, 'note' => $data['note'] ?? null,
            'customer_email' => $data['customer_email'] ?? null, 'source' => $data['source'] ?? null,
            'adults' => $data['adults'] ?? null, 'children' => $data['children'] ?? null,
            'special_requests' => $data['special_requests'] ?? null, 'internal_note' => $data['internal_note'] ?? null,
        ]);
    }

    /** Guest check-in / check-out stamps (hotels, chalets). */
    public function checkin(Request $request, int $id): JsonResponse
    {
        $b = Booking::findOrFail($id);
        $data = $request->validate(['action' => ['required', Rule::in(['in', 'out', 'clear'])]]);
        if ($b->status === 'cancelled') {
            return response()->json(['message' => 'الحجز ملغى.'], 422);
        }
        match ($data['action']) {
            'in' => $b->fill(['checked_in_at' => now(), 'status' => $b->status === 'pending' ? 'confirmed' : $b->status]),
            'out' => $b->fill(['checked_out_at' => now(), 'status' => 'completed']),
            'clear' => $b->fill(['checked_in_at' => null, 'checked_out_at' => null]),
        };
        $b->save();

        return response()->json($b->fresh());
    }

    private function syncLines(Booking $b, array $lines): void
    {
        foreach ($lines as $l) {
            $svc = ! empty($l['service_id']) ? BookingService::find($l['service_id']) : null;
            $price = (float) $l['price'];
            BookingLine::create(['booking_id' => $b->id, 'service_id' => $svc?->id, 'name' => $l['name'] ?? $svc?->name ?? 'خدمة', 'qty' => $l['qty'], 'price' => $price, 'total' => round($price * (float) $l['qty'], 3)]);
        }
    }

    private function recalc(Booking $b): void
    {
        $services = (float) $b->lines()->sum('total');
        $base = (float) $b->qty * (float) $b->unit_price + $services;
        $taxable = max(0, $base - (float) $b->discount);
        $tax = round($taxable * (float) $b->tax_rate / 100, 3);
        $b->update(['services_total' => $services, 'tax' => $tax, 'total' => round($taxable + $tax, 3)]);
    }

    /** Overlapping bookings on the same unit (confirmed-only when $confirmedOnly). */
    private function conflicts(int $unitId, Carbon $start, Carbon $end, ?int $ignoreId = null, bool $confirmedOnly = false)
    {
        return Booking::where('unit_id', $unitId)
            ->whereIn('status', $confirmedOnly ? ['confirmed'] : ['pending', 'confirmed'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('start_at', '<', $end)->where('end_at', '>', $start)
            ->get(['id', 'number', 'status', 'start_at', 'end_at', 'customer_name'])
            ->map(fn ($x) => ['id' => $x->id, 'number' => $x->number, 'status' => $x->status, 'start_at' => $x->start_at->toDateTimeString(), 'end_at' => $x->end_at->toDateTimeString()]);
    }
}
