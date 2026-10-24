<script setup lang="ts">
/** Lease / installment contract form with a live schedule preview. */
const props = defineProps<{ form: any; assets: any[]; customers: any[]; editing?: boolean }>()
const api = useApi(); const f = useFmt(); const terms = useRentalTerms()
const preview = ref<any>(null); const previewErr = ref('')
const asset = computed(() => props.assets.find((a: any) => String(a.id) === String(props.form.asset_id)))
const newRenter = ref(!props.form.customer_id)
const assetLabel = (a: any) => (a.parent?.name ? `${a.parent.name} — ` : '') + a.name + (a.plate_no ? ` (${a.plate_no})` : '')

// Picking an asset pre-fills its default pricing (only on a fresh choice).
watch(() => props.form.asset_id, () => {
  if (!asset.value) return
  props.form.rent_amount = +asset.value.rent_amount || props.form.rent_amount
  props.form.frequency = asset.value.rent_frequency || props.form.frequency
  props.form.deposit = +asset.value.deposit || 0
})
watch(newRenter, v => { if (v) props.form.customer_id = '' })

let t: any = null
watch(() => [props.form.start_date, props.form.end_date, props.form.frequency, props.form.rent_amount, props.form.down_payment], () => {
  clearTimeout(t)
  t = setTimeout(async () => {
    previewErr.value = ''
    if (!props.form.start_date || !props.form.end_date) { preview.value = null; return }
    try { preview.value = await api.post('/app/rental/contracts/preview', { start_date: props.form.start_date, end_date: props.form.end_date, frequency: props.form.frequency, rent_amount: +props.form.rent_amount || 0, down_payment: +props.form.down_payment || 0 }) }
    catch (e: any) { preview.value = null; previewErr.value = e.message }
  }, 300)
}, { immediate: true })
</script>

<template>
  <div>
    <div class="card-title"><i :class="`ti ${terms.isVehicle ? 'ti-car' : 'ti-building-community'}`" /> {{ terms.asset }} {{ tt('والطرف الآخر', '& party') }}</div>
    <div class="grid grid-3">
      <div class="field" style="grid-column:span 2"><label>{{ terms.asset }} <span style="color:var(--primary)">*</span></label>
        <UiSelect v-model="form.asset_id" :placeholder="tt('اختر', 'Select')" :add-label="terms.newAsset" add-to="/rental/assets/new" add-key="asset"><option value="">—</option><option v-for="a in assets" :key="a.id" :value="a.id">{{ assetLabel(a) }} · {{ assetStatus[a.status]?.label }}</option></UiSelect>
        <small v-if="asset" class="muted">{{ assetTypes[asset.type] }}<template v-if="asset.area"> · {{ asset.area }} {{ tt('م²', 'm²') }}</template><template v-if="asset.rooms"> · {{ asset.rooms }} {{ tt('غرف', 'rooms') }}</template><template v-if="asset.owner"> · {{ tt('المالك', 'Owner') }}: {{ asset.owner.name }}</template></small>
      </div>
      <div v-if="terms.isSale" class="field"><label>{{ tt('نوع العقد', 'Contract type') }}</label><UiSelect v-model="form.kind"><option value="installment">{{ tt('بيع بالتقسيط', 'Installment sale') }}</option><option value="lease">{{ tt('تأجير', 'Lease') }}</option></UiSelect></div>
    </div>
    <div class="grid grid-3">
      <div class="field"><label>{{ terms.renter }} <span style="color:var(--primary)">*</span></label>
        <div class="row" style="gap:6px;margin-bottom:6px"><span class="chip" :class="{ on: !newRenter }" @click="newRenter = false">{{ tt('مسجّل', 'Existing') }}</span><span class="chip" :class="{ on: newRenter }" @click="newRenter = true">{{ tt('جديد', 'New') }}</span></div>
        <UiSelect v-if="!newRenter" v-model="form.customer_id" :placeholder="tt('اختر', 'Select')"><option value="">—</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect>
        <input v-else v-model="form.new_customer.name" :placeholder="tt('الاسم الكامل', 'Full name')" />
      </div>
      <template v-if="newRenter">
        <div class="field"><label>{{ tt('الهاتف', 'Phone') }}</label><input v-model="form.new_customer.phone" dir="ltr" /></div>
        <div class="field"><label>{{ tt('رقم الهوية / الإقامة', 'ID / residency no.') }}</label><input v-model="form.new_customer.id_number" dir="ltr" /></div>
      </template>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-calendar-event" /> {{ tt('المدة والدفعات', 'Term & payments') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('تاريخ البداية', 'Start date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.start_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('تاريخ النهاية', 'End date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.end_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('تكرار الدفع', 'Payment frequency') }}</label><UiSelect v-model="form.frequency"><option v-for="(l, k) in frequencies" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ terms.rent }} {{ tt('لكل دفعة', 'per payment') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.rent_amount" type="number" min="0" step="0.001" dir="ltr" /></div>
      <div v-if="form.kind === 'installment'" class="field"><label>{{ tt('الدفعة المقدّمة', 'Down payment') }}</label><input v-model.number="form.down_payment" type="number" min="0" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('التأمين (يُسترد)', 'Security deposit (refundable)') }}</label><input v-model.number="form.deposit" type="number" min="0" step="0.001" dir="ltr" /></div>
    </div>
    <div v-if="editing" class="alert warn" style="margin-top:4px">{{ tt('تغيير المدة أو المبلغ يعيد توليد جدول الدفعات، ولا يُسمح بذلك بعد تحصيل أي دفعة.', 'Changing the term or amount rebuilds the schedule, which is not allowed once a payment is collected.') }}</div>

    <div class="grid grid-2" style="margin-top:12px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-file-text" /> {{ tt('الشروط والملاحظات', 'Terms & notes') }}</div>
        <div class="field"><label>{{ tt('شروط العقد', 'Contract terms') }}</label><textarea v-model="form.terms" rows="4" :placeholder="tt('مثال: الصيانة البسيطة على المستأجر، يُمنع التأجير من الباطن…', 'e.g. minor maintenance on tenant, no subletting…')" /></div>
        <div class="field"><label>{{ tt('ملاحظات داخلية', 'Internal notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
      </div>
      <div>
        <div class="card-title"><i class="ti ti-list-numbers" /> {{ tt('جدول الدفعات المتوقع', 'Payment schedule') }}</div>
        <div v-if="previewErr" class="alert bad">{{ previewErr }}</div>
        <div v-else-if="preview" class="totals-box">
          <div class="row between"><span>{{ tt('عدد الدفعات', 'Payments') }}</span><b>{{ preview.count }}</b></div>
          <div class="row between"><span>{{ tt('أول دفعة', 'First due') }}</span><b dir="ltr">{{ preview.rows[0]?.due_date }}</b></div>
          <div class="row between"><span>{{ tt('آخر دفعة', 'Last due') }}</span><b dir="ltr">{{ preview.rows[preview.rows.length - 1]?.due_date }}</b></div>
          <div class="row between"><span>{{ tt('التأمين', 'Deposit') }}</span><b>{{ f.money(form.deposit) }}</b></div>
          <div class="row between total"><span>{{ tt('إجمالي قيمة العقد', 'Contract value') }}</span><b>{{ f.money(preview.total, true) }}</b></div>
          <details style="margin-top:8px"><summary class="muted" style="cursor:pointer;font-size:12px">{{ tt('عرض الجدول', 'Show schedule') }}</summary>
            <table class="table" style="margin-top:6px"><tbody><tr v-for="r in preview.rows" :key="r.seq"><td class="muted">{{ r.seq }}</td><td dir="ltr" style="text-align:right">{{ r.due_date }}</td><td>{{ r.is_down ? tt('دفعة مقدمة', 'Down payment') : '' }}</td><td class="num">{{ f.money(r.amount) }}</td></tr></tbody></table>
          </details>
        </div>
      </div>
    </div>
  </div>
</template>
