<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\EduClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = EduClass::with('teacher:id,name')->withCount(['students' => fn ($s) => $s->where('status', 'active')])->orderBy('sort')->orderBy('name');
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }

        return response()->json($q->get());
    }

    public function teachers(): JsonResponse
    {
        return response()->json(User::where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(EduClass::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $c = EduClass::findOrFail($id);
        $c->update($this->validated($request));

        return response()->json($c);
    }

    public function destroy(int $id): JsonResponse
    {
        $c = EduClass::withCount('students')->findOrFail($id);
        abort_if($c->students_count > 0, 422, 'لا يمكن حذف صف فيه طلاب. انقل الطلاب أولاً أو أوقف الصف.');
        $c->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'name' => 'required|string|max:120', 'grade' => 'nullable|string|max:60', 'section' => 'nullable|string|max:20',
            'stage' => ['nullable', Rule::in(['kindergarten', 'primary', 'middle', 'secondary'])], 'academic_year' => 'nullable|string|max:20',
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $t)], 'room' => 'nullable|string|max:40',
            'capacity' => 'nullable|integer|min:0|max:500', 'annual_fee' => 'nullable|numeric|min:0', 'is_active' => 'boolean', 'sort' => 'nullable|integer',
        ]);
        $d['annual_fee'] = $d['annual_fee'] ?? 0;
        $d['sort'] = $d['sort'] ?? 0;

        return $d;
    }
}
