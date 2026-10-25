<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\StockLevel;
use App\Services\BatchService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Add-on «الدفعات وتواريخ الصلاحية»: expiry board, batches, write-off, opening batches. */
class BatchController extends Controller
{
    private function open() { return ItemBatch::where('qty_left', '>', 0); }

    public function summary(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $today = today()->toDateString();
        $value = fn ($q) => (float) $q->selectRaw('coalesce(sum(qty_left * coalesce(unit_cost, 0)), 0) v')->value('v');
        $expired = $this->open()->whereNotNull('expires_on')->where('expires_on', '<', $today);
        $soon = $this->open()->whereBetween('expires_on', [$today, today()->addDays($days)->toDateString()]);

        return response()->json([
            'days' => $days,
            'expired' => (clone $expired)->count(), 'expired_value' => $value(clone $expired),
            'soon' => (clone $soon)->count(), 'soon_value' => $value(clone $soon),
            'week' => $this->open()->whereBetween('expires_on', [$today, today()->addDays(7)->toDateString()])->count(),
            'tracked_items' => Item::where('track_expiry', true)->count(),
            'undated' => $this->open()->whereNull('expires_on')->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $today = today()->toDateString();
        $q = ItemBatch::with('item:id,name,sku,unit_id', 'item.unit:id,symbol', 'warehouse:id,name');
        $scope = $request->query('scope', 'soon');
        match ($scope) {
            'expired' => $q->where('qty_left', '>', 0)->whereNotNull('expires_on')->where('expires_on', '<', $today),
            'soon' => $q->where('qty_left', '>', 0)->whereBetween('expires_on', [$today, today()->addDays((int) $request->query('days', 30))->toDateString()]),
            'undated' => $q->where('qty_left', '>', 0)->whereNull('expires_on'),
            'finished' => $q->where('qty_left', '<=', 0),
            default => $q->where('qty_left', '>', 0),
        };
        if ($i = $request->query('item_id')) {
            $q->where('item_id', $i);
        }
        if ($w = $request->query('warehouse_id')) {
            $q->where('warehouse_id', $w);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($x) => $x->where('batch_no', 'like', "%$s%")->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', 'like', "%$s%")));
        }
        $q->orderByRaw('expires_on is null')->orderBy('expires_on')->orderBy('id');

        return response()->json($q->paginate(30));
    }

    /** Tracked items with stock not yet in any batch (from before tracking started). */
    public function untracked(): JsonResponse
    {
        $rows = StockLevel::with('item:id,name,sku', 'warehouse:id,name')->where('qty', '>', 0)
            ->whereHas('item', fn ($i) => $i->where('track_expiry', true))->get()
            ->map(function ($l) {
                $inBatches = (float) ItemBatch::where('item_id', $l->item_id)->where('warehouse_id', $l->warehouse_id)->sum('qty_left');

                return ['item' => $l->item, 'warehouse' => $l->warehouse, 'item_id' => $l->item_id, 'warehouse_id' => $l->warehouse_id, 'stock' => (float) $l->qty, 'untracked' => round((float) $l->qty - $inBatches, 3)];
            })->filter(fn ($r) => $r['untracked'] > 0.0005)->values();

        return response()->json($rows);
    }

    /** Give existing stock (no batch yet) an expiry date — no stock movement. */
    public function assign(Request $request, BatchService $svc): JsonResponse
    {
        $tid = app('currentTenantId');
        $d = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $tid)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tid)],
            'qty' => ['required', 'numeric', 'gt:0'],
            'expires_on' => ['required', 'date'],
            'batch_no' => ['nullable', 'string', 'max:60'],
        ]);
        $item = Item::findOrFail($d['item_id']);
        $stock = (float) StockLevel::where('item_id', $item->id)->where('warehouse_id', $d['warehouse_id'])->value('qty');
        $free = $stock - (float) ItemBatch::where('item_id', $item->id)->where('warehouse_id', $d['warehouse_id'])->sum('qty_left');
        abort_if((float) $d['qty'] > $free + 0.0005, 422, 'الكمية أكبر من المخزون غير المسجّل في دفعات (المتاح '.round($free, 3).').');
        $b = $svc->create($item, (int) $d['warehouse_id'], (float) $d['qty'], $d['batch_no'] ?? null, $d['expires_on'], $item->cost_price, 'opening', null, 'opening', 'رصيد موجود قبل تتبع الصلاحية');

        return response()->json($b, 201);
    }

    /** Edit a batch's number / expiry (typo on receiving). */
    public function update(Request $request, int $id): JsonResponse
    {
        $b = ItemBatch::findOrFail($id);
        $b->update($request->validate(['batch_no' => ['nullable', 'string', 'max:60'], 'expires_on' => ['nullable', 'date']]));

        return response()->json($b->fresh('item:id,name', 'warehouse:id,name'));
    }

    /** Destroy expired / damaged goods: stock out of that batch + loss in the books. */
    public function writeOff(Request $request, int $id, InventoryService $inv): JsonResponse
    {
        $b = ItemBatch::with('item')->findOrFail($id);
        $d = $request->validate(['qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:255']]);
        abort_if((float) $d['qty'] > (float) $b->qty_left + 0.0005, 422, 'الكمية أكبر من المتبقي في الدفعة.');
        DB::transaction(function () use ($b, $d, $inv) {
            $cost = (float) ($b->unit_cost ?? $b->item->cost_price ?? 0);
            $inv->move($b->item, $b->warehouse_id, -(float) $d['qty'], 'damage', ['ref_type' => 'batch_writeoff', 'ref_id' => $b->id, 'batch_id' => $b->id, 'unit_cost' => $cost,
                'note' => 'إتلاف'.($b->batch_no ? ' دفعة '.$b->batch_no : '').($d['reason'] ? ' — '.$d['reason'] : ''), 'allow_negative' => true]);
            $value = round((float) $d['qty'] * $cost, 3);
            if ($value > 0) {
                app(LedgerService::class)->post([['account' => 'other_exp', 'debit' => $value, 'description' => 'تالف / منتهي الصلاحية'], ['account' => 'inventory', 'credit' => $value]],
                    ['date' => now()->toDateString(), 'description' => 'إتلاف '.$b->item->name.($b->batch_no ? ' — دفعة '.$b->batch_no : ''), 'reference' => 'BATCH-'.$b->id, 'source_type' => 'batch_writeoff', 'source_id' => $b->id]);
            }
        });

        return response()->json($b->fresh('item:id,name', 'warehouse:id,name'));
    }
}
