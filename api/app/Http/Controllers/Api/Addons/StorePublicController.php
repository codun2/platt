<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\Sequence;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Services\SectorIdentity;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The public order page (no login). Resolves the tenant from the link, then works
 * inside that tenant only. Prices always come from the database, never the browser.
 */
class StorePublicController extends Controller
{
    private function tenant(string $handle): array
    {
        $tenant = StorefrontService::findByHandle($handle);
        abort_unless($tenant && $tenant->isActive() && StorefrontService::enabled($tenant), 404, 'المتجر غير موجود.');
        $s = StorefrontService::settings($tenant);
        app()->instance('currentTenantId', $tenant->id);
        app()->instance('currentTenant', $tenant);

        return [$tenant, $s];
    }

    private function catalog(Tenant $tenant, array $s)
    {
        $wh = app(InventoryService::class)->defaultWarehouseId();
        $items = Item::with('unit:id,symbol,name')->where('is_active', true)->where('show_online', true)->where('sale_price', '>', 0)
            ->select('id', 'name', 'category_id', 'unit_id', 'sale_price', 'image', 'track_stock')
            ->withSum(['stockLevels as stock' => fn ($x) => $x->where('warehouse_id', $wh)], 'qty')->orderBy('name')->get();
        if ($s['hide_out_of_stock']) {
            $items = $items->filter(fn ($i) => ! $i->track_stock || (float) $i->stock > 0)->values();
        }

        return $items;
    }

    public function show(string $handle): JsonResponse
    {
        [$tenant, $s] = $this->tenant($handle);
        $items = $s['open'] ? $this->catalog($tenant, $s) : collect();
        $catIds = $items->pluck('category_id')->filter()->unique();

        return response()->json([
            'store' => [
                'name' => $s['title'], 'welcome' => $s['welcome'], 'notice' => $s['notice'], 'open' => (bool) $s['open'],
                'phone' => $tenant->phone, 'whatsapp' => $s['whatsapp'] ?: $tenant->phone, 'address' => ($tenant->settings ?? [])['address'] ?? null,
                'pickup' => (bool) $s['pickup'], 'delivery' => (bool) $s['delivery'], 'delivery_fee' => (float) $s['delivery_fee'],
                'min_order' => (float) $s['min_order'], 'lead_hours' => (float) $s['lead_hours'],
                'currency' => $tenant->currency, 'tax_rate' => (float) ($tenant->tax_rate ?? 0),
                'accent' => SectorIdentity::publicFor($tenant)['accent'],
            ],
            'categories' => Category::whereIn('id', $catIds)->orderBy('name')->get(['id', 'name']),
            'items' => $items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'category_id' => $i->category_id, 'price' => (float) $i->sale_price,
                'unit' => $i->unit?->symbol ?: $i->unit?->name, 'image' => $i->image ? url('storage/'.$i->image) : null,
                'left' => $i->track_stock ? max(0, (float) $i->stock) : null,
            ])->values(),
        ]);
    }

    public function order(Request $request, string $handle): JsonResponse
    {
        [$tenant, $s] = $this->tenant($handle);
        abort_unless($s['open'], 422, 'المتجر لا يستقبل طلبات حالياً.');
        $ways = array_keys(array_filter(['pickup' => $s['pickup'], 'delivery' => $s['delivery']]));
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'fulfilment' => ['required', Rule::in($ways)],
            'address' => ['nullable', 'required_if:fulfilment,delivery', 'string', 'max:500'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tz' => ['nullable', 'integer', 'between:-840,840'],   // browser getTimezoneOffset(): due times are the shop's wall-clock time
            'lines' => ['required', 'array', 'min:1', 'max:60'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ], ['address.required_if' => 'اكتب عنوان التوصيل.', 'phone.required' => 'رقم الجوال مطلوب لنتواصل معك بخصوص الطلب.']);

        $catalog = $this->catalog($tenant, $s)->keyBy('id');
        $lines = [];
        foreach ($d['lines'] as $l) {
            $item = $catalog[(int) $l['item_id']] ?? null;
            if (! $item) {
                throw ValidationException::withMessages(['lines' => 'أحد الأصناف لم يعد متاحاً، حدّث الصفحة وحاول مرة أخرى.']);
            }
            $lines[] = ['item_id' => $item->id, 'description' => trim($item->name.(! empty($l['note']) ? ' — '.$l['note'] : '')), 'qty' => (float) $l['qty'], 'unit_price' => (float) $item->sale_price];
        }
        $subtotal = array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_price'], $lines));
        if ((float) $s['min_order'] > 0 && $subtotal + 0.0005 < (float) $s['min_order']) {
            throw ValidationException::withMessages(['lines' => 'أقل قيمة للطلب '.(float) $s['min_order'].'.']);
        }
        $wallNow = now()->subMinutes((int) ($d['tz'] ?? 0));
        $earliest = $wallNow->copy()->addMinutes((int) round((float) $s['lead_hours'] * 60))->second(0);
        $due = ! empty($d['due_at']) ? \Carbon\Carbon::parse($d['due_at'], config('app.timezone')) : $earliest;
        if ($due->lt($earliest->copy()->subMinutes(5))) {
            throw ValidationException::withMessages(['due_at' => 'أقرب موعد متاح للتسليم: '.$earliest->format('Y-m-d H:i').'.']);
        }

        // A returning customer (same phone) is linked to their customer file.
        $digits = preg_replace('/\D/', '', $d['phone']);
        $customer = strlen($digits) >= 7 ? Customer::where(fn ($q) => $q->where('phone', 'like', '%'.substr($digits, -9))->orWhere('whatsapp', 'like', '%'.substr($digits, -9)))->first() : null;

        $o = DB::transaction(function () use ($d, $lines, $s, $due, $customer) {
            $o = new Order(['number' => Sequence::next('order', 'ORD'), 'status' => 'new']);
            app(OrderController::class)->fill($o, [
                'customer_id' => $customer?->id, 'customer_name' => $d['name'], 'customer_phone' => $d['phone'], 'channel' => 'online',
                'fulfilment' => $d['fulfilment'], 'due_at' => $due, 'address' => $d['address'] ?? null,
                'delivery_fee' => $d['fulfilment'] === 'delivery' ? (float) $s['delivery_fee'] : 0,
                'notes' => $d['notes'] ?? null, 'lines' => $lines,
            ]);

            return $o;
        });

        return response()->json(['number' => $o->number, 'total' => (float) $o->total, 'due_at' => $o->due_at->format('Y-m-d H:i'), 'fulfilment' => $o->fulfilment], 201);
    }
}
