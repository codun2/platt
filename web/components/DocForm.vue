<script setup lang="ts">
/** Full invoice / quote form (shared by new + edit pages). `kind` switches the few fields that differ. */
const props = defineProps<{ kind: DocKind; form: any; customers: any[] }>()
const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const isInvoice = computed(() => props.kind === 'invoice')
const canPickItems = computed(() => auth.state.value.sidebar.some((i: any) => i.key === 'inventory'))
const totals = computed(() => calcDoc(props.form))
const customer = computed(() => props.customers.find((c: any) => String(c.id) === String(props.form.customer_id)))

// Sector profile: naming, record link, line style, presets.
const { profile: P, link } = useDocProfile()
const docName = computed(() => tt(...(isInvoice.value ? P.value.invoice : P.value.quote)))
const party = computed(() => tt(...P.value.party))
const links = ref<any[]>([])
onMounted(async () => { if (link.value) links.value = await loadDocLinks(link.value.type) })
// Issuing against a case / student / contract / work order / booking fills the party, subject and reference.
watch(() => props.form.link_id, id => {
  const l = links.value.find((x: any) => x.id === id)
  if (!l) { if (!id) props.form.student_id = ''; return }
  if (l.customer_id) props.form.customer_id = l.customer_id
  props.form.reference = l.reference || props.form.reference
  if (!props.form.subject) props.form.subject = l.subject || ''
  props.form.student_id = l.student_id || ''
})

// Invoice: payment terms → due date (only when the user hasn't typed a due date by hand).
watch(() => [props.form.payment_terms_days, props.form.date], () => {
  if (!isInvoice.value || props.form.payment_terms_days === '' || !props.form.date) return
  const d = new Date(props.form.date + 'T00:00:00'); d.setDate(d.getDate() + (+props.form.payment_terms_days || 0))
  props.form.due_date = d.toISOString().slice(0, 10)
})
</script>

<template>
  <div>
    <div class="card-title"><i :class="`ti ${isInvoice ? 'ti-file-invoice' : 'ti-file-dollar'}`" /> {{ tt('بيانات', 'Details:') }} {{ docName }}</div>
    <div class="grid grid-3">
      <div v-if="link" class="field">
        <label>{{ tt(link.label[0], link.label[1]) }}</label>
        <UiSelect v-model="form.link_id" :placeholder="tt('اختياري', 'Optional')"><option value="">—</option><option v-for="l in links" :key="l.id" :value="l.id">{{ l.label }}</option></UiSelect>
        <small class="muted">{{ tt('يملأ', 'Fills') }} {{ party }} {{ tt('والمرجع تلقائياً', '& reference') }}</small>
      </div>
      <div class="field">
        <label>{{ party }}<span v-if="!P.cash" style="color:var(--primary)"> *</span></label>
        <UiSelect v-model="form.customer_id" placeholder="—" :add-label="tt('إضافة', 'Add') + ' ' + party" add-to="/crm/customers/new" add-key="customer">
          <option value="">{{ P.cash ? tt('بدون عميل (نقدي)','No customer (cash)') : '—' }}</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option>
        </UiSelect>
        <small v-if="customer" class="muted">{{ [customer.phone, customer.email, customer.city].filter(Boolean).join(' · ') }}<span v-if="customer.tax_number"> · ض.{{ customer.tax_number }}</span></small>
      </div>
      <div class="field"><label>{{ tt('التاريخ','Date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <template v-if="isInvoice">
        <div class="field"><label>{{ tt('شروط السداد (أيام)','Payment terms (days)') }}</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" :placeholder="tt('مثال: 30','e.g. 30')" /></div>
        <div class="field"><label>{{ tt('تاريخ الاستحقاق','Due date') }}</label><input v-model="form.due_date" type="date" dir="ltr" /></div>
      </template>
      <div v-else class="field"><label>{{ tt('صالح حتى','Valid until') }}</label><input v-model="form.valid_until" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الموضوع','Subject') }}</label><input v-model="form.subject" :placeholder="tt(...P.subject)" /></div>
      <div class="field"><label>{{ tt(...P.ref) }}</label><input v-model="form.reference" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> {{ P.lineMode === 'amount' ? tt('البنود والمبالغ', 'Items & amounts') : tt('البنود','Line items') }}</div>
    <DocLinesEditor :lines="form.lines" :can-pick-items="canPickItems" :mode="P.lineMode" :desc-placeholder="tt(...P.desc)" :unit="P.unit" :presets="P.presets" />

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-discount-2" /> {{ tt('الخصم والضريبة','Discount & tax') }}</div>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('نوع الخصم','Discount type') }}</label><UiSelect v-model="form.discount_type"><option value="amount">{{ tt('مبلغ','Amount') }}</option><option value="percent">{{ tt('نسبة %','Percent %') }}</option></UiSelect></div>
          <div class="field"><label>{{ tt('قيمة الخصم','Discount value') }}</label><input v-model.number="form.discount_value" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('نسبة الضريبة %','Tax rate %') }}</label><input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" dir="ltr" /></div>
        </div>
        <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('الملاحظات','Notes') }}</div>
        <div v-if="!isInvoice" class="field"><label>{{ tt('شروط العرض (تظهر للعميل)','Quote terms (shown to customer)') }}</label><textarea v-model="form.terms" rows="2" :placeholder="tt('مثال: الأسعار شاملة التوصيل، الدفع 50% مقدماً','e.g. prices include delivery, 50% upfront')" /></div>
        <div class="field"><label>{{ tt('ملاحظات تظهر للعميل','Notes (shown to customer)') }}</label><textarea v-model="form.notes" rows="2" /></div>
        <div class="field"><label>{{ tt('ملاحظات داخلية (لا تُطبع)','Internal notes (not printed)') }}</label><textarea v-model="form.internal_notes" rows="2" /></div>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(totals.subtotal) }}</b></div>
        <div class="row between"><span>{{ tt('الخصم','Discount') }}<small v-if="form.discount_type === 'percent'" class="muted"> ({{ form.discount_value || 0 }}%)</small></span><b>- {{ f.money(totals.discount) }}</b></div>
        <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ form.tax_rate || 0 }}%)</span><b>{{ f.money(totals.tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(totals.total, true) }}</b></div>
      </div>
    </div>
  </div>
</template>
