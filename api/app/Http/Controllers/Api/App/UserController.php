<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::with('roles:id,key,name_ar')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }

        return response()->json($q->get());
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('currentTenant');
        $max = $tenant->plan?->max_users ?? 5;
        if (User::count() >= $max) {
            return response()->json(['message' => "وصلت إلى الحد الأقصى للمستخدمين في باقتك ($max)."], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ]);

        $user = User::create($data);
        $user->roles()->sync($data['roles'] ?? []);

        return response()->json($user->load('roles:id,key,name_ar'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $tenant = app('currentTenant');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is_owner) {
            unset($data['is_active']); // the owner can never be deactivated
        }

        $user->fill($data)->save();
        if (array_key_exists('roles', $data) && ! $user->is_owner) {
            $user->roles()->sync($data['roles']);
        }

        return response()->json($user->fresh()->load('roles:id,key,name_ar'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->is_owner || $user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكن حذف هذا المستخدم.'], 422);
        }
        $user->delete();

        return response()->json(['ok' => true]);
    }
}
