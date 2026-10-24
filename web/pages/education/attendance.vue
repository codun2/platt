<script setup lang="ts">
definePageMeta({ title: 'حضور الطلاب', titleEn: 'Student Attendance' })
const api = useApi(); const f = useFmt(); const route = useRoute()
const classes = ref<any[]>([]); const classId = ref<any>(route.query.class_id ? Number(route.query.class_id) : '')
const date = ref(f.today()); const sheet = ref<any>(null); const rows = ref<any[]>([]); const msg = ref(''); const err = ref(''); const busy = ref(false)
async function load() {
  msg.value = ''; err.value = ''
  if (!classId.value) { sheet.value = null; rows.value = []; return }
  sheet.value = await api.get('/app/education/attendance', { class_id: classId.value, date: date.value })
  rows.value = sheet.value.rows.map((r: any) => ({ ...r, status: r.status || 'present' }))   // default: everyone present
}
onMounted(async () => { classes.value = await api.get('/app/education/classes', { active_only: 1 }); if (!classId.value && classes.value.length) classId.value = classes.value[0].id; await load() })
watch([classId, date], load)
const counts = computed(() => rows.value.reduce((m: any, r: any) => { m[r.status] = (m[r.status] || 0) + 1; return m }, {}))
function all(status: string) { rows.value.forEach(r => (r.status = status)) }
async function save() {
  busy.value = true; err.value = ''
  try { await api.post('/app/education/attendance', { class_id: classId.value, date: date.value, rows: rows.value.map(r => ({ student_id: r.student_id, status: r.status, note: r.note || null })) }); msg.value = tt('تم حفظ الحضور', 'Attendance saved'); await load(); msg.value = tt('تم حفظ الحضور', 'Attendance saved') }
  catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/education">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('حضور الطلاب', 'Attendance') }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <UiSelect v-model="classId" style="min-width:180px"><option value="">{{ tt('اختر الشعبة', 'Select class') }}</option><option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <input v-model="date" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" />
          <span v-if="sheet?.saved" class="badge ok"><i class="ti ti-check" /> {{ tt('مسجّل مسبقاً — يمكنك التعديل', 'Already saved — editable') }}</span>
        </div>
        <div class="chips"><span v-for="(st, k) in attendStatus" :key="k" class="chip"><i :class="`ti ${st.icon}`" /> {{ st.label }} <b>{{ counts[k] || 0 }}</b></span></div>
      </div>
      <template v-if="rows.length">
        <div class="row" style="margin-bottom:8px"><small class="muted">{{ tt('الكل:', 'All:') }}</small><button class="btn ghost sm" @click="all('present')">{{ tt('حاضر', 'Present') }}</button><button class="btn ghost sm" @click="all('absent')">{{ tt('غائب', 'Absent') }}</button></div>
        <table class="table">
          <thead><tr><th>#</th><th>{{ tt('الطالب', 'Student') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th>{{ tt('ملاحظة', 'Note') }}</th><th>{{ tt('غياب الشهر', 'Absences (month)') }}</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in rows" :key="r.student_id" :style="r.status === 'absent' ? 'background:#fef2f2' : ''">
              <td class="muted">{{ i + 1 }}</td>
              <td><NuxtLink :to="`/education/students/${r.student_id}`" style="font-weight:700;color:var(--ink)">{{ r.name }}</NuxtLink></td>
              <td><div class="att"><button v-for="(st, k) in attendStatus" :key="k" type="button" :class="[{ on: r.status === k }, k]" @click="r.status = k"><i :class="`ti ${st.icon}`" /> {{ st.label }}</button></div></td>
              <td><input v-if="r.status !== 'present'" v-model="r.note" :placeholder="tt('السبب (اختياري)', 'Reason (optional)')" style="padding:5px 8px;border:1px solid var(--line);border-radius:8px;width:100%" /></td>
              <td><b :style="r.absent_month >= 3 ? 'color:var(--primary)' : ''">{{ r.absent_month }}</b></td>
            </tr>
          </tbody>
        </table>
        <button class="btn mt" :disabled="busy" @click="save"><i class="ti ti-device-floppy" /> {{ tt('حفظ الحضور', 'Save attendance') }}</button>
      </template>
      <p v-else-if="classId" class="empty">{{ tt('لا يوجد طلاب منتظمون في هذه الشعبة.', 'No active students in this class.') }}</p>
      <p v-else class="empty">{{ tt('اختر الشعبة لبدء التحضير.', 'Pick a class to start.') }}</p>
    </div>
  </div>
</template>

<style scoped>
.att { display: inline-flex; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.att button { border: 0; background: var(--card); padding: 6px 10px; font: inherit; font-size: 12px; cursor: pointer; color: var(--muted); border-inline-start: 1px solid var(--line); }
.att button:first-child { border-inline-start: 0; }
.att button.on.present { background: #dcfce7; color: #166534; font-weight: 700; }
.att button.on.absent { background: #fee2e2; color: #991b1b; font-weight: 700; }
.att button.on.late { background: #fef3c7; color: #92400e; font-weight: 700; }
.att button.on.excused { background: #e0f2fe; color: #075985; font-weight: 700; }
</style>
