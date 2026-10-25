<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Add-on «الولاء والمحفظة»: members, statement, top-up / refund / adjust, settings. */
class LoyaltyController extends Controller
{
    public function summary(): JsonResponse
    {
        $settings = LoyaltyService::settings();
        $points = (float) Customer::sum('points');
        $month = LoyaltyTransaction::where('created_at', '>=', now()->startOfMonth())->where('reversed', false);

        return response()->json([
            'settings' => $settings,
            'members' => Customer::where(fn ($q) => $q->where('points', '>', 0)->orWhere('wallet_balance', '>', 0))->count(),
            'points_outstanding' => $points,
            'points_value' => round($points * (float) $settings['point_value'], 3),
            'wallet_total' => (float) Customer::sum('wallet_balance'),
            'month_earned' => (float) (clone $month)->where('type', 'earn')->sum('points'),
            'month_redeemed' => -(float) (clone $month)->where('type', 'redeem')->sum('points'),
            'month_topups' => (float) (clone $month)->where('type', 'topup')->sum('amount'),
        ]);
    }

    /** Customers with their points and wallet (search by name / phone). */
    public function members(Request $request): JsonResponse
    {
        $q = Customer::query()->select('id', 'name', 'phone', 'points', 'wallet_balance', 'is_active')
            ->withMax('loyaltyTransactions as last_activity', 'created_at');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }
        match ($request->query('filter')) {
            'points' => $q->where('points', '>', 0),
            'wallet' => $q->where('wallet_balance', '>', 0),
            default => null,
        };
        $sort = $request->query('sort') === 'wallet' ? 'wallet_balance' : 'points';

        return response()->json($q->orderByDesc($sort)->orderBy('name')->paginate(25));
    }

    /** One customer's balances and statement. */
    public function member(int $id): JsonResponse
    {
        $c = Customer::select('id', 'name', 'phone', 'email', 'points', 'wallet_balance')->findOrFail($id);
        $s = LoyaltyService::settings();

        return response()->json([
            'customer' => $c, 'settings' => $s,
            'points_value' => round((float) $c->points * (float) $s['point_value'], 3),
            'transactions' => LoyaltyTransaction::with('user:id,name')->where('customer_id', $id)->orderByDesc('id')->paginate(25),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $q = LoyaltyTransaction::with('customer:id,name,phone', 'user:id,name')->orderByDesc('id');
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return response()->json($q->paginate(30));
    }

    /** Wallet top-up (money received) or refund (money paid back). */
    public function wallet(Request $request, int $id, LoyaltyService $svc): JsonResponse
    {
        if (! LoyaltyService::settings()['wallet_enabled']) {
            return response()->json(['message' => 'المحفظة غير مفعّلة في إعدادات الولاء.'], 422);
        }
        $c = Customer::findOrFail($id);
        $data = $request->validate([
            'action' => ['required', Rule::in(['topup', 'refund'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $t = DB::transaction(fn () => $data['action'] === 'topup'
            ? $svc->topUp($c, (float) $data['amount'], $data['method'], $data['note'] ?? null)
            : $svc->refund($c, (float) $data['amount'], $data['method'], $data['note'] ?? null));

        return response()->json($t->fresh('customer:id,name,points,wallet_balance'), 201);
    }

    /** Manual points correction (gift / correction / expiry) with a reason. */
    public function adjust(Request $request, int $id, LoyaltyService $svc): JsonResponse
    {
        $c = Customer::findOrFail($id);
        $data = $request->validate([
            'points' => ['required', 'numeric', 'not_in:0', 'between:-10000000,10000000'],
            'note' => ['required', 'string', 'max:255'],
        ], ['note.required' => 'اكتب سبب تعديل النقاط.']);
        $t = DB::transaction(fn () => $svc->record($c, 'adjust', (float) $data['points'], 0, ['note' => $data['note']]));

        return response()->json($t->fresh('customer:id,name,points,wallet_balance'), 201);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'earn_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'point_value' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_redeem' => ['required', 'numeric', 'min:0'],
            'max_redeem_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'wallet_enabled' => ['required', 'boolean'],
        ]);
        $tenant = app('currentTenant');
        $settings = $tenant->settings ?? [];
        $settings['loyalty'] = $data;
        $tenant->update(['settings' => $settings]);

        return response()->json(LoyaltyService::settings($tenant->fresh()));
    }
}
