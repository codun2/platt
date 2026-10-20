<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Module::orderBy('sort')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $module = Module::create($data);
        $this->ensureViewPermission($module);

        return response()->json($module, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $module = Module::findOrFail($id);
        $module->update($this->validated($request, $module));
        $this->ensureViewPermission($module);

        return response()->json($module->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $module = Module::findOrFail($id);
        if ($module->is_core) {
            return response()->json(['message' => 'لا يمكن حذف وحدة أساسية.'], 422);
        }
        Module::where('parent_key', $module->key)->delete();
        $module->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Module $module = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('modules', 'key')->ignore($module?->id)],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:60'],
            'route' => ['nullable', 'string', 'max:120'],
            'parent_key' => ['nullable', 'string', 'exists:modules,key'],
            'engine' => ['required', 'string', 'max:40'],
            'sort' => ['integer'],
            'is_core' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function ensureViewPermission(Module $module): void
    {
        Permission::firstOrCreate(
            ['key' => $module->viewPermissionKey()],
            ['name_ar' => 'عرض: '.$module->name_ar, 'group_key' => $module->parent_key ?: $module->key],
        );
    }
}
