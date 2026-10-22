<script setup lang="ts">
definePageMeta({ title: 'الحضور والإجازات' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const tab = ref<'day' | 'month' | 'leaves'>(route.query.tab === 'leaves' ? 'leaves' : 'day')
const canManage = computed(() => auth.can('hr.manage'))
// Daily sheet
const date = ref(new Date().toISOString().slice(0, 10)); const sheet = ref<any>(null); const rows = ref<any[]>([]); const saving = ref(false); const msg = ref(''); const err = ref('')
async function loadDay() { sheet.value = await api.get('/app/hr/attendance/day', { date: date.value }); rows.value = sheet.value.rows.map((r: any) => ({ employee: r.employee, status: r.attendance?.status || '', check_in: r.attendance?.check_in?.slice(0, 5) || '', check_out: r.attendance?.check_out?.slice(0, 5) || '', late_minutes: r.attendance?.late_minutes || 0, overtime_minutes: r.attendance?.overtime_minutes || 0, note: r.attendance?.note || '' })) }
function markAll(status: string) { rows.value.forEach(r => { if (!r.status || status === 'present') r.status = status }) }
async function saveDay() {
  err.value = ''; msg.value = ''; saving.value = true
  const payload = rows.value.filter(r => r.status).map(r => ({ employee_id: r.employee.id, status: r.status, check_in: r.check_in || null, check_out: r.check_out || null, late_minutes: +r.late_minutes || 0, overtime_minutes: +r.overtime_minutes || 0, note: r.note || null }))
  try { const r = await api.post('/app/hr/attendance/bulk', { date: date.value, rows: payload }); msg.value = `تم حفظ حضور ${r.count} موظف`; await loadDay() } catch (x: any) { err.value = x.message } finally { saving.value = false }
}
function shift(n: number) { const d = new Date(date.value + 'T00:00:00'); d.setDate(d.getDate() + n); date.value = d.toISOString().slice(0, 10); loadDay() }
// Monthly
const month = ref(thisMonth()); const summary = ref<any>(null)
async function loadMonth() { summary.value = await api.get('/app/hr/attendance/summary', { month: month.value }) }
// Leaves
const leaves = ref<any>(null); const lf = reactive({ status: '', type: '' })
async function loadLeaves() { leaves.value = await api.get('/app/hr/leaves', lf) }
onMounted(() => { loadDay(); loadMonth(); loadLeaves() })
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr">الموارد البشرية</NuxtLink> › <b>الحضور والإجازات</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-user-check" /></div><div><b>{{ sheet?.summary?.present ?? 0 }}</b><small>حاضر ({{ f.d(date) }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-user-x" /></div><div><b>{{ sheet?.summary?.absent ?? 0 }}</b><small>غائب</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ sheet?.summary?.leave ?? 0 }}</b><small>في إجازة · {{ leaves?.stats?.pending ?? 0 }} طلب معلّق</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-help" /></div><div><b>{{ sheet?.summary?.unmarked ?? 0 }}</b><small>لم يُسجَّل بعد</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:4px">
        <div class="tabs" style="margin-bottom:0;border:none"><button :class="{ on: tab === 'day' }" @click="tab = 'day'">الحضور اليومي</button><button :class="{ on: tab === 'month' }" @click="tab = 'month'">ملخص الشهر</button><button :class="{ on: tab === 'leaves' }" @click="tab = 'leaves'">الإجازات</button></div>
        <NuxtLink v-if="canManage" class="btn sm" to="/hr/leaves/new"><i class="ti ti-plus" /> طلب إجازة</NuxtLink>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>

      <div v-if="tab === 'day'">
        <div class="row between" style="margin-bottom:12px">
          <div class="row"><button class="btn ghost sm" @click="shift(1)"><i class="ti ti-chevron-right" /></button><input v-model="date" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="loadDay" /><button class="btn ghost sm" @click="shift(-1)"><i class="ti ti-chevron-left" /></button><button class="btn ghost sm" @click="date = new Date().toISOString().slice(0, 10); loadDay()">اليوم</button></div>
          <div v-if="canManage" class="row"><button class="btn ghost sm" @click="markAll('present')"><i class="ti ti-checks" /> الكل حاضر</button><button class="btn ghost sm" @click="markAll('weekend')">غير المسجَّل: نهاية أسبوع</button><button class="btn" :disabled="saving" @click="saveDay"><i class="ti ti-device-floppy" /> حفظ الحضور</button></div>
        </div>
        <table class="table lines-editor">
          <thead><tr><th>#</th><th>الموظف</th><th style="width:150px">الحالة</th><th style="width:100px">دخول</th><th style="width:100px">خروج</th><th style="width:90px">تأخير (د)</th><th style="width:90px">إضافي (د)</th><th>ملاحظة</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in rows" :key="r.employee.id">
              <td class="muted">{{ i + 1 }}</td>
              <td><NuxtLink :to="`/hr/employees/${r.employee.id}`" style="color:var(--info);font-weight:700">{{ r.employee.name }}</NuxtLink><br /><small class="muted">{{ [r.employee.job_title, r.employee.department].filter(Boolean).join(' · ') }}</small></td>
              <td><UiSelect v-model="r.status" placeholder="—"><option value="">—</option><option v-for="(s, k) in attendanceStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect></td>
              <td><input v-model="r.check_in" type="time" dir="ltr" :disabled="!canManage" /></td>
              <td><input v-model="r.check_out" type="time" dir="ltr" :disabled="!canManage" /></td>
              <td><input v-model.number="r.late_minutes" type="number" min="0" dir="ltr" :disabled="!canManage" /></td>
              <td><input v-model.number="r.overtime_minutes" type="number" min="0" dir="ltr" :disabled="!canManage" /></td>
              <td><input v-model="r.note" :disabled="!canManage" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="sheet && !rows.length" class="empty">لا يوجد موظفون. <NuxtLink to="/hr/employees/new" style="color:var(--info)">أضف موظفاً</NuxtLink>.</p>
      </div>

      <div v-else-if="tab === 'month'">
        <div class="row" style="margin-bottom:12px"><input v-model="month" type="month" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="loadMonth" /><b>{{ monthLabel(month) }}</b></div>
        <table class="table">
          <thead><tr><th>الموظف</th><th>القسم</th><th>حضور</th><th>نصف يوم</th><th>غياب</th><th>تأخير</th><th>إجازة</th><th>دقائق تأخير</th><th>دقائق إضافي</th><th>أيام مسجّلة</th></tr></thead>
          <tbody><tr v-for="r in summary?.rows" :key="r.employee.id"><td><NuxtLink :to="`/hr/employees/${r.employee.id}`" style="color:var(--info);font-weight:700">{{ r.employee.name }}</NuxtLink></td><td>{{ r.employee.department || '—' }}</td><td class="num" style="color:var(--ok)">{{ r.present }}</td><td class="num">{{ r.half_day }}</td><td class="num" :style="r.absent ? 'color:var(--primary);font-weight:700' : ''">{{ r.absent }}</td><td class="num">{{ r.late }}</td><td class="num">{{ r.leave }}</td><td class="num">{{ r.late_minutes }}</td><td class="num">{{ r.overtime_minutes }}</td><td class="num">{{ r.days_marked }}</td></tr></tbody>
        </table>
      </div>

      <div v-else>
        <div class="row" style="margin-bottom:12px">
          <UiSelect v-model="lf.status" @change="loadLeaves"><option value="">كل الحالات</option><option v-for="(s, k) in leaveStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <UiSelect v-model="lf.type" @change="loadLeaves"><option value="">كل الأنواع</option><option v-for="(l, k) in leaveTypes" :key="k" :value="k">{{ l }}</option></UiSelect>
        </div>
        <table class="table">
          <thead><tr><th>الموظف</th><th>النوع</th><th>من</th><th>إلى</th><th>أيام</th><th>السبب</th><th>الحالة</th><th>اعتمدها</th><th></th></tr></thead>
          <tbody><tr v-for="l in leaves?.data" :key="l.id"><td><NuxtLink :to="`/hr/employees/${l.employee?.id}`" style="color:var(--info)">{{ l.employee?.name }}</NuxtLink></td><td>{{ leaveTypes[l.type] }}</td><td>{{ f.d(l.from) }}</td><td>{{ f.d(l.to) }}</td><td>{{ l.days }}</td><td class="muted">{{ l.reason || '' }}</td><td><span class="badge" :class="leaveStatus[l.status]?.cls">{{ leaveStatus[l.status]?.label }}</span></td><td>{{ l.approver?.name || '—' }}</td><td><NuxtLink class="btn ghost sm icon" :to="`/hr/leaves/${l.id}`"><i class="ti ti-eye" /></NuxtLink></td></tr></tbody>
        </table>
        <p v-if="leaves && !leaves.data.length" class="empty">لا توجد إجازات.</p>
      </div>
    </div>
  </div>
</template>
