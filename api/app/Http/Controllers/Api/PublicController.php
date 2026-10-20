<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SectorPreset;
use Illuminate\Http\JsonResponse;

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
}
