<script setup lang="ts">
definePageMeta({ title: 'الرسوم والأقساط', titleEn: 'Fees' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const d = ref<any>(null); const classes = ref<any[]>([]); const classId = ref('')
const gen = reactive<any>({ open: false, class_ids: [] as number[], academic_year: currentAcademicYear(), send: true, installments: [] as any[] })
const result = ref<any>(null); const err = ref(''); const busy = ref(false)
const pay = ref<any>(null); const payForm = reactive({ amount: 0, method: 'cash' })
async function load() { d.value = await api.get('/app/education/fees', { class_id: classId.value }) }
onMounted(async () => { await load(); classes.value = await api.get('/app/education/classes', { active_only: 1 }) })
function startGen() {
  const y = new Date().getMonth() >= 7 ? new Date().getFullYear() : new Date().getFullYear() - 1
  gen.class_ids = classes.value.map((c: any) => c.id)
  gen.installments = [
    { label: tt('القسط الأول', 'Installment 1'), due_date: `${y}-09-15`, percent: 40 },
    { label: tt('القسط الثاني', 'Installment 2'), due_date: `${y + 1}-01-15`, percent: 30 },
    { label: tt('القسط الثالث', 'Installment 3'), due_date: `${y + 1}-04-15`, percent: 30 },
  ]
  result.value = null; err.value = ''; gen.open = true
}
const pctTotal = computed(() => gen.installments.reduce((n: number, i: any) => n + (+i.percent || 0), 0))
const preview = computed(() => classes.value.filter((c: any) => gen.class_ids.includes(c.id)).reduce((n: number, c: any) => n + c.students_count * +c.annual_fee * pctTotal.value / 100, 0))
function toggleClass(id: number) { const i = gen.class_ids.indexOf(id); i >= 0 ? gen.class_ids.splice(i, 1) : gen.class_ids.push(id) }
async function generate() {
  if (!(await uiConfirm(tt(`إصدار ${gen.installments.length} أقساط لطلاب ${gen.class_ids.length} شعبة؟ ستُرسل الفواتير لأولياء الأمور وتُرحَّل للمحاسبة.`, `Issue ${gen.installments.length} installments for ${gen.class_ids.length} classes?`), { tone: 'info', okText: tt('إصدار', 'Issue') }))) return
  busy.value = true; err.value = ''
  try { result.value = await api.post('/app/education/fees/generate', gen); await load() } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
function openPay(i: any) { pay.value = i; payForm.amount = +(+i.total - +i.paid).toFixed(3); payForm.method = 'cash' }
async function doPay() { try { await api.post(`/app/invoices/${pay.value.id}/pay`, payForm); pay.value = null; await load() } catch (e: any) { uiAlert(e.message) } }
const today = f.today()
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/education">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('الرسوم والأقساط', 'Fees') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b>{{ f.money(d?.stats.billed) }}</b><small>{{ tt('رسوم صادرة', 'Billed') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats.paid) }}</b><small>{{ tt('محصّل', 'Collected') }} ({{ d?.stats.rate ?? 0 }}%)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(d?.stats.balance) }}</b><small>{{ tt('متبقٍ', 'Outstanding') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ f.money(d?.stats.overdue) }}</b><small>{{ tt('متأخر عن موعده', 'Overdue') }}</small></div></div>
    </div>

    <div v-if="gen.open" class="card">
      <div class="card-title"><i class="ti ti-file-plus" /> {{ tt('إصدار أقساط الرسوم', 'Issue fee installments') }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div v-if="result" class="alert ok">{{ tt(`تم إصدار ${result.created} فاتورة.`, `${result.created} invoices issued.`) }}<template v-if="result.already_billed"> {{ tt(`(${result.already_billed} صادرة مسبقاً تم تجاهلها)`, `(${result.already_billed} already billed, skipped)`) }}</template><template v-if="result.skipped_no_guardian.length"><br />{{ tt('بدون ولي أمر (لم تُصدر لهم):', 'No guardian (skipped):') }} {{ result.skipped_no_guardian.join('، ') }}</template></div>
      <div class="field"><label>{{ tt('الشعب', 'Classes') }}</label><div class="chips"><span v-for="c in classes" :key="c.id" class="chip" :class="{ on: gen.class_ids.includes(c.id) }" @click="toggleClass(c.id)">{{ c.name }} <small class="muted">{{ c.students_count }} · {{ f.money(c.annual_fee) }}</small></span></div></div>
      <div class="grid grid-3"><div class="field"><label>{{ tt('العام الدراسي', 'Academic year') }}</label><input v-model="gen.academic_year" dir="ltr" /></div></div>
      <table class="table lines-editor">
        <thead><tr><th>{{ tt('القسط', 'Installment') }}</th><th style="width:170px">{{ tt('تاريخ الاستحقاق', 'Due date') }}</th><th style="width:120px">{{ tt('% من الرسوم', '% of fee') }}</th><th style="width:44px"></th></tr></thead>
        <tbody><tr v-for="(i, n) in gen.installments" :key="n"><td><input v-model="i.label" /></td><td><input v-model="i.due_date" type="date" dir="ltr" /></td><td><input v-model.number="i.percent" type="number" min="0" max="100" dir="ltr" /></td><td><button class="btn ghost sm icon" style="color:var(--primary)" @click="gen.installments.splice(n, 1)"><i class="ti ti-x" /></button></td></tr></tbody>
      </table>
      <div class="row between mt">
        <button class="btn ghost sm" @click="gen.installments.push({ label: tt(`القسط ${gen.installments.length + 1}`, `Installment ${gen.installments.length + 1}`), due_date: today, percent: 0 })"><i class="ti ti-plus" /> {{ tt('قسط', 'Installment') }}</button>
        <span :style="pctTotal !== 100 ? 'color:var(--primary)' : 'color:var(--ok)'">{{ tt('مجموع النسب', 'Total') }}: <b>{{ pctTotal }}%</b> · {{ tt('القيمة التقديرية', 'Est. value') }} <b>{{ f.money(preview) }}</b> {{ tt('(قبل الخصومات والضريبة)', '(before discounts & tax)') }}</span>
      </div>
      <div class="row mt"><button class="btn" :disabled="busy || !gen.class_ids.length || !gen.installments.length" @click="generate"><i class="ti ti-send" /> {{ tt('إصدار الفواتير', 'Issue invoices') }}</button><button class="btn ghost" @click="gen.open = false">{{ tt('إغلاق', 'Close') }}</button></div>
      <small class="muted" style="display:block;margin-top:6px">{{ tt('تُصدر فاتورة لكل طالب لكل قسط باسم ولي الأمر، مع خصم الطالب. إعادة الإصدار لا تُكرّر الأقساط الموجودة.', 'One invoice per student per installment, addressed to the guardian, with the student discount. Re-running never duplicates.') }}</small>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><b>{{ tt('أقساط غير مسددة', 'Unpaid installments') }}</b><UiSelect v-model="classId" style="min-width:150px" @change="load"><option value="">{{ tt('كل الصفوف', 'All classes') }}</option><option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect></div>
        <button v-if="auth.can('education.manage') && !gen.open" class="btn" @click="startGen"><i class="ti ti-file-plus" /> {{ tt('إصدار أقساط', 'Issue installments') }}</button>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الطالب', 'Student') }}</th><th>{{ tt('الصف', 'Class') }}</th><th>{{ tt('القسط', 'Installment') }}</th><th>{{ tt('ولي الأمر', 'Guardian') }}</th><th>{{ tt('الاستحقاق', 'Due') }}</th><th>{{ tt('المتبقي', 'Balance') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="i in d?.open" :key="i.id" :style="i.due_date && i.due_date < today ? 'background:var(--primary-50)' : ''">
            <td><NuxtLink :to="`/education/students/${i.student_id}`" style="color:var(--info);font-weight:700">{{ i.student?.name }}</NuxtLink></td><td>{{ i.student?.edu_class?.name || '—' }}</td>
            <td>{{ (i.subject || '').split(' — ')[0] }}</td>
            <td>{{ i.customer?.name }}<br /><a v-if="i.customer?.phone" :href="`https://wa.me/${String(i.customer.phone).replace(/\D/g, '')}`" target="_blank" style="color:var(--ok);font-size:12px" dir="ltr"><i class="ti ti-brand-whatsapp" /> {{ i.customer.phone }}</a></td>
            <td dir="ltr" style="text-align:right">{{ i.due_date || '—' }}<br /><small v-if="i.due_date && i.due_date < today" style="color:var(--primary)">{{ tt('متأخر', 'overdue') }}</small></td>
            <td class="num"><b>{{ f.money(+i.total - +i.paid) }}</b></td>
            <td><button class="btn sm" @click="openPay(i)"><i class="ti ti-cash" /> {{ tt('تحصيل', 'Collect') }}</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.open.length" class="empty">{{ tt('لا توجد أقساط غير مسددة 🎉', 'No unpaid installments 🎉') }}</p>
    </div>

    <UiModal v-if="pay" :title="tt(`تحصيل — ${pay.student?.name}`, `Collect — ${pay.student?.name}`)" @close="pay = null">
      <p class="muted" style="margin-top:0">{{ pay.subject }}</p>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('المبلغ', 'Amount') }}</label><input v-model.number="payForm.amount" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="payForm.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option></UiSelect></div>
      </div>
      <template #footer><button class="btn ghost" @click="pay = null">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn ok" :disabled="!(payForm.amount > 0)" @click="doPay"><i class="ti ti-check" /> {{ tt('تسجيل', 'Record') }}</button></template>
    </UiModal>
  </div>
</template>
