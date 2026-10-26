<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\SerialUnit;
use App\Models\StockLevel;
use App\Services\SerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Add-on «الأرقام التسلسلية والضمان»: registry, delivery, warranty board, lookup, claims. */
class SerialController extends Controller
{
    private const WITH = ['item:id,name,sku,warranty_months', 'warehouse:id,name', 'customer:id,name,phone'];

    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $today = today()->toDateString();
        $active = SerialUnit::whereIn('status', SerialUnit::OUT);

        return response()->json([
            'days' => $days,
            'total' => SerialUnit::count(),
            'in_stock' => SerialUnit::where('status', 'in_stock')->count(),
            'with_customers' => (clone $active)->count(),
            'under_warranty' => (clone $active)->whereNotNull('warranty_until')->where('warranty_until', '>=', $today)->count(),
            'expiring' => (clone $active)->whereBetween('warranty_until', [$today, today()->addDays($days)->toDateString()])->count(),
            'expired' => (clone $active)->whereNotNull('warranty_until')->where('warranty_until', '<', $today)->count(),
            'faulty' => SerialUnit::where('status', 'faulty')->whereNull('replaced_by_id')->count(),
            'tracked_items' => Item::where('track_serial', true)->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $today = today()->toDateString();
        $q = SerialUnit::with(self::WITH);
        match ($request->query('scope')) {
            'in_stock' => $q->where('status', 'in_stock'),
            'delivered' => $q->whereIn('status', SerialUnit::OUT),
            'under_warranty' => $q->whereIn('status', SerialUnit::OUT)->whereNotNull('warranty_until')->where('warranty_until', '>=', $today),
            'expiring' => $q->whereIn('status', SerialUnit::OUT)->whereBetween('warranty_until', [$today, today()->addDays((int) $request->query('days', 30))->toDateString()]),
            'expired' => $q->whereIn('status', SerialUnit::OUT)->whereNotNull('warranty_until')->where('warranty_until', '<', $today),
            'faulty' => $q->where('status', 'faulty'),
            default => null,
        };
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($i = $request->query('item_id')) {
            $q->where('item_id', $i);
        }
        if ($c = $request->query('customer_id')) {
            $q->where('customer_id', $c);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('serial_no', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('out_ref', 'like', "%$s%")
                ->orWhereHas('customer', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('item', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")));
        }

        return response()->json($q->orderByDesc('id')->paginate(30));
    }

    /** Scan / type a serial → the full unit card with its timeline. */
    public function lookup(Request $request): JsonResponse
    {
        $sn = trim((string) $request->query('serial'));
        $u = SerialUnit::with(array_merge(self::WITH, ['events.user:id,name', 'customer:id,name,phone,email']))->where('serial_no', $sn)->first();
        if (! $u) {
            return response()->json(['found' => false]);
        }
        $today = today();

        return response()->json([
            'found' => true, 'unit' => $u,
            'warranty' => [
                'active' => $u->warranty_until && $u->isOut() && $u->warranty_until->gte($today),
                'days_left' => $u->warranty_until ? $today->diffInDays($u->warranty_until, false) : null,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(SerialUnit::with(array_merge(self::WITH, ['events.user:id,name', 'customer:id,name,phone,email']))->findOrFail($id));
    }

    /** In-stock serial count vs stock qty per serialized item (keep them in sync). */
    public function reconcile(): JsonResponse
    {
        $rows = Item::where('track_serial', true)->where('is_active', true)->get(['id', 'name', 'sku'])->map(function ($it) {
            $stock = (float) StockLevel::where('item_id', $it->id)->sum('qty');
            $serials = SerialUnit::where('item_id', $it->id)->where('status', 'in_stock')->count();

            return ['item' => $it, 'stock' => $stock, 'serials' => $serials, 'missing' => round($stock - $serials, 3)];
        })->filter(fn ($r) => abs($r['missing']) > 0.0005)->values();

        return response()->json($rows);
    }

    public function register(Request $request, SerialService $svc): JsonResponse
    {
        $tid = app('currentTenantId');
        $d = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $tid)->where('track_serial', true)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tid)],
            'serials' => ['required', 'array', 'min:1', 'max:500'],
            'serials.*' => ['required', 'string', 'max:80'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['serials' => 'الأرقام التسلسلية']);
        $item = Item::findOrFail($d['item_id']);
        $r = DB::transaction(fn () => $svc->register($item, (int) $d['warehouse_id'], $d['serials'], ['warranty_months' => $d['warranty_months'] ?? null, 'note' => $d['note'] ?? null]));

        return response()->json($r, 201);
    }

    public function deliver(Request $request, SerialService $svc): JsonResponse
    {
        $tid = app('currentTenantId');
        $d = $request->validate([
            'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', Rule::exists('serial_units', 'id')->where('tenant_id', $tid)],
            'as' => ['required', Rule::in(SerialUnit::OUT)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tid)],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'out_ref' => ['nullable', 'string', 'max:60'],
            'delivered_at' => ['nullable', 'date'], 'warranty_start' => ['nullable', 'date'], 'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'location' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        if (empty($d['customer_id']) && empty($d['customer_name'])) {
            return response()->json(['message' => 'اختر العميل أو اكتب اسمه.', 'errors' => ['customer_name' => ['مطلوب']]], 422);
        }
        DB::transaction(function () use ($d, $svc) {
            foreach (SerialUnit::whereIn('id', $d['ids'])->get() as $u) {
                $svc->deliver($u, $d['as'], $d);
            }
        });

        return response()->json(['ok' => true, 'count' => count($d['ids'])]);
    }

    public function status(Request $request, int $id, SerialService $svc): JsonResponse
    {
        $u = SerialUnit::findOrFail($id);
        $d = $request->validate(['status' => ['required', Rule::in(['in_stock', 'returned', 'faulty', 'scrapped'])], 'note' => ['nullable', 'string', 'max:255']]);
        DB::transaction(fn () => $svc->setStatus($u, $d['status'], $d['note'] ?? null));

        return $this->show($id);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $u = SerialUnit::findOrFail($id);
        $d = $request->validate([
            'warranty_start' => ['nullable', 'date'], 'warranty_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'location' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $u->fill($d);
        $u->warranty_until = app(SerialService::class)->warrantyUntil($u->warranty_start?->toDateString(), $u->warranty_months);
        $u->save();
        \App\Models\SerialEvent::create(['serial_unit_id' => $u->id, 'type' => 'edited', 'from_status' => $u->status, 'to_status' => $u->status, 'note' => 'تعديل الضمان/البيانات', 'user_id' => auth()->id()]);

        return $this->show($id);
    }

    public function replace(Request $request, int $id, SerialService $svc): JsonResponse
    {
        $tid = app('currentTenantId');
        $faulty = SerialUnit::findOrFail($id);
        $d = $request->validate(['replacement_id' => ['required', Rule::exists('serial_units', 'id')->where('tenant_id', $tid)], 'note' => ['nullable', 'string', 'max:255']]);
        $with = SerialUnit::where('item_id', $faulty->item_id)->findOrFail($d['replacement_id']);
        $r = DB::transaction(fn () => $svc->replace($faulty, $with, $d['note'] ?? null));

        return response()->json($r->fresh(self::WITH));
    }
}
