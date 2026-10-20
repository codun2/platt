<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Plan::withCount('tenants')->orderBy('sort')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Plan::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $plan->update($this->validated($request, $plan));

        return response()->json($plan->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = Plan::withCount('tenants')->findOrFail($id);
        if ($plan->tenants_count > 0) {
            return response()->json(['message' => 'لا يمكن حذف خطة مرتبطة بمشتركين.'], 422);
        }
        $plan->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('plans', 'key')->ignore($plan?->id)],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'description_ar' => ['nullable', 'string'],
            'sort' => ['integer'],
            'is_active' => ['boolean'],
        ]);
    }
}
