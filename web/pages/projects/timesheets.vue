<script setup lang="ts">
definePageMeta({ title: 'ساعات العمل', titleEn: 'Timesheets' })
const api = useApi(); const f = useFmt(); const auth = useAuth()
const kind = useProjectKind(); const T = computed(() => projectTerms(kind.value))
const d = ref<any>(null); const people = ref<any[]>([]); const projects = ref<any[]>([])
const filt = reactive({ from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10), to: f.today(), project_id: '', user_id: '', billing: '', page: 1 })
const entry = reactive<any>({ project_id: '', date: f.today(), hours: 1, description: '', billable: true })
const err = ref(''); const msg = ref('')
async function load() { d.value = await api.get('/app/projects/timesheets', filt) }
onMounted(async () => { await load(); people.value = await api.get('/app/projects/people'); projects.value = (await api.get('/app/projects/items', { kind: kind.value })).page.data })
async function add() {
  err.value = ''; msg.value = ''
  try { await api.post('/app/projects/timesheets', { ...entry, project_id: entry.project_id || null }); msg.value = tt('تم تسجيل الساعات', 'Hours logged'); entry.hours = 1; entry.description = ''; await load() }
  catch (e: any) { err.value = e.message }
}
async function del(t: any) { if (!(await uiConfirm(tt('حذف هذا القيد؟', 'Delete this entry?')))) return; try { await api.del(`/app/projects/timesheets/${t.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/projects">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ T.isCase ? tt('الساعات المحتسبة', 'Billable hours') : tt('ساعات العمل', 'Timesheets') }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-clock" /></div><div><b>{{ d?.totals.hours ?? 0 }}</b><small>{{ tt('ساعات الفترة', 'Hours in period') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-receipt" /></div><div><b>{{ d?.totals.billable_hours ?? 0 }}</b><small>{{ tt('قابلة للفوترة', 'Billable') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.totals.amount) }}</b><small>{{ tt('قيمة الساعات', 'Value') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(d?.totals.unbilled) }}</b><small>{{ tt('لم تُفوتر بعد', 'Not billed yet') }}</small></div></div>
    </div>
    <div class="card">
      <div class="card-title"><i class="ti ti-clock-plus" /> {{ tt('تسجيل ساعات', 'Log hours') }}</div>
      <div class="grid grid-4" style="align-items:end">
        <div class="field" style="margin:0;grid-column:span 2"><label>{{ T.one }}</label><UiSelect v-model="entry.project_id" :placeholder="tt('اختر', 'Select')"><option value="">—</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.number }} — {{ p.title }}</option></UiSelect></div>
        <div class="field" style="margin:0"><label>{{ tt('التاريخ', 'Date') }}</label><input v-model="entry.date" type="date" dir="ltr" /></div>
        <div class="field" style="margin:0"><label>{{ tt('عدد الساعات', 'Hours') }}</label><input v-model.number="entry.hours" type="number" min="0.25" step="0.25" dir="ltr" /></div>
      </div>
      <div class="row mt"><input v-model="entry.description" :placeholder="tt('ماذا أنجزت؟', 'What was done?')" style="flex:1;padding:9px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="add" /><label class="switch"><input v-model="entry.billable" type="checkbox" /><span /> {{ tt('قابلة للفوترة', 'Billable') }}</label><button class="btn" :disabled="!entry.project_id || !(entry.hours > 0)" @click="add"><i class="ti ti-plus" /> {{ tt('تسجيل', 'Log') }}</button></div>
    </div>
    <div class="card">
      <div class="row" style="margin-bottom:14px;flex-wrap:wrap">
        <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">{{ tt('إلى', 'to') }}</span><input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <UiSelect v-model="filt.project_id" style="min-width:160px" @change="load"><option value="">{{ tt('كل الملفات', 'All files') }}</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.title }}</option></UiSelect>
        <UiSelect v-if="people.length > 1" v-model="filt.user_id" style="min-width:130px" @change="load"><option value="">{{ tt('كل الفريق', 'Everyone') }}</option><option v-for="u in people" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect>
        <UiSelect v-model="filt.billing" style="min-width:130px" @change="load"><option value="">{{ tt('كل الحالات', 'All') }}</option><option value="unbilled">{{ tt('غير مفوترة', 'Unbilled') }}</option><option value="billed">{{ tt('مفوترة', 'Billed') }}</option></UiSelect>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ T.one }}</th><th>{{ tt('البيان', 'Description') }}</th><th>{{ tt('بواسطة', 'By') }}</th><th>{{ tt('الساعات', 'Hours') }}</th><th>{{ tt('القيمة', 'Amount') }}</th><th>{{ tt('الفوترة', 'Billing') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in d?.page.data" :key="t.id">
            <td dir="ltr" style="text-align:right">{{ t.date }}</td>
            <td><NuxtLink :to="`${projectTerms(t.project?.kind).base}/${t.project_id}`" style="color:var(--info)">{{ t.project?.title }}</NuxtLink></td>
            <td>{{ t.description || '—' }}</td><td>{{ t.user?.name }}</td><td class="num">{{ +t.hours }}</td><td class="num">{{ t.billable ? f.money(t.amount) : '—' }}</td>
            <td><NuxtLink v-if="t.invoice" :to="`/crm/invoices/${t.invoice_id}`" class="badge ok" dir="ltr">{{ t.invoice.number }}</NuxtLink><span v-else-if="t.billable" class="badge warn">{{ tt('غير مفوترة', 'Unbilled') }}</span><span v-else class="badge muted">{{ tt('غير قابلة', 'Non-billable') }}</span></td>
            <td><button v-if="!t.invoice_id && (auth.can('projects.manage') || t.user_id === auth.state.value.user?.id)" class="btn ghost sm icon" style="color:var(--primary)" @click="del(t)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.page.data.length" class="empty">{{ tt('لا توجد ساعات في هذه الفترة.', 'No hours in this period.') }}</p>
    </div>
  </div>
</template>
