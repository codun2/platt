<script setup lang="ts">
definePageMeta({ title: 'الجلسات والمهام', titleEn: 'Hearings & Tasks' })
const api = useApi(); const auth = useAuth()
const kind = useProjectKind(); const T = computed(() => projectTerms(kind.value))
const d = ref<any>(null); const people = ref<any[]>([]); const projects = ref<any[]>([])
const filt = reactive({ scope: 'upcoming', kind: '', assigned_to: '', mine: false, page: 1 })
const dialog = ref<any>(null)
const scopes = computed(() => [['today', tt('اليوم', 'Today')], ['week', tt('هذا الأسبوع', 'This week')], ['upcoming', tt('كل القادمة', 'All upcoming')], ['overdue', tt('فات موعدها', 'Overdue')], ['done', tt('المغلقة', 'Closed')]])
async function load() { d.value = await api.get('/app/projects/tasks', { ...filt, mine: filt.mine ? 1 : '' }) }
onMounted(async () => { await load(); people.value = await api.get('/app/projects/people'); projects.value = (await api.get('/app/projects/items', { kind: kind.value })).page.data })
function setScope(s: string) { filt.scope = s; filt.page = 1; load() }
// Group the agenda by day for readability.
const groups = computed(() => {
  const out: { day: string; items: any[] }[] = []
  for (const t of d.value?.data || []) {
    const day = t.due_at ? fmtWhen(t.due_at).split(' · ')[0] : tt('بدون موعد', 'No date')
    const g = out.find(x => x.day === day); g ? g.items.push(t) : out.push({ day, items: [t] })
  }
  return out
})
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/projects">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ T.isCase ? tt('الجلسات والمهام', 'Hearings & tasks') : tt('المهام', 'Tasks') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="chips"><span v-for="[k, l] in scopes" :key="k" class="chip" :class="{ on: filt.scope === k }" @click="setScope(k)">{{ l }}</span></div>
        <div class="row">
          <UiSelect v-if="T.isCase" v-model="filt.kind" style="min-width:110px" @change="load"><option value="">{{ tt('الكل', 'All') }}</option><option value="hearing">{{ tt('الجلسات', 'Hearings') }}</option><option value="task">{{ tt('المهام', 'Tasks') }}</option></UiSelect>
          <label class="switch"><input v-model="filt.mine" type="checkbox" @change="load" /><span /> {{ tt('المسندة لي', 'Mine') }}</label>
          <button class="btn" @click="dialog = { mode: 'edit', kind: T.isCase ? 'hearing' : 'task' }"><i class="ti ti-plus" /> {{ T.isCase ? tt('جلسة / مهمة', 'Hearing / task') : tt('مهمة', 'Task') }}</button>
        </div>
      </div>
      <div v-for="g in groups" :key="g.day" style="margin-bottom:14px">
        <div class="muted" style="font-weight:700;font-size:13px;margin-bottom:6px"><i class="ti ti-calendar" /> {{ g.day }}</div>
        <table class="table agenda">
          <colgroup><col style="width:70px" /><col /><col style="width:15%" /><col style="width:14%" /><col style="width:16%" /><col style="width:170px" /></colgroup>
          <tbody>
            <tr v-for="t in g.items" :key="t.id" :style="isOverdue(t) ? 'background:var(--primary-50)' : ''">
              <td style="width:70px" dir="ltr"><b>{{ t.due_at ? t.due_at.slice(11, 16) : '—' }}</b></td>
              <td><span class="badge" :class="t.kind === 'hearing' ? 'info' : 'muted'">{{ t.kind === 'hearing' ? tt('جلسة', 'Hearing') : tt('مهمة', 'Task') }}</span> <b>{{ t.title }}</b><br />
                <NuxtLink :to="`${projectTerms(t.project?.kind).base}/${t.project_id}`" style="color:var(--info);font-size:12px">{{ t.project?.title }}</NuxtLink><small v-if="t.project?.court" class="muted"> · {{ t.project.court }}</small></td>
              <td>{{ t.location || '' }}</td><td>{{ t.assignee?.name || '—' }}</td>
              <td><span class="badge" :class="taskStatus[t.status]?.cls">{{ taskStatus[t.status]?.label }}</span><p v-if="t.outcome" class="muted" style="margin:4px 0 0;font-size:12px">{{ t.outcome }}</p></td>
              <td class="row"><template v-if="['todo', 'doing'].includes(t.status)"><button class="btn ok sm" @click="dialog = { mode: 'complete', task: t }"><i class="ti ti-check" /> {{ t.kind === 'hearing' ? tt('النتيجة', 'Outcome') : tt('إنجاز', 'Done') }}</button><button class="btn ghost sm icon" @click="dialog = { mode: 'edit', task: t }"><i class="ti ti-pencil" /></button></template></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="d && !d.data.length" class="empty">{{ filt.scope === 'overdue' ? tt('لا شيء متأخر 🎉', 'Nothing overdue 🎉') : tt('لا توجد مواعيد.', 'Nothing here.') }}</p>
      <UiPager :p="d" @go="(n: number) => { filt.page = n; load() }" />
    </div>
    <TaskDialog v-if="dialog" :mode="dialog.mode" :task="dialog.task" :projects="projects" :people="people" :default-kind="dialog.kind" @close="dialog = null" @saved="load" />
  </div>
</template>

<style scoped>
/* Same column widths in every day group so rows line up across the agenda. */
.agenda { table-layout: fixed; }
.agenda td { overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
</style>
