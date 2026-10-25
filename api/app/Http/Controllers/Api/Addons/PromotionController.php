<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Add-on «العروض والكوبونات»: manage offers + the checkout preview. */
class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = now();
        $rows = Promotion::orderByDesc('is_active')->orderByDesc('id')->get()->map(fn (Promotion $p) => $p->toArray() + ['state' => $this->state($p, $now)]);
        if ($s = $request->query('state')) {
            $rows = $rows->where('state', $s)->values();
        }

        return response()->json($rows);
    }

    private function state(Promotion $p, $now): string
    {
        if (! $p->is_active) {
            return 'off';
        }
        if ($p->usage_limit && $p->used_count >= $p->usage_limit) {
            return 'used_up';
        }
        if ($p->ends_on && $now->toDateString() > $p->ends_on->toDateString()) {
            return 'expired';
        }
        if ($p->starts_on && $now->toDateString() < $p->starts_on->toDateString()) {
            return 'scheduled';
        }

        return $p->liveAt($now) ? 'live' : 'waiting';   // waiting = outside its days / hours right now
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Promotion::findOrFail($id));
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $tid = app('currentTenantId');
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['percent', 'amount', 'bxgy', 'bill_percent', 'bill_amount'])],
            'applies_to' => ['required', Rule::in(['all', 'categories', 'items'])],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('tenant_id', $tid)],
            'item_ids' => ['nullable', 'array'], 'item_ids.*' => ['integer', Rule::exists('items', 'id')->where('tenant_id', $tid)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'buy_qty' => ['nullable', 'integer', 'min:0', 'max:1000'], 'get_qty' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'min_total' => ['nullable', 'numeric', 'min:0'], 'max_discount' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('promotions', 'code')->where('tenant_id', $tid)->ignore($id)],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'days' => ['nullable', 'array'], 'days.*' => ['integer', 'between:0,6'],
            'time_from' => ['nullable', 'date_format:H:i'], 'time_to' => ['nullable', 'date_format:H:i'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000'],
        ], ['code.regex' => 'رمز الكوبون: حروف إنجليزية وأرقام فقط (مثال: EID25).', 'code.unique' => 'رمز الكوبون مستخدم في عرض آخر.']);

        $bill = in_array($d['type'], ['bill_percent', 'bill_amount'], true);
        if ($d['type'] === 'bxgy' && (($d['buy_qty'] ?? 0) < 1 || ($d['get_qty'] ?? 0) < 1)) {
            throw ValidationException::withMessages(['buy_qty' => 'حدّد عدد القطع التي يشتريها العميل وعدد المجانية.']);
        }
        if ($d['type'] !== 'bxgy' && ! ((float) ($d['value'] ?? 0) > 0)) {
            throw ValidationException::withMessages(['value' => 'حدّد قيمة الخصم.']);
        }
        if (in_array($d['type'], ['percent', 'bill_percent'], true) && (float) $d['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'النسبة لا تتجاوز 100%.']);
        }
        if (! $bill && $d['applies_to'] === 'categories' && empty($d['category_ids'])) {
            throw ValidationException::withMessages(['category_ids' => 'اختر تصنيفاً واحداً على الأقل.']);
        }
        if (! $bill && $d['applies_to'] === 'items' && empty($d['item_ids'])) {
            throw ValidationException::withMessages(['item_ids' => 'اختر صنفاً واحداً على الأقل.']);
        }
        if (! empty($d['time_from']) xor ! empty($d['time_to'])) {
            throw ValidationException::withMessages(['time_to' => 'حدّد بداية الوقت ونهايته معاً.']);
        }

        return $d + [
            'applies_to' => $bill ? 'all' : $d['applies_to'],
            'code' => ! empty($d['code']) ? strtoupper($d['code']) : null,
            'days' => ! empty($d['days']) && count($d['days']) < 7 ? array_values($d['days']) : null,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $d = $this->validated($request);
        $d['code'] = ! empty($d['code']) ? strtoupper($d['code']) : null;

        return response()->json(Promotion::create($d), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = Promotion::findOrFail($id);
        $p->update($this->validated($request, $id));

        return response()->json($p->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        Promotion::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Checkout preview: the same calculation the sale will store. */
    public function evaluate(Request $request, PromotionService $svc): JsonResponse
    {
        $tid = app('currentTenantId');
        $d = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $tid)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($svc->evaluate($d['lines'], $d['coupon_code'] ?? null));
    }
}
