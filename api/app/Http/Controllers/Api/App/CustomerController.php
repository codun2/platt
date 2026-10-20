<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Customer::withCount('sales')->withSum('sales as sales_total', 'total')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }

        return response()->json($request->boolean('all') ? $q->get() : $q->paginate(50));
    }

    public function show(int $id): JsonResponse
    {
        $c = Customer::findOrFail($id);
        $sales = $c->sales()->orderByDesc('id')->limit(50)->get(['id', 'number', 'date', 'status', 'total', 'paid', 'payment_method']);
        $due = (float) $c->sales()->where('status', 'completed')->selectRaw('coalesce(sum(total - paid),0) as d')->value('d');

        return response()->json(['customer' => $c, 'sales' => $sales, 'balance_due' => $due]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Customer::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $c = Customer::findOrFail($id);
        $c->update($this->validated($request));

        return response()->json($c->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $c = Customer::withCount('sales')->findOrFail($id);
        if ($c->sales_count > 0) {
            $c->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $c->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
