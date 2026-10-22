<script setup lang="ts">
/** Expense form (new + edit). */
const props = defineProps<{ form: any; meta: any; suppliers: any[] }>()
const f = useFmt()
const total = computed(() => Math.round(((+props.form.amount || 0) + (+props.form.tax || 0)) * 1000) / 1000)
const supplier = computed(() => props.suppliers.find((s: any) => String(s.id) === String(props.form.supplier_id)))
watch(() => props.form.supplier_id, () => { if (supplier.value && !props.form.payee) props.form.payee = supplier.value.name })
// Default money account by method (cash → الصندوق, otherwise البنك) when nothing picked yet.
watch(() => props.form.payment_method, m => { if (props.form.paid_from_account_id) return; const k = m === 'cash' ? 'cash' : 'bank'; const a = props.meta.money?.find((x: any) => x.system_key === k); if (a) props.form.paid_from_account_id = a.id })
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-receipt" /> بيانات المصروف</div>
    <div class="grid grid-3">
      <div class="field"><label>التاريخ <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <div class="field"><label>نوع المصروف (الحساب) <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.account_id" placeholder="اختر"><option value="">—</option><option v-for="a in meta.categories" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></div>
      <div class="field"><label>طريقة الدفع</label><UiSelect v-model="form.payment_method"><option v-for="(l, k) in expenseMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>مدفوع من <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.paid_from_account_id" placeholder="الصندوق / البنك"><option value="">—</option><option v-for="a in meta.money" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></div>
      <div class="field"><label>المورد (اختياري)</label><UiSelect v-model="form.supplier_id" placeholder="—"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect></div>
      <div class="field"><label>المستفيد / الجهة</label><input v-model="form.payee" placeholder="لمن دُفع المبلغ" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> المبلغ</div>
    <div class="grid grid-3">
      <div class="field"><label>المبلغ قبل الضريبة <span style="color:var(--primary)">*</span></label><input v-model.number="form.amount" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>الضريبة (مدخلات)</label><input v-model.number="form.tax" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>الإجمالي المدفوع</label><input :value="f.money(total, true)" disabled dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> المرجع والوصف</div>
    <div class="grid grid-2">
      <div class="field"><label>المرجع (رقم فاتورة/إيصال)</label><input v-model="form.reference" dir="ltr" /></div>
      <div class="field"><label>الوصف</label><input v-model="form.description" placeholder="مثال: إيجار شهر أكتوبر" /></div>
    </div>
    <div class="field"><label>ملاحظات</label><textarea v-model="form.notes" rows="2" /></div>
  </div>
</template>
