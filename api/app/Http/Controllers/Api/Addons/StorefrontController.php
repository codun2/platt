<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Add-on «صفحة الطلب أونلاين»: the tenant's side (settings, which items show, stats). */
class StorefrontController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = app('currentTenant');
        $online = Order::where('channel', 'online');

        return response()->json([
            'settings' => StorefrontService::settings($tenant),
            'stats' => [
                'orders_total' => (clone $online)->count(),
                'orders_month' => (clone $online)->where('created_at', '>=', now()->startOfMonth())->count(),
                'orders_new' => (clone $online)->where('status', 'new')->count(),
                'value_month' => (float) (clone $online)->where('created_at', '>=', now()->startOfMonth())->whereNot('status', 'cancelled')->sum('total'),
                'items_shown' => Item::where('is_active', true)->where('show_online', true)->where('sale_price', '>', 0)->count(),
                'items_total' => Item::where('is_active', true)->count(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = app('currentTenant');
        $d = $request->validate([
            'open' => ['required', 'boolean'],
            'handle' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'title' => ['nullable', 'string', 'max:120'],
            'welcome' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'pickup' => ['required', 'boolean'],
            'delivery' => ['required', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'lead_hours' => ['nullable', 'numeric', 'min:0', 'max:720'],
            'hide_out_of_stock' => ['required', 'boolean'],
            'notice' => ['nullable', 'string', 'max:500'],
        ], ['handle.regex' => 'اسم الرابط: حروف إنجليزية صغيرة وأرقام وشرطة فقط (مثال: halawiyat-alsham).']);
        if (! $d['pickup'] && ! $d['delivery']) {
            return response()->json(['message' => 'فعّل الاستلام من المحل أو التوصيل (واحد على الأقل).', 'errors' => ['pickup' => ['مطلوب']]], 422);
        }
        $taken = Tenant::where('id', '!=', $tenant->id)->where(fn ($q) => $q->where('settings->storefront->handle', $d['handle'])->orWhere('slug', $d['handle']))->exists();
        if ($taken) {
            return response()->json(['message' => 'اسم الرابط مستخدم لمتجر آخر، اختر اسماً مختلفاً.', 'errors' => ['handle' => ['مستخدم']]], 422);
        }
        $settings = $tenant->settings ?? [];
        $settings['storefront'] = $d;
        $tenant->update(['settings' => $settings]);

        return $this->show();
    }

    /** Items with their «show on the page» switch. */
    public function items(Request $request): JsonResponse
    {
        $q = Item::with('category:id,name')->where('is_active', true)->select('id', 'name', 'sku', 'category_id', 'sale_price', 'image', 'show_online', 'track_stock')
            ->withSum('stockLevels as stock', 'qty');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', 'like', "%$s%"));
        }
        if ($c = $request->query('category_id')) {
            $q->where('category_id', $c);
        }

        return response()->json($q->orderBy('name')->paginate(50));
    }

    public function toggleItems(Request $request): JsonResponse
    {
        $tid = app('currentTenantId');
        $d = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', Rule::exists('items', 'id')->where('tenant_id', $tid)], 'show' => ['required', 'boolean']]);
        Item::whereIn('id', $d['ids'])->update(['show_online' => $d['show']]);

        return response()->json(['ok' => true]);
    }
}
