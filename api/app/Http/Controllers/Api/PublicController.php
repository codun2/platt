<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SectorPreset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /** Sectors shown on the sign-up form. */
    public function sectors(): JsonResponse
    {
        $sectors = SectorPreset::where('is_active', true)
            ->orderBy('sort')
            ->withCount('modules')
            ->get(['id', 'key', 'name_ar', 'name_en', 'icon', 'engine', 'description_ar']);

        return response()->json($sectors);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::where('is_active', true)->orderBy('sort')->get());
    }

    /** Curated demo accounts for the one-click quick-login chips (labels only — never passwords). */
    public const DEMO = [
        ['email' => 'demo@rakeez.app', 'ar' => 'متجر تجريبي', 'en' => 'Demo store', 'icon' => 'ti-shopping-cart'],
        ['email' => 'retail@test.app', 'ar' => 'متاجر ونقاط بيع', 'en' => 'Retail & POS', 'icon' => 'ti-building-store'],
        ['email' => 'solar@test.app', 'ar' => 'طاقة شمسية', 'en' => 'Solar energy', 'icon' => 'ti-solar-panel-2'],
        ['email' => 'pharmacy@test.app', 'ar' => 'صيدلية', 'en' => 'Pharmacy', 'icon' => 'ti-pill'],
        ['email' => 'school@test.app', 'ar' => 'مدرسة', 'en' => 'School', 'icon' => 'ti-school'],
        ['email' => 'auto_garage@test.app', 'ar' => 'ورشة سيارات', 'en' => 'Auto garage', 'icon' => 'ti-tool'],
        ['email' => 'real_estate@test.app', 'ar' => 'عقارات', 'en' => 'Real estate', 'icon' => 'ti-building-skyscraper'],
        ['email' => 'hotel@test.app', 'ar' => 'فندق', 'en' => 'Hotel', 'icon' => 'ti-bed'],
    ];

    private static function quickEnabled(): bool
    {
        // On by default while demo tenants exist; set QUICK_LOGINS=false to hide on a real production login.
        return filter_var(env('QUICK_LOGINS', env('SEED_DEMO', true)), FILTER_VALIDATE_BOOLEAN);
    }

    /** Chips to show under the login form (only accounts that actually exist). */
    public function quickLogins(): JsonResponse
    {
        if (! self::quickEnabled()) {
            return response()->json([]);
        }
        $exist = User::withoutGlobalScopes()->whereIn('email', array_column(self::DEMO, 'email'))->where('is_active', true)->pluck('email')->flip();

        return response()->json(array_values(array_map(
            fn ($d) => ['email' => $d['email'], 'name_ar' => $d['ar'], 'name_en' => $d['en'], 'icon' => $d['icon']],
            array_filter(self::DEMO, fn ($d) => isset($exist[$d['email']]))
        )));
    }

    /** One-click login for a demo account — allowlisted, no password crosses the wire. */
    public function demoLogin(Request $request, \App\Http\Controllers\Api\AuthController $auth): JsonResponse
    {
        abort_unless(self::quickEnabled(), 404);
        $email = (string) $request->input('email');
        abort_unless(in_array($email, array_column(self::DEMO, 'email'), true), 404, 'حساب غير متاح.');
        $user = User::withoutGlobalScopes()->where('email', $email)->where('is_active', true)->whereNotNull('tenant_id')->first();
        abort_unless($user, 404, 'حساب غير متاح.');
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['token' => $user->createToken('spa')->plainTextToken] + $auth->publicPayload($user));
    }
}
