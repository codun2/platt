<script setup lang="ts">
definePageMeta({ title: 'نظرة عامة', titleEn: 'Overview' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const kind = useProjectKind(); const T = computed(() => projectTerms(kind.value))
const d = ref<any>(null); const people = ref<any[]>([]); const projects = ref<any[]>([])
const dialog = ref<any>(null)
async function load() { d.value = await api.get('/app/projects/summary') }
onMounted(async () => { await load(); people.value = await api.get('/app/projects/people'); projects.value = (await api.get('/app/projects/items', { kind: kind.value })).page.data })
const canManage = computed(() => auth.can('projects.manage'))
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i :class="`ti ${T.icon}`" /></div><div><b>{{ d?.stats.open ?? '…' }}</b><small>{{ T.isCase ? tt('قضايا جارية', 'Active cases') : tt('مشاريع جارية', 'Active projects') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b>{{ d?.stats.hearings_week ?? '…' }}</b><small>{{ T.isCase ? tt('جلسات خلال 7 أيام', 'Hearings in 7 days') : tt('مواعيد خلال 7 أيام', 'Due in 7 days') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-clock" /></div><div><b>{{ d?.stats.hours_month ?? '…' }}</b><small>{{ tt('ساعات هذا الشهر', 'Hours this month') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(d?.stats.unbilled_amount) }}</b><small>{{ tt('أتعاب غير مفوترة', 'Unbilled fees') }} ({{ d?.stats.unbilled_hours ?? 0 }} {{ tt('ساعة', 'h') }})</small></div></div>
    </div>

    <div class="card mt">
      <div class="row between">
        <div class="card-title" style="margin:0"><i class="ti ti-bolt" /> {{ tt('إجراءات سريعة', 'Quick actions') }}</div>
        <div class="row">
          <NuxtLink v-if="canManage" class="btn" :to="`${T.base}/new`"><i class="ti ti-plus" /> {{ T.newOne }}</NuxtLink>
          <button class="btn ghost" @click="dialog = { mode: 'edit', kind: T.isCase ? 'hearing' : 'task' }"><i class="ti ti-calendar-plus" /> {{ T.isCase ? tt('جلسة / مهمة', 'Hearing / task') : tt('مهمة', 'Task') }}</button>
          <NuxtLink class="btn ghost" to="/projects/timesheets"><i class="ti ti-clock-plus" /> {{ tt('تسجيل ساعات', 'Log hours') }}</NuxtLink>
        </div>
      </div>
    </div>

    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-calendar-event" /> {{ T.isCase ? tt('الجلسات والمواعيد القادمة', 'Upcoming hearings') : tt('المواعيد القادمة', 'Upcoming') }}</div>
        <div v-for="t in d?.upcoming" :key="t.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div><span class="badge" :class="t.kind === 'hearing' ? 'info' : 'muted'">{{ t.kind === 'hearing' ? tt('جلسة', 'Hearing') : tt('مهمة', 'Task') }}</span> <b>{{ t.title }}</b><br />
            <NuxtLink :to="`${projectTerms(t.project?.kind).base}/${t.project_id}`" class="muted" style="font-size:12px">{{ t.project?.title }}</NuxtLink><small v-if="t.location" class="muted"> · {{ t.location }}</small></div>
          <div style="text-align:left"><small><b>{{ fmtWhen(t.due_at) }}</b></small><br /><small class="muted">{{ t.assignee?.name }}</small></div>
        </div>
        <p v-if="d && !d.upcoming.length" class="empty">{{ tt('لا توجد مواعيد قادمة.', 'Nothing upcoming.') }}</p>
        <NuxtLink to="/projects/tasks" class="btn ghost sm mt">{{ tt('كل المواعيد', 'Full agenda') }}</NuxtLink>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-triangle" style="color:var(--primary)" /> {{ tt('فات موعدها ولم تُغلق', 'Overdue, not closed') }}</div>
        <div v-for="t in d?.overdue" :key="t.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div><b>{{ t.title }}</b><br /><NuxtLink :to="`${projectTerms(t.project?.kind).base}/${t.project_id}`" class="muted" style="font-size:12px">{{ t.project?.title }}</NuxtLink></div>
          <div class="row"><small style="color:var(--primary)">{{ fmtWhen(t.due_at) }}</small><button class="btn ok sm" @click="dialog = { mode: 'complete', task: t }">{{ t.kind === 'hearing' ? tt('النتيجة', 'Outcome') : tt('إنجاز', 'Done') }}</button></div>
        </div>
        <p v-if="d && !d.overdue.length" class="empty">{{ tt('لا شيء متأخر 🎉', 'Nothing overdue 🎉') }}</p>
      </div>
    </div>
    <TaskDialog v-if="dialog" :mode="dialog.mode" :task="dialog.task" :projects="projects" :people="people" :default-kind="dialog.kind" @close="dialog = null" @saved="load" />
  </div>
</template>
