<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Role::withCount('users')->with('permissions:id,key')->orderBy('is_system', 'desc')->orderBy('name_ar')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'key' => $r->key,
                    'name_ar' => $r->name_ar,
                    'is_system' => $r->is_system,
                    'users_count' => $r->users_count,
                    'permissions' => $r->permissions->pluck('key'),
                ])
        );
    }

    /** All permissions grouped by module (for the role editor). */
    public function permissions(): JsonResponse
    {
        $tenant = app('currentTenant');
        $enabled = array_flip($tenant->enabledModuleKeys());

        $perms = Permission::orderBy('group_key')->orderBy('id')->get()
            ->filter(fn (Permission $p) => $p->group_key === null || $p->group_key === 'core' || isset($enabled[$p->group_key]))
            ->groupBy(fn (Permission $p) => $p->group_key ?? 'core')
            ->map(fn ($items, $group) => ['group' => $group, 'items' => $items->values()])
            ->values();

        return response()->json($perms);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:80'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        $role = Role::create(['name_ar' => $data['name_ar'], 'key' => $this->uniqueKey($data['name_ar'])]);
        $role->permissions()->sync(Permission::whereIn('key', $data['permissions'] ?? [])->pluck('id'));

        return response()->json($role->load('permissions:id,key'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'name_ar' => ['sometimes', 'string', 'max:80'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        if (isset($data['name_ar'])) {
            $role->update(['name_ar' => $data['name_ar']]);
        }
        if (array_key_exists('permissions', $data) && $role->key !== 'admin') {
            $role->permissions()->sync(Permission::whereIn('key', $data['permissions'])->pluck('id'));
        }

        return response()->json($role->fresh()->load('permissions:id,key'));
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        if ($role->is_system) {
            return response()->json(['message' => 'لا يمكن حذف دور نظامي.'], 422);
        }
        $role->delete();

        return response()->json(['ok' => true]);
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $key = $base;
        $i = 1;
        while (Role::where('key', $key)->exists()) {
            $key = $base.'_'.(++$i);
        }

        return $key;
    }
}
