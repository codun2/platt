<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Shift::with('user:id,name', 'warehouse:id,name')->withCount('sales')->withSum('sales as sales_total', 'total')->orderByDesc('id')->paginate(30));
    }

    /** The calling user's open shift (or null). */
    public function current(Request $request): JsonResponse
    {
        $shift = Shift::with('warehouse:id,name')->where('user_id', $request->user()->id)->where('status', 'open')->first();

        return response()->json($shift ? $this->summary($shift) : null);
    }

    public function open(Request $request, InventoryService $inv): JsonResponse
    {
        if (Shift::where('user_id', $request->user()->id)->where('status', 'open')->exists()) {
            return response()->json(['message' => 'لديك وردية مفتوحة بالفعل.'], 422);
        }
        $data = $request->validate(['opening_cash' => ['nullable', 'numeric', 'min:0'], 'warehouse_id' => ['nullable', 'integer']]);
        $shift = Shift::create([
            'user_id' => $request->user()->id,
            'warehouse_id' => $data['warehouse_id'] ?? $inv->defaultWarehouseId(),
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'] ?? 0,
            'status' => 'open',
        ]);

        return response()->json($this->summary($shift->load('warehouse:id,name')), 201);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);
        if ($shift->status !== 'open') {
            return response()->json(['message' => 'الوردية مغلقة مسبقاً.'], 422);
        }
        $data = $request->validate(['closing_cash' => ['required', 'numeric', 'min:0'], 'note' => ['nullable', 'string']]);
        $s = $this->summary($shift);
        $shift->update([
            'status' => 'closed', 'closed_at' => now(),
            'expected_cash' => $s['expected_cash'], 'closing_cash' => $data['closing_cash'], 'note' => $data['note'] ?? null,
        ]);

        return response()->json($this->summary($shift->fresh()->load('warehouse:id,name')));
    }

    public function show(int $id): JsonResponse
    {
        $shift = Shift::with('user:id,name', 'warehouse:id,name')->findOrFail($id);
        $sales = $shift->sales()->with('customer:id,name')->orderByDesc('id')->get(['id', 'number', 'customer_id', 'total', 'paid', 'payment_method', 'status', 'created_at']);

        return response()->json($this->summary($shift) + ['sales' => $sales]);
    }

    private function summary(Shift $shift): array
    {
        $sales = $shift->sales()->where('status', 'completed');
        // Cash in drawer comes from actual payments (a credit sale's deposit counts too).
        $byMethod = \App\Models\SalePayment::whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('method as payment_method, count(*) as n, sum(amount) as amount')->groupBy('method')->get()->keyBy('payment_method');
        $cash = (float) ($byMethod['cash']->amount ?? 0);

        return array_merge($shift->toArray(), [
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('total'),
            'by_method' => $byMethod->values(),
            'expected_cash' => $shift->status === 'closed' ? (float) $shift->expected_cash : round((float) $shift->opening_cash + $cash, 3),
        ]);
    }
}
