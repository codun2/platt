<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduStudent;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = now()->toDateString();
        $active = EduStudent::where('status', 'active');
        $marks = EduAttendance::where('date', $today)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $marked = (int) $marks->sum();
        $fees = Invoice::whereNotNull('student_id')->whereNotIn('status', ['cancelled', 'draft']);

        return response()->json([
            'stats' => [
                'students' => (clone $active)->count(), 'classes' => EduClass::where('is_active', true)->count(),
                'present_today' => (int) (($marks['present'] ?? 0) + ($marks['late'] ?? 0)), 'absent_today' => (int) ($marks['absent'] ?? 0), 'marked_today' => $marked,
                'attendance_rate' => $marked ? round((($marks['present'] ?? 0) + ($marks['late'] ?? 0)) * 100 / $marked) : null,
                'fees_billed' => round((float) (clone $fees)->sum('total'), 3), 'fees_paid' => round((float) (clone $fees)->sum('paid'), 3),
                'fees_overdue' => round((float) (clone $fees)->whereIn('status', ['sent', 'partial'])->where('due_date', '<', $today)->sum(DB::raw('total - paid')), 3),
            ],
            'classes' => EduClass::withCount(['students' => fn ($s) => $s->where('status', 'active')])->where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name', 'capacity']),
            'unmarked' => EduClass::where('is_active', true)->whereDoesntHave('students', fn ($s) => $s->whereHas('attendance', fn ($a) => $a->where('date', $today)))
                ->whereHas('students', fn ($s) => $s->where('status', 'active'))->get(['id', 'name']),
        ]);
    }
}
