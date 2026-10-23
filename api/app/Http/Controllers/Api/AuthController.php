<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SidebarBuilder;
use App\Services\TenantRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScopes()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'هذا الحساب معطّل.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json(['token' => $token] + $this->payload($user));
    }

    public function register(Request $request, TenantRegistrar $registrar): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'sector' => ['required', 'string', 'exists:sector_presets,key'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $result = $registrar->register($data);
        $user = $result['user'];
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json(['token' => $token] + $this->payload($user), 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->fill($data)->save();

        return response()->json($this->payload($user->fresh()));
    }

    /** Common auth payload: user + tenant + permissions + dynamic sidebar. */
    private function payload(User $user): array
    {
        $tenant = $user->tenant_id ? $user->tenant()->with('sectorPreset:id,key,name_ar,name_en,icon', 'plan:id,key,name_ar')->first() : null;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_super_admin' => $user->is_super_admin,
                'is_owner' => $user->is_owner,
                'roles' => $user->tenant_id ? $user->roles()->pluck('name_ar') : [],
            ],
            'tenant' => $tenant,
            'identity' => \App\Services\SectorIdentity::publicFor($tenant),
            'permissions' => $user->permissionKeys(),
            'sidebar' => $tenant ? app(SidebarBuilder::class)->forUser($user, $tenant) : [],
        ];
    }
}
