<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\ProjectTask;
use App\Models\RentalInstallment;
use App\Models\ServiceOrder;
use App\Models\WaMessage;
use App\Models\WaTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * WhatsApp messages without the Business API: templates with {placeholders},
 * ready-made recipient lists built from the tenant's data, and a log of what
 * was sent (the SPA opens wa.me with the filled text, then logs it here).
 */
class WhatsappController extends Controller
{
    /** Audience → [module required, Arabic name, default template]. */
    private const AUDIENCES = [
        'overdue_invoices' => ['crm', 'فواتير متأخرة السداد', 'مرحباً {name}، نود تذكيركم بأن الفاتورة رقم {number} بمبلغ {amount} كان موعد سدادها {due_date}. نرجو التكرم بالسداد، وشكراً لتعاملكم مع {company}.'],
        'rent_due' => ['rental', 'إيجارات مستحقة', 'مرحباً {name}، نذكّركم باستحقاق دفعة الإيجار بمبلغ {amount} بتاريخ {due_date} عن {asset} (عقد {number}). شكراً لكم — {company}.'],
        'order_ready' => ['service', 'طلبات جاهزة للاستلام', 'مرحباً {name}، طلبكم رقم {number} ({title}) جاهز للاستلام. بانتظاركم — {company}.'],
        'bookings_tomorrow' => ['bookings', 'حجوزات الغد', 'مرحباً {name}، نذكّركم بحجزكم رقم {number} في {unit} غداً {date} الساعة {time}. نسعد بخدمتكم — {company}.'],
        'hearings_soon' => ['projects', 'مواعيد قادمة للموكّلين', 'مرحباً {name}، نذكّركم بموعد {title} في قضيتكم يوم {date} الساعة {time} — {court}. {company}.'],
    ];

    public function audiences(): JsonResponse
    {
        $enabled = array_flip(app('currentTenant')->enabledModuleKeys());
        $out = [];
        foreach (self::AUDIENCES as $key => [$module, $name]) {
            if (isset($enabled[$module])) {
                $out[] = ['key' => $key, 'name' => $name];
            }
        }

        return response()->json($out);
    }

    public function templates(): JsonResponse
    {
        $this->seedDefaults();

        return response()->json(WaTemplate::orderBy('audience')->orderBy('name')->get());
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        return response()->json(WaTemplate::create($this->validatedTemplate($request)), 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $t = WaTemplate::findOrFail($id);
        $t->update($this->validatedTemplate($request));

        return response()->json($t);
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        WaTemplate::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Recipients for an audience, each with the variables its template needs. */
    public function recipients(Request $request): JsonResponse
    {
        $key = $request->validate(['audience' => ['required', Rule::in(array_keys(self::AUDIENCES))]])['audience'];
        $today = now()->toDateString();
        $rows = match ($key) {
            'overdue_invoices' => Invoice::with('customer:id,name,phone')->whereIn('status', ['sent', 'partial'])->whereNotNull('due_date')->where('due_date', '<', $today)
                ->orderBy('due_date')->limit(200)->get()->map(fn ($i) => $this->row($i->customer?->name, $i->customer?->phone, $i->customer_id, "invoice:{$i->id}",
                    ['number' => $i->number, 'amount' => $this->money((float) $i->total - (float) $i->paid), 'due_date' => $i->due_date?->toDateString()])),
            'rent_due' => RentalInstallment::with('contract:id,number,customer_id,asset_id,status', 'contract.customer:id,name,phone', 'contract.asset:id,name')
                ->whereIn('status', ['due', 'partial'])->where('due_date', '<=', now()->addDays(3)->toDateString())->whereHas('contract', fn ($c) => $c->where('status', 'active'))
                ->orderBy('due_date')->limit(200)->get()->map(fn ($i) => $this->row($i->contract?->customer?->name, $i->contract?->customer?->phone, $i->contract?->customer_id, "rent:{$i->id}",
                    ['number' => $i->contract?->number, 'amount' => $this->money((float) $i->amount - (float) $i->paid), 'due_date' => $i->due_date?->toDateString(), 'asset' => $i->contract?->asset?->name])),
            'order_ready' => ServiceOrder::with('customer:id,name,phone')->where('status', 'ready')->orderBy('updated_at')->limit(200)->get()
                ->map(fn ($o) => $this->row($o->customer?->name ?? $o->customer_name, $o->customer?->phone ?? $o->customer_phone, $o->customer_id, "order:{$o->id}", ['number' => $o->number, 'title' => $o->title])),
            'bookings_tomorrow' => Booking::with('customer:id,name,phone', 'unit:id,name')->whereIn('status', ['pending', 'confirmed'])->whereDate('start_at', now()->addDay()->toDateString())->orderBy('start_at')->get()
                ->map(fn ($b) => $this->row($b->customer?->name ?? $b->customer_name, $b->customer?->phone ?? $b->customer_phone, $b->customer_id, "booking:{$b->id}",
                    ['number' => $b->number, 'unit' => $b->unit?->name, 'date' => $b->start_at?->toDateString(), 'time' => $b->start_at?->format('H:i')])),
            'hearings_soon' => ProjectTask::with('project:id,title,court,customer_id', 'project.customer:id,name,phone')->whereIn('status', ['todo', 'doing'])
                ->whereBetween('due_at', [now()->startOfDay(), now()->addDays(3)->endOfDay()])->orderBy('due_at')->get()
                ->map(fn ($t) => $this->row($t->project?->customer?->name, $t->project?->customer?->phone, $t->project?->customer_id, "task:{$t->id}",
                    ['title' => $t->title, 'date' => $t->due_at?->toDateString(), 'time' => $t->due_at?->format('H:i'), 'court' => $t->location ?: $t->project?->court, 'number' => $t->project?->title])),
        };
        // Already messaged today → flagged, so nobody gets the same reminder twice.
        $sent = WaMessage::whereDate('created_at', $today)->whereIn('context', $rows->pluck('context'))->pluck('context')->flip();

        return response()->json($rows->map(fn ($r) => $r + ['sent_today' => isset($sent[$r['context']])])->values());
    }

    public function log(Request $request): JsonResponse
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'phone' => 'required|string|max:30', 'body' => 'required|string', 'recipient' => 'nullable|string|max:150', 'context' => 'nullable|string|max:60',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $t)], 'template_id' => ['nullable', Rule::exists('wa_templates', 'id')->where('tenant_id', $t)],
        ]);

        return response()->json(WaMessage::create($d + ['user_id' => $request->user()->id]), 201);
    }

    public function history(): JsonResponse
    {
        return response()->json(WaMessage::with('user:id,name', 'template:id,name')->latest()->paginate(50));
    }

    /* ------------------------------------------------------------------ */

    private function row(?string $name, ?string $phone, ?int $customerId, string $context, array $vars): array
    {
        return ['name' => $name ?: '—', 'phone' => $phone, 'wa' => $this->normalize($phone), 'customer_id' => $customerId, 'context' => $context,
            'vars' => array_map(fn ($v) => (string) ($v ?? ''), $vars + ['name' => $name ?: '', 'company' => app('currentTenant')->name])];
    }

    /** Local numbers (05xxxxxxxx) → international digits for wa.me. */
    private function normalize(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        $cc = (string) ((app('currentTenant')->settings['wa_country_code'] ?? null) ?: '966');
        if (str_starts_with($d, '00')) {
            return substr($d, 2);
        }
        if (str_starts_with($d, '0')) {
            return $cc.substr($d, 1);
        }
        if (strlen($d) <= 9) {
            return $cc.$d;
        }

        return $d;
    }

    private function money(float $v): string
    {
        return number_format($v, 3).' '.(app('currentTenant')->currency === 'SAR' || ! app('currentTenant')->currency ? 'ريال' : app('currentTenant')->currency);
    }

    private function seedDefaults(): void
    {
        if (WaTemplate::exists()) {
            return;
        }
        $enabled = array_flip(app('currentTenant')->enabledModuleKeys());
        foreach (self::AUDIENCES as $key => [$module, $name, $body]) {
            if (isset($enabled[$module])) {
                WaTemplate::create(['name' => $name, 'audience' => $key, 'body' => $body, 'is_active' => true]);
            }
        }
        WaTemplate::create(['name' => 'رسالة عامة', 'audience' => 'custom', 'body' => 'مرحباً {name}، ', 'is_active' => true]);
    }

    private function validatedTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150', 'audience' => ['required', Rule::in([...array_keys(self::AUDIENCES), 'custom'])],
            'body' => 'required|string|max:2000', 'is_active' => 'boolean',
        ]);
    }
}
