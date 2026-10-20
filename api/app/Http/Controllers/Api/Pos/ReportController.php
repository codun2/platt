<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $base = Sale::where('status', 'completed')->whereBetween('date', [$from, $to]);

        $totals = (clone $base)->selectRaw('count(*) as invoices, coalesce(sum(subtotal),0) as subtotal, coalesce(sum(discount),0) as discount, coalesce(sum(tax),0) as tax, coalesce(sum(total),0) as total, coalesce(sum(paid),0) as paid')->first();

        $cost = (float) SaleItem::whereIn('sale_id', (clone $base)->select('id'))->selectRaw('coalesce(sum(qty*unit_cost),0) as c')->value('c');

        $byDay = (clone $base)->selectRaw('date, count(*) as invoices, sum(total) as total')->groupBy('date')->orderBy('date')->get();
        $byMethod = (clone $base)->selectRaw('payment_method, count(*) as invoices, sum(total) as total, sum(paid) as paid')->groupBy('payment_method')->get();

        $topItems = SaleItem::whereIn('sale_id', (clone $base)->select('id'))
            ->selectRaw('item_id, name, sum(qty) as qty, sum(total) as total, sum(qty*unit_cost) as cost')
            ->groupBy('item_id', 'name')->orderByDesc('total')->limit(15)->get();

        $topCustomers = (clone $base)->whereNotNull('customer_id')
            ->selectRaw('customer_id, count(*) as invoices, sum(total) as total')
            ->groupBy('customer_id')->orderByDesc('total')->limit(10)->with('customer:id,name')->get();

        $cancelled = Sale::where('status', 'cancelled')->whereBetween('date', [$from, $to])->count();

        return response()->json([
            'from' => $from, 'to' => $to,
            'totals' => $totals,
            'cost' => round($cost, 3),
            'gross_profit' => round((float) $totals->subtotal - (float) $totals->discount - $cost, 3),
            'cancelled' => $cancelled,
            'by_day' => $byDay,
            'by_method' => $byMethod,
            'top_items' => $topItems,
            'top_customers' => $topCustomers,
        ]);
    }

    private function range(Request $request): array
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->toDateString();

        return [$from, $to];
    }
}
