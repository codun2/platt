<script setup lang="ts">
/** Case / project file: details, agenda (hearings & tasks), hours, fee invoices. */
const props = defineProps<{ kind: 'case' | 'project' }>()
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const T = computed(() => projectTerms(props.kind))
const id = Number(route.params.id)
const p = ref<any>(null); const people = ref<any[]>([]); const customers = ref<any[]>([])
const tab = ref<'agenda' | 'hours' | 'billing' | 'details'>('agenda')
const editing = ref(route.query.edit === '1'); const form = reactive<any>(blankProject(props.kind))
const err = ref(''); const msg = ref('')
const dialog = ref<{ mode: 'edit' | 'complete'; task?: any; kind?: 'task' | 'hearing' } | null>(null)
const hours = reactive({ date: f.today(), hours: 1, description: '', billable: true, rate: '' as any })
const bill = reactive({ include_hours: true, fixed_amount: 0, fixed_label: '' })
const canManage = computed(() => auth.can('projects.manage'))

async function load() {
  p.value = await api.get(`/app/projects/items/${id}`)
  if (!editing.value) Object.assign(form, projectToForm(p.value))
  if (hours.rate === '') hours.rate = +p.value.hourly_rate || 0
  if (!bill.fixed_amount && p.value.fee_type !== 'hourly') bill.fixed_amount = +p.value.fee_amount || 0
}
onMounted(async () => {
  await load(); people.value = await api.get('/app/projects/people')
  if (editing.value) { Object.assign(form, projectToForm(p.value)); customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) }
})
async function startEdit() { Object.assign(form, projectToForm(p.value)); customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }); editing.value = true }
async function saveEdit() { err.value = ''; try { await api.patch(`/app/projects/items/${id}`, projectPayload(form)); editing.value = false; msg.value = tt('تم حفظ التعديلات', 'Changes saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!(await uiConfirm(tt(`حذف ${T.value.one} «${p.value.title}» نهائياً؟`, `Delete «${p.value.title}» permanently?`)))) return; try { await api.del(`/app/projects/items/${id}`); navigateTo(T.value.base) } catch (e: any) { uiAlert(e.message) } }
async function addHours() {
  err.value = ''; msg.value = ''
  try { await api.post('/app/projects/timesheets', { project_id: id, ...hours, rate: hours.rate === '' ? null : +hours.rate }); msg.value = tt('تم تسجيل الساعات', 'Hours logged'); hours.hours = 1; hours.description = ''; await load() }
  catch (e: any) { err.value = e.message }
}
async function delHours(t: any) { if (!(await uiConfirm(tt('حذف هذا القيد من الساعات؟', 'Delete this time entry?')))) return; try { await api.del(`/app/projects/timesheets/${t.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
async function issueInvoice() {
  err.value = ''; msg.value = ''
  try { const r = await api.post(`/app/projects/items/${id}/bill`, bill); msg.value = tt(`تم إنشاء فاتورة ${r.number} كمسودة — راجعها ثم أرسلها`, `Draft invoice ${r.number} created — review and send it`); await load(); tab.value = 'billing' }
  catch (e: any) { err.value = e.message }
}
const openTasks = computed(() => (p.value?.tasks || []).filter((t: any) => ['todo', 'doing'].includes(t.status)))
const closedTasks = computed(() => (p.value?.tasks || []).filter((t: any) => !['todo', 'doing'].includes(t.status)))
const nextHearing = computed(() => openTasks.value.find((t: any) => t.kind === 'hearing'))
const details = computed(() => {
  const x = p.value; if (!x) return []
  const rows: [string, any][] = T.value.isCase
    ? [[tt('نوع القضية', 'Type'), caseTypes[x.case_type]], [tt('المرحلة', 'Stage'), caseStages[x.stage]], [tt('المحكمة', 'Court'), x.court], [tt('رقم القضية', 'Case no.'), x.court_case_no],
       [tt('صفة الموكّل', 'Client role'), clientRoles[x.client_role]], [tt('الخصم', 'Opponent'), x.opponent], [tt('محامي الخصم', 'Opposing counsel'), x.opponent_lawyer]]
    : [[tt('الموقع', 'Location'), x.location], [tt('قيمة العقد', 'Contract value'), +x.contract_value ? f.money(x.contract_value, true) : null], [tt('الإنجاز', 'Progress'), `${x.progress || 0}%`]]
  rows.push([T.value.lead, x.lead?.name], [tt('البدء', 'Start'), x.start_date], [tt('الموعد النهائي', 'Deadline'), x.due_date],
    [tt('الأتعاب', 'Fees'), `${feeTypes[x.fee_type]}${+x.hourly_rate ? ' · ' + f.money(x.hourly_rate) + ' ' + tt('/ساعة', '/h') : ''}${+x.fee_amount ? ' · ' + f.money(x.fee_amount) : ''}`])
  return rows.filter(r => r[1] !== null && r[1] !== undefined && r[1] !== '' && String(r[1]) !== 'undefined')
})
</script>

<template>
  <div v-if="p">
    <div class="breadcrumb"><NuxtLink :to="T.base">{{ T.many }}</NuxtLink> › <b>{{ p.title }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <ProjectForm :form="form" :customers="customers" :people="people" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" @click="saveEdit"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div>
            <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap"><i :class="`ti ${T.icon}`" style="font-size:24px;color:var(--primary)" /><h2 style="margin:0">{{ p.title }}</h2>
              <span class="badge" :class="projectStatus[p.status]?.cls">{{ projectStatus[p.status]?.label }}</span>
              <span v-if="p.priority !== 'normal'" class="badge" :class="priorities[p.priority]?.cls">{{ priorities[p.priority]?.label }}</span></div>
            <p class="muted" style="margin:6px 0 0"><span dir="ltr">{{ p.number }}</span> · {{ T.client }}: <NuxtLink v-if="p.customer" :to="`/crm/customers/${p.customer.id}`" style="color:var(--info)">{{ p.customer.name }}</NuxtLink><span v-else>—</span>
              <template v-if="T.isCase && p.court"> · {{ p.court }}<span v-if="p.court_case_no"> (<span dir="ltr">{{ p.court_case_no }}</span>)</span></template>
              <template v-if="T.isCase && p.opponent"> · {{ tt('ضد', 'vs') }} {{ p.opponent }}</template></p>
          </div>
          <div class="row" style="gap:8px">
            <button class="btn sm" @click="dialog = { mode: 'edit', kind: T.isCase ? 'hearing' : 'task' }"><i class="ti ti-calendar-plus" /> {{ T.isCase ? tt('جلسة / مهمة', 'Hearing / task') : tt('مهمة', 'Task') }}</button>
            <button class="btn ghost sm" @click="tab = 'hours'"><i class="ti ti-clock-plus" /> {{ tt('تسجيل ساعات', 'Log hours') }}</button>
            <template v-if="canManage"><button class="btn ghost sm" @click="startEdit"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></template>
          </div>
        </div>
      </div>

      <div class="grid grid-4 mt">
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b style="font-size:14px">{{ nextHearing ? fmtWhen(nextHearing.due_at) : (openTasks[0] ? fmtWhen(openTasks[0].due_at) : '—') }}</b><small>{{ T.isCase ? tt('الجلسة القادمة', 'Next hearing') : tt('أقرب مهمة', 'Next task') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-list-check" /></div><div><b>{{ openTasks.length }}</b><small>{{ tt('مهام وجلسات مفتوحة', 'Open tasks & hearings') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-clock" /></div><div><b>{{ p.totals.hours }}</b><small>{{ tt('إجمالي الساعات', 'Total hours') }} · {{ f.money(p.totals.amount) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(p.totals.unbilled_amount) }}</b><small>{{ tt('غير مفوتر', 'Unbilled') }} ({{ p.totals.unbilled_hours }} {{ tt('ساعة', 'h') }})</small></div></div>
      </div>

      <div class="card mt">
        <div class="tabs">
          <button :class="{ on: tab === 'agenda' }" @click="tab = 'agenda'">{{ T.isCase ? tt('الجلسات والمهام', 'Hearings & tasks') : tt('المهام', 'Tasks') }} ({{ openTasks.length }})</button>
          <button :class="{ on: tab === 'hours' }" @click="tab = 'hours'">{{ tt('الساعات', 'Hours') }} ({{ p.timesheets.length }})</button>
          <button :class="{ on: tab === 'billing' }" @click="tab = 'billing'">{{ tt('الأتعاب والفواتير', 'Fees & invoices') }} ({{ p.invoices.length }})</button>
          <button :class="{ on: tab === 'details' }" @click="tab = 'details'">{{ tt('التفاصيل', 'Details') }}</button>
        </div>

        <div v-if="tab === 'agenda'">
          <table v-if="openTasks.length" class="table">
            <thead><tr><th>{{ tt('الموعد', 'When') }}</th><th>{{ tt('العنوان', 'Title') }}</th><th>{{ tt('المكان', 'Place') }}</th><th>{{ tt('المسؤول', 'Assignee') }}</th><th></th></tr></thead>
            <tbody>
              <tr v-for="t in openTasks" :key="t.id" :style="isOverdue(t) ? 'background:var(--primary-50)' : ''">
                <td style="white-space:nowrap">{{ fmtWhen(t.due_at) }}<br /><small v-if="isOverdue(t)" style="color:var(--primary)">{{ tt('فات موعدها', 'Overdue') }}</small></td>
                <td><span class="badge" :class="t.kind === 'hearing' ? 'info' : 'muted'">{{ t.kind === 'hearing' ? tt('جلسة', 'Hearing') : tt('مهمة', 'Task') }}</span> <b>{{ t.title }}</b><br /><small v-if="t.notes" class="muted">{{ t.notes }}</small></td>
                <td>{{ t.location || '—' }}</td><td>{{ t.assignee?.name || '—' }}</td>
                <td class="row"><button class="btn ok sm" @click="dialog = { mode: 'complete', task: t }"><i class="ti ti-check" /> {{ t.kind === 'hearing' ? tt('النتيجة', 'Outcome') : tt('إنجاز', 'Done') }}</button><button class="btn ghost sm icon" @click="dialog = { mode: 'edit', task: t }"><i class="ti ti-pencil" /></button></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty">{{ tt('لا توجد مواعيد قادمة. أضف جلسة أو مهمة.', 'Nothing scheduled. Add a hearing or task.') }}</p>
          <template v-if="closedTasks.length">
            <div class="card-title mt"><i class="ti ti-history" /> {{ tt('السجل', 'History') }}</div>
            <div v-for="t in closedTasks" :key="t.id" style="border-inline-start:3px solid var(--line);padding:6px 12px;margin-bottom:8px">
              <div class="row between"><b>{{ t.title }}</b><span class="badge" :class="taskStatus[t.status]?.cls">{{ taskStatus[t.status]?.label }}</span></div>
              <small class="muted">{{ fmtWhen(t.due_at) }}<template v-if="t.assignee"> · {{ t.assignee.name }}</template></small>
              <p v-if="t.outcome" style="margin:4px 0 0;white-space:pre-line">{{ t.outcome }}</p>
            </div>
          </template>
        </div>

        <div v-if="tab === 'hours'">
          <div class="grid grid-4" style="align-items:end">
            <div class="field" style="margin:0"><label>{{ tt('التاريخ', 'Date') }}</label><input v-model="hours.date" type="date" dir="ltr" /></div>
            <div class="field" style="margin:0"><label>{{ tt('عدد الساعات', 'Hours') }}</label><input v-model.number="hours.hours" type="number" min="0.25" step="0.25" dir="ltr" /></div>
            <div class="field" style="margin:0"><label>{{ tt('سعر الساعة', 'Rate') }}</label><input v-model.number="hours.rate" type="number" min="0" step="0.001" dir="ltr" /></div>
            <div class="field" style="margin:0"><label class="switch" style="margin-bottom:10px"><input v-model="hours.billable" type="checkbox" /><span /> {{ tt('قابلة للفوترة', 'Billable') }}</label></div>
          </div>
          <div class="row mt"><input v-model="hours.description" :placeholder="tt('ماذا أنجزت؟ (حضور جلسة، إعداد مذكرة…)', 'What was done?')" style="flex:1;padding:9px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="addHours" /><button class="btn" :disabled="!(hours.hours > 0)" @click="addHours"><i class="ti ti-plus" /> {{ tt('تسجيل', 'Log') }}</button></div>
          <table v-if="p.timesheets.length" class="table mt">
            <thead><tr><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ tt('البيان', 'Description') }}</th><th>{{ tt('بواسطة', 'By') }}</th><th>{{ tt('الساعات', 'Hours') }}</th><th>{{ tt('القيمة', 'Amount') }}</th><th>{{ tt('الفوترة', 'Billing') }}</th><th></th></tr></thead>
            <tbody><tr v-for="t in p.timesheets" :key="t.id"><td dir="ltr" style="text-align:right">{{ t.date }}</td><td>{{ t.description || '—' }}</td><td>{{ t.user?.name }}</td><td class="num">{{ +t.hours }}</td><td class="num">{{ t.billable ? f.money(t.amount) : '—' }}</td>
              <td><NuxtLink v-if="t.invoice" :to="`/crm/invoices/${t.invoice.id}`" class="badge ok" dir="ltr">{{ t.invoice.number }}</NuxtLink><span v-else-if="t.billable" class="badge warn">{{ tt('غير مفوترة', 'Unbilled') }}</span><span v-else class="badge muted">{{ tt('غير قابلة', 'Non-billable') }}</span></td>
              <td><button v-if="!t.invoice_id" class="btn ghost sm icon" style="color:var(--primary)" @click="delHours(t)"><i class="ti ti-trash" /></button></td></tr></tbody>
          </table>
          <p v-else class="empty">{{ tt('لم تُسجَّل ساعات بعد.', 'No hours yet.') }}</p>
        </div>

        <div v-if="tab === 'billing'">
          <div v-if="canManage" class="totals-box" style="max-width:none;margin-bottom:14px">
            <div class="card-title" style="margin:0 0 8px"><i class="ti ti-file-invoice" /> {{ tt('إصدار فاتورة أتعاب', 'Issue fee invoice') }}</div>
            <label class="switch"><input v-model="bill.include_hours" type="checkbox" /><span /> {{ tt(`تضمين الساعات غير المفوترة (${p.totals.unbilled_hours} ساعة · ${f.money(p.totals.unbilled_amount)})`, `Include unbilled hours (${p.totals.unbilled_hours}h · ${f.money(p.totals.unbilled_amount)})`) }}</label>
            <div class="grid grid-2 mt">
              <div class="field" style="margin:0"><label>{{ tt('مبلغ ثابت إضافي (أتعاب مقطوعة / دفعة)', 'Additional fixed amount') }}</label><input v-model.number="bill.fixed_amount" type="number" min="0" step="0.001" dir="ltr" /></div>
              <div class="field" style="margin:0"><label>{{ tt('بيان المبلغ الثابت', 'Fixed amount label') }}</label><input v-model="bill.fixed_label" :placeholder="tt('أتعاب المرحلة الابتدائية', 'First-instance fee')" /></div>
            </div>
            <button class="btn mt" @click="issueInvoice"><i class="ti ti-file-plus" /> {{ tt('إنشاء الفاتورة كمسودة', 'Create draft invoice') }}</button>
            <small class="muted" style="display:block;margin-top:6px">{{ tt('تُنشأ في «الفواتير» كمسودة لتراجعها، وتُرحَّل للمحاسبة عند إرسالها.', 'Created in Invoices as a draft; posted to accounting when sent.') }}</small>
          </div>
          <table v-if="p.invoices.length" class="table">
            <thead><tr><th>{{ tt('الفاتورة', 'Invoice') }}</th><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ tt('الإجمالي', 'Total') }}</th><th>{{ tt('المدفوع', 'Paid') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
            <tbody><tr v-for="i in p.invoices" :key="i.id"><td><NuxtLink :to="`/crm/invoices/${i.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ i.number }}</NuxtLink></td><td dir="ltr" style="text-align:right">{{ i.date }}</td><td class="num">{{ f.money(i.total) }}</td><td class="num">{{ f.money(i.paid) }}</td><td><span class="badge" :class="invoiceStatus[i.status]?.cls">{{ invoiceStatus[i.status]?.label || i.status }}</span></td></tr></tbody>
          </table>
          <p v-else class="empty">{{ tt('لا توجد فواتير لهذا الملف بعد.', 'No invoices for this file yet.') }}</p>
        </div>

        <div v-if="tab === 'details'">
          <div class="grid grid-3"><div v-for="[k, v] in details" :key="k"><small class="muted">{{ k }}</small><br /><b>{{ v }}</b></div></div>
          <div v-if="p.description" class="mt"><small class="muted">{{ T.isCase ? tt('ملخّص الوقائع', 'Facts') : tt('الوصف', 'Description') }}</small><p style="white-space:pre-line;margin:4px 0 0">{{ p.description }}</p></div>
          <div v-if="p.notes" style="background:var(--bg);padding:10px;border-radius:10px;margin-top:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية', 'Internal notes') }}</small><p style="white-space:pre-line;margin:4px 0 0">{{ p.notes }}</p></div>
        </div>
      </div>
    </template>

    <TaskDialog v-if="dialog" :mode="dialog.mode" :task="dialog.task" :project-id="id" :people="people" :default-kind="dialog.kind" @close="dialog = null" @saved="load" />
  </div>
</template>
