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
            // ---- page customization ----
            'accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme' => ['nullable', 'in:light,dark'],
            'layout' => ['nullable', 'in:classic,modern,minimal'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'string', 'max:300'],
            'sections' => ['nullable', 'array', 'max:10'],
            'sections.*.key' => ['required', Rule::in(StorefrontService::SECTION_KEYS)],
            'sections.*.on' => ['required', 'boolean'],
        ], [
            'handle.regex' => 'اسم الرابط: حروف إنجليزية صغيرة وأرقام وشرطة فقط (مثال: halawiyat-alsham).',
            'accent.regex' => 'لون غير صالح، استخدم صيغة مثل #ea580c.',
        ]);
        // Keep only known social keys.
        if (isset($d['socials'])) {
            $d['socials'] = array_intersect_key($d['socials'], array_flip(StorefrontService::SOCIAL_KEYS));
        }
        if (isset($d['sections'])) {
            $d['sections'] = StorefrontService::normalizeSections($d['sections']);
        }
        if (! $d['pickup'] && ! $d['delivery']) {
            return response()->json(['message' => 'فعّل الاستلام من المحل أو التوصيل (واحد على الأقل).', 'errors' => ['pickup' => ['مطلوب']]], 422);
        }
        $taken = Tenant::where('id', '!=', $tenant->id)->where(fn ($q) => $q->where('settings->storefront->handle', $d['handle'])->orWhere('slug', $d['handle']))->exists();
        if ($taken) {
            return response()->json(['message' => 'اسم الرابط مستخدم لمتجر آخر، اختر اسماً مختلفاً.', 'errors' => ['handle' => ['مستخدم']]], 422);
        }
        $settings = $tenant->settings ?? [];
        // Merge so upload-set fields (logo) and any untouched keys are preserved.
        $settings['storefront'] = array_merge((array) ($settings['storefront'] ?? []), $d);
        $tenant->update(['settings' => $settings]);

        return $this->show();
    }

    /** Upload / replace the storefront logo (public disk). */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048']]);
        $tenant = app('currentTenant');
        $settings = $tenant->settings ?? [];
        $old = $settings['storefront']['logo'] ?? null;

        $ext = strtolower($request->file('logo')->getClientOriginalExtension() ?: 'png');
        $path = $request->file('logo')->storeAs("storefront/{$tenant->id}", 'logo-'.time().'.'.$ext, 'public');

        $settings['storefront'] = array_merge((array) ($settings['storefront'] ?? []), ['logo' => $path]);
        $tenant->update(['settings' => $settings]);
        if ($old && $old !== $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }

        return response()->json(['logo' => $path, 'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path)]);
    }

    /** Remove the storefront logo. */
    public function deleteLogo(): JsonResponse
    {
        $tenant = app('currentTenant');
        $settings = $tenant->settings ?? [];
        if ($old = ($settings['storefront']['logo'] ?? null)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
        }
        $settings['storefront'] = array_merge((array) ($settings['storefront'] ?? []), ['logo' => null]);
        $tenant->update(['settings' => $settings]);

        return response()->json(['ok' => true]);
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
