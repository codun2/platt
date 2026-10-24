<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\EduAttendance;
use App\Models\EduClass;
use App\Models\EduStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    /** Roll-call sheet for one class on one day. */
    public function sheet(Request $request): JsonResponse
    {
        $d = $request->validate(['class_id' => ['required', Rule::exists('edu_classes', 'id')->where('tenant_id', app('currentTenantId'))], 'date' => 'nullable|date']);
        $date = $d['date'] ?? now()->toDateString();
        $students = EduStudent::where('class_id', $d['class_id'])->where('status', 'active')->orderBy('name')->get(['id', 'name', 'student_no', 'gender']);
        $marks = EduAttendance::where('date', $date)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');
        $from = now()->startOfMonth()->toDateString();
        $absences = EduAttendance::whereIn('student_id', $students->pluck('id'))->where('date', '>=', $from)->where('status', 'absent')
            ->selectRaw('student_id, count(*) n')->groupBy('student_id')->pluck('n', 'student_id');

        return response()->json([
            'class' => EduClass::find($d['class_id'], ['id', 'name']), 'date' => $date, 'saved' => $marks->isNotEmpty(),
            'rows' => $students->map(fn ($s) => [
                'student_id' => $s->id, 'name' => $s->name, 'student_no' => $s->student_no,
                'status' => $marks[$s->id]->status ?? null, 'note' => $marks[$s->id]->note ?? null, 'absent_month' => (int) ($absences[$s->id] ?? 0),
            ]),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'class_id' => ['required', Rule::exists('edu_classes', 'id')->where('tenant_id', $t)], 'date' => 'required|date',
            'rows' => 'required|array', 'rows.*.student_id' => ['required', Rule::exists('edu_students', 'id')->where('tenant_id', $t)],
            'rows.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])], 'rows.*.note' => 'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($d, $request) {
            foreach ($d['rows'] as $r) {
                EduAttendance::updateOrCreate(['student_id' => $r['student_id'], 'date' => $d['date']], ['class_id' => $d['class_id'], 'status' => $r['status'], 'note' => $r['note'] ?? null, 'user_id' => $request->user()->id]);
            }
        });

        return response()->json(['ok' => true, 'saved' => count($d['rows'])]);
    }

    /** Monthly summary per class (for the attendance page header). */
    public function report(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $rows = EduAttendance::whereBetween('date', [$from, $to])->selectRaw('class_id, status, count(*) n')->groupBy('class_id', 'status')->get();
        $today = EduAttendance::where('date', now()->toDateString())->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return response()->json(['from' => $from, 'to' => $to, 'rows' => $rows, 'today' => $today]);
    }
}
