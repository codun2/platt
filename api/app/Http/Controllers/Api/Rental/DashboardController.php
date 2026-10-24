<?php

namespace App\Http\Controllers\Api\Rental;

use App\Http\Controllers\Controller;
use App\Models\RentalAsset;
use App\Models\RentalContract;
use App\Models\RentalInstallment;
use App\Models\RentalPayment;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Rental overview + the collection list (installments due / overdue / paid). */
class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = now()->toDateString();
        $assets = RentalAsset::where('type', '!=', 'building');
        $total = (clone $assets)->count();
        $rented = (clone $assets)->where('status', 'rented')->count();
        $overdueQ = RentalInstallment::with('contract:id,number,customer_id,asset_id', 'contract.customer:id,name,phone', 'contract.asset:id,name')
            ->whereIn('status', ['due', 'partial'])->where('due_date', '<', $today)
            ->whereHas('contract', fn ($c) => $c->where('status', 'active'));

        return response()->json([
            'stats' => [
                'assets' => $total, 'rented' => $rented, 'available' => (clone $assets)->where('status', 'available')->count(),
                'occupancy' => $total ? round($rented * 100 / $total) : 0,
                'active_contracts' => RentalContract::where('status', 'active')->count(),
                'overdue' => round((float) (clone $overdueQ)->sum(DB::raw('amount - paid')), 3),
                'collected_month' => round((float) RentalPayment::where('kind', 'rent')->whereBetween('date', [now()->startOfMonth()->toDateString(), $today])->sum('amount'), 3),
                'due_month' => round((float) RentalInstallment::whereIn('status', ['due', 'partial'])->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->whereHas('contract', fn ($c) => $c->where('status', 'active'))->sum(DB::raw('amount - paid')), 3),
                'deposits_held' => round((float) RentalContract::sum('deposit_paid'), 3),
            ],
            'overdue' => $overdueQ->orderBy('due_date')->limit(10)->get(),
            'expiring' => RentalContract::with('customer:id,name', 'asset:id,name')->where('status', 'active')
                ->whereBetween('end_date', [$today, now()->addDays(60)->toDateString()])->orderBy('end_date')->limit(10)->get(),
        ]);
    }

    /** Collection list. scope: overdue | month | upcoming | paid | all */
    public function installments(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $q = RentalInstallment::with('contract:id,number,customer_id,asset_id,status', 'contract.customer:id,name,phone', 'contract.asset:id,name,parent_id', 'contract.asset.parent:id,name');
        match ($request->query('scope', 'open')) {
            'overdue' => $q->whereIn('status', ['due', 'partial'])->where('due_date', '<', $today),
            'month' => $q->whereIn('status', ['due', 'partial'])->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]),
            'upcoming' => $q->whereIn('status', ['due', 'partial'])->whereBetween('due_date', [$today, now()->addDays(30)->toDateString()]),
            'paid' => $q->where('status', 'paid'),
            'all' => $q,
            default => $q->whereIn('status', ['due', 'partial']),
        };
        if ($request->query('scope') !== 'paid' && $request->query('scope') !== 'all') {
            $q->whereHas('contract', fn ($c) => $c->where('status', 'active'));
        }
        if ($s = $request->query('q')) {
            $q->whereHas('contract', fn ($c) => $c->where('number', 'like', "%$s%")
                ->orWhereHas('customer', fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('asset', fn ($w) => $w->where('name', 'like', "%$s%")));
        }
        $request->query('scope') === 'paid' ? $q->orderByDesc('paid_at') : $q->orderBy('due_date');

        return response()->json($q->paginate(40));
    }

    public function frequencies(): JsonResponse
    {
        return response()->json(RentalService::FREQUENCIES);
    }
}
