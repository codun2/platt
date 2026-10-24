<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EduAttendance;
use App\Models\EduStudent;
use App\Models\Invoice;
use App\Models\Sequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = EduStudent::with('eduClass:id,name', 'guardian:id,name,phone')
            ->withSum(['invoices as billed' => fn ($i) => $i->whereNotIn('status', ['cancelled', 'draft'])], 'total')
            ->withSum(['invoices as paid' => fn ($i) => $i->whereNotIn('status', ['cancelled', 'draft'])], 'paid')
            ->orderBy('name');
        $status = $request->query('status', 'active');
        if ($status && $status !== 'all') {
            $q->where('status', $status);
        }
        if ($c = $request->query('class_id')) {
            $q->where('class_id', $c);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('student_no', 'like', "%$s%")->orWhere('national_id', 'like', "%$s%")
                ->orWhereHas('guardian', fn ($g) => $g->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        if ($request->boolean('owing')) {
            $q->whereHas('invoices', fn ($i) => $i->whereIn('status', ['sent', 'partial']));
        }
        $base = EduStudent::query();

        return response()->json(['page' => $q->paginate(40), 'stats' => [
            'active' => (clone $base)->where('status', 'active')->count(),
            'boys' => (clone $base)->where('status', 'active')->where('gender', 'male')->count(),
            'girls' => (clone $base)->where('status', 'active')->where('gender', 'female')->count(),
            'no_guardian' => (clone $base)->where('status', 'active')->whereNull('guardian_id')->count(),
        ]]);
    }

    public function show(int $id): JsonResponse
    {
        $s = EduStudent::with('eduClass', 'guardian')->findOrFail($id);
        $s->setAttribute('invoices', Invoice::where('student_id', $s->id)->latest('date')->latest('id')->get(['id', 'number', 'date', 'due_date', 'subject', 'total', 'paid', 'status']));
        $from = now()->startOfMonth()->subMonths(2)->toDateString();
        $att = EduAttendance::where('student_id', $s->id)->where('date', '>=', $from)->orderByDesc('date')->get(['date', 'status', 'note']);
        $s->setAttribute('attendance', $att);
        $s->setAttribute('attendance_summary', $att->groupBy('status')->map->count());
        $s->setAttribute('siblings', $s->guardian_id ? EduStudent::with('eduClass:id,name')->where('guardian_id', $s->guardian_id)->where('id', '!=', $s->id)->get(['id', 'name', 'class_id', 'status']) : []);

        return response()->json($s);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $this->validated($request);
        $s = DB::transaction(function () use ($d, $request) {
            $d['guardian_id'] = $d['guardian_id'] ?? $this->quickGuardian($request);
            unset($d['new_guardian']);
            $d['student_no'] = $d['student_no'] ?: Sequence::next('student', 'ST');

            return EduStudent::create($d);
        });

        return response()->json($s, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $s = EduStudent::findOrFail($id);
        $d = $this->validated($request);
        $d['guardian_id'] = $d['guardian_id'] ?? $this->quickGuardian($request) ?? $s->guardian_id;
        unset($d['new_guardian']);
        $d['student_no'] = $d['student_no'] ?: $s->student_no;
        $s->update($d);

        return response()->json($s->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $s = EduStudent::findOrFail($id);
        if (Invoice::where('student_id', $s->id)->exists()) {
            $s->update(['status' => 'withdrawn']);

            return response()->json(['deactivated' => true]);
        }
        $s->delete();

        return response()->json(['ok' => true]);
    }

    /** Guardian typed inline on the student form → CRM customer. */
    private function quickGuardian(Request $request): ?int
    {
        $g = $request->input('new_guardian', []);
        if (empty($g['name'])) {
            return null;
        }
        if (! empty($g['phone']) && ($existing = Customer::where('phone', $g['phone'])->value('id'))) {
            return $existing;   // same parent registering a second child
        }

        return Customer::create(['name' => $g['name'], 'phone' => $g['phone'] ?? null, 'id_number' => $g['id_number'] ?? null, 'customer_type' => 'individual', 'category' => 'guardian', 'is_active' => true])->id;
    }

    private function validated(Request $request): array
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'student_no' => 'nullable|string|max:40', 'name' => 'required|string|max:150', 'name_en' => 'nullable|string|max:150',
            'gender' => ['nullable', Rule::in(['male', 'female'])], 'birth_date' => 'nullable|date', 'national_id' => 'nullable|string|max:40', 'nationality' => 'nullable|string|max:60',
            'class_id' => ['nullable', Rule::exists('edu_classes', 'id')->where('tenant_id', $t)],
            'guardian_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $t)], 'guardian_relation' => ['nullable', Rule::in(['father', 'mother', 'other'])],
            'new_guardian' => 'nullable|array', 'new_guardian.name' => 'nullable|string|max:150', 'new_guardian.phone' => 'nullable|string|max:30', 'new_guardian.id_number' => 'nullable|string|max:40',
            'emergency_phone' => 'nullable|string|max:30', 'enrolled_at' => 'nullable|date', 'status' => ['nullable', Rule::in(['active', 'withdrawn', 'graduated'])],
            'discount_pct' => 'nullable|numeric|min:0|max:100', 'transport' => 'nullable|string|max:60', 'medical_notes' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $d['status'] = $d['status'] ?? 'active';
        $d['discount_pct'] = $d['discount_pct'] ?? 0;
        $d['student_no'] = $d['student_no'] ?? null;

        return $d;
    }
}
