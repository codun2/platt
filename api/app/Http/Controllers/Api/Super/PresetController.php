<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\SectorPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SectorPreset::withCount('tenants')->with('modules:id,key,name_ar')->orderBy('sort')->get()
                ->map(fn (SectorPreset $p) => $p->toArray() + ['module_ids' => $p->modules->pluck('id')])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $preset = SectorPreset::create($data);
        $preset->modules()->sync($data['module_ids'] ?? []);

        return response()->json($preset->load('modules:id,key,name_ar'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $preset = SectorPreset::findOrFail($id);
        $data = $this->validated($request, $preset);
        $preset->update($data);
        if (array_key_exists('module_ids', $data)) {
            $preset->modules()->sync($data['module_ids']);
        }

        return response()->json($preset->fresh()->load('modules:id,key,name_ar'));
    }

    public function destroy(int $id): JsonResponse
    {
        $preset = SectorPreset::withCount('tenants')->findOrFail($id);
        if ($preset->tenants_count > 0) {
            return response()->json(['message' => 'لا يمكن حذف باقة مرتبطة بمشتركين.'], 422);
        }
        $preset->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?SectorPreset $preset = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('sector_presets', 'key')->ignore($preset?->id)],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:60'],
            'engine' => ['nullable', 'string', 'max:40'],
            'description_ar' => ['nullable', 'string'],
            'sort' => ['integer'],
            'is_active' => ['boolean'],
            'module_ids' => ['array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
        ]);
    }
}
