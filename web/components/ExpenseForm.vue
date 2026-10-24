<script setup lang="ts">
/** Expense form (new + edit). */
const props = defineProps<{ form: any; meta: any; suppliers: any[] }>()
const f = useFmt(); const { tt } = useI18n()
const total = computed(() => Math.round(((+props.form.amount || 0) + (+props.form.tax || 0)) * 1000) / 1000)
const supplier = computed(() => props.suppliers.find((s: any) => String(s.id) === String(props.form.supplier_id)))
watch(() => props.form.supplier_id, () => { if (supplier.value && !props.form.payee) props.form.payee = supplier.value.name })
// Default money account by method (cash → الصندوق, otherwise البنك) when nothing picked yet.
watch(() => props.form.payment_method, m => { if (props.form.paid_from_account_id) return; const k = m === 'cash' ? 'cash' : 'bank'; const a = props.meta.money?.find((x: any) => x.system_key === k); if (a) props.form.paid_from_account_id = a.id })
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-receipt" /> {{ tt('بيانات المصروف','Expense details') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('التاريخ','Date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('نوع المصروف (الحساب)','Expense type (account)') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.account_id" :placeholder="tt('اختر','Select')"><option value="">—</option><option v-for="a in meta.categories" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('طريقة الدفع','Payment method') }}</label><UiSelect v-model="form.payment_method"><option v-for="(l, k) in expenseMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('مدفوع من','Paid from') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.paid_from_account_id" :placeholder="tt('الصندوق / البنك','Cash / Bank')"><option value="">—</option><option v-for="a in meta.money" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('المورد (اختياري)','Supplier (optional)') }}</label><UiSelect v-model="form.supplier_id" placeholder="—" :add-label="tt('إضافة مورد','Add supplier')" add-to="/inventory/suppliers/new" add-key="supplier"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('المستفيد / الجهة','Payee') }}</label><input v-model="form.payee" :placeholder="tt('لمن دُفع المبلغ','Who was paid')" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> {{ tt('المبلغ','Amount') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('المبلغ قبل الضريبة','Amount before tax') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.amount" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الضريبة (مدخلات)','Tax (input)') }}</label><input v-model.number="form.tax" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الإجمالي المدفوع','Total paid') }}</label><input :value="f.money(total, true)" disabled dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('المرجع والوصف','Reference & description') }}</div>
    <div class="grid grid-2">
      <div class="field"><label>{{ tt('المرجع (رقم فاتورة/إيصال)','Reference (invoice/receipt)') }}</label><input v-model="form.reference" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الوصف','Description') }}</label><input v-model="form.description" :placeholder="tt('مثال: إيجار شهر أكتوبر','e.g. October rent')" /></div>
    </div>
    <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
  </div>
</template>
