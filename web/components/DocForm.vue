<script setup lang="ts">
/** Full invoice / quote form (shared by new + edit pages). `kind` switches the few fields that differ. */
const props = defineProps<{ kind: DocKind; form: any; customers: any[] }>()
const auth = useAuth(); const f = useFmt()
const isInvoice = computed(() => props.kind === 'invoice')
const canPickItems = computed(() => auth.state.value.sidebar.some((i: any) => i.key === 'inventory'))
const totals = computed(() => calcDoc(props.form))
const customer = computed(() => props.customers.find((c: any) => String(c.id) === String(props.form.customer_id)))

// Invoice: payment terms → due date (only when the user hasn't typed a due date by hand).
watch(() => [props.form.payment_terms_days, props.form.date], () => {
  if (!isInvoice.value || props.form.payment_terms_days === '' || !props.form.date) return
  const d = new Date(props.form.date + 'T00:00:00'); d.setDate(d.getDate() + (+props.form.payment_terms_days || 0))
  props.form.due_date = d.toISOString().slice(0, 10)
})
</script>

<template>
  <div>
    <div class="card-title"><i :class="`ti ${isInvoice ? 'ti-file-invoice' : 'ti-file-dollar'}`" /> {{ isInvoice ? 'بيانات الفاتورة' : 'بيانات العرض' }}</div>
    <div class="grid grid-3">
      <div class="field">
        <label>العميل</label>
        <UiSelect v-model="form.customer_id" placeholder="بدون عميل (نقدي)">
          <option value="">بدون عميل (نقدي)</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option>
        </UiSelect>
        <small v-if="customer" class="muted">{{ [customer.phone, customer.email, customer.city].filter(Boolean).join(' · ') }}<span v-if="customer.tax_number"> · ض.{{ customer.tax_number }}</span></small>
      </div>
      <div class="field"><label>التاريخ <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <template v-if="isInvoice">
        <div class="field"><label>شروط السداد (أيام)</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" placeholder="مثال: 30" /></div>
        <div class="field"><label>تاريخ الاستحقاق</label><input v-model="form.due_date" type="date" dir="ltr" /></div>
      </template>
      <div v-else class="field"><label>صالح حتى</label><input v-model="form.valid_until" type="date" dir="ltr" /></div>
      <div class="field"><label>الموضوع</label><input v-model="form.subject" :placeholder="isInvoice ? 'مثال: أعمال صيانة شهر أكتوبر' : 'مثال: عرض توريد وتركيب'" /></div>
      <div class="field"><label>{{ isInvoice ? 'رقم أمر الشراء / المرجع' : 'مرجع العميل' }}</label><input v-model="form.reference" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> البنود</div>
    <DocLinesEditor :lines="form.lines" :can-pick-items="canPickItems" />

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-discount-2" /> الخصم والضريبة</div>
        <div class="grid grid-3">
          <div class="field"><label>نوع الخصم</label><UiSelect v-model="form.discount_type"><option value="amount">مبلغ</option><option value="percent">نسبة %</option></UiSelect></div>
          <div class="field"><label>قيمة الخصم</label><input v-model.number="form.discount_value" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>نسبة الضريبة %</label><input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" dir="ltr" /></div>
        </div>
        <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> الملاحظات</div>
        <div v-if="!isInvoice" class="field"><label>شروط العرض (تظهر للعميل)</label><textarea v-model="form.terms" rows="2" placeholder="مثال: الأسعار شاملة التوصيل، الدفع 50% مقدماً" /></div>
        <div class="field"><label>ملاحظات تظهر للعميل</label><textarea v-model="form.notes" rows="2" /></div>
        <div class="field"><label>ملاحظات داخلية (لا تُطبع)</label><textarea v-model="form.internal_notes" rows="2" /></div>
      </div>
      <div class="totals-box">
        <div class="row between"><span>المجموع</span><b>{{ f.money(totals.subtotal) }}</b></div>
        <div class="row between"><span>الخصم<small v-if="form.discount_type === 'percent'" class="muted"> ({{ form.discount_value || 0 }}%)</small></span><b>- {{ f.money(totals.discount) }}</b></div>
        <div class="row between"><span>الضريبة ({{ form.tax_rate || 0 }}%)</span><b>{{ f.money(totals.tax) }}</b></div>
        <div class="row between total"><span>الإجمالي</span><b>{{ f.money(totals.total, true) }}</b></div>
      </div>
    </div>
  </div>
</template>
