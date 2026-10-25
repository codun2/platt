<script setup lang="ts">
/** Cases / projects list (kind decides wording and columns). */
const props = defineProps<{ kind: 'case' | 'project' }>()
const api = useApi(); const auth = useAuth(); const f = useFmt()
const T = computed(() => projectTerms(props.kind))
const d = ref<any>(null); const people = ref<any[]>([])
const filt = reactive({ q: '', status: 'active', lead_id: '', page: 1 })
const canManage = computed(() => auth.can('projects.manage'))
async function load() { d.value = await api.get('/app/projects/items', { ...filt, kind: props.kind }) }
onMounted(async () => { await load(); people.value = await api.get('/app/projects/people') })
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/projects">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ T.many }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i :class="`ti ${T.icon}`" /></div><div><b>{{ d?.stats.active ?? 0 }}</b><small>{{ T.isCase ? tt('قضايا جارية', 'Active cases') : tt('مشاريع جارية', 'Active projects') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--muted)"><i class="ti ti-archive" /></div><div><b>{{ d?.stats.closed ?? 0 }}</b><small>{{ tt('مغلقة', 'Closed') }}</small></div></div>
      <div v-if="T.isCase" class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trophy" /></div><div><b>{{ d?.stats.won ?? 0 }}</b><small>{{ tt('قضايا كُسبت', 'Cases won') }}</small></div></div>
      <div v-else class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats.value) }}</b><small>{{ tt('قيمة العقود الجارية', 'Active contract value') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ f.money((d?.page.data || []).reduce((n: number, p: any) => n + +(p.unbilled || 0), 0)) }}</b><small>{{ tt('أتعاب غير مفوترة (هذه الصفحة)', 'Unbilled fees (this page)') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="T.isCase ? tt('موضوع / رقم / خصم / موكّل', 'Subject / no. / opponent / client') : tt('اسم / رقم / عميل', 'Name / no. / client')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="filt.page = 1; load()" />
          <UiSelect v-model="filt.status" style="min-width:130px" @change="filt.page = 1; load()"><option value="active">{{ tt('الجارية', 'Active') }}</option><option value="all">{{ tt('الكل', 'All') }}</option><option v-for="(s, k) in projectStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <UiSelect v-if="people.length > 1" v-model="filt.lead_id" style="min-width:130px" @change="filt.page = 1; load()"><option value="">{{ tt('كل المسؤولين', 'Everyone') }}</option><option v-for="u in people" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" :to="`${T.base}/new`"><i class="ti ti-plus" /> {{ T.newOne }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ T.one }}</th><th>{{ T.client }}</th><th v-if="T.isCase">{{ tt('المحكمة / المرحلة', 'Court / stage') }}</th><th v-else>{{ tt('الإنجاز', 'Progress') }}</th><th>{{ tt('المسؤول', 'Lead') }}</th><th>{{ tt('مهام مفتوحة', 'Open tasks') }}</th><th>{{ tt('الساعات', 'Hours') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
        <tbody>
          <tr v-for="p in d?.page.data" :key="p.id">
            <td><NuxtLink :to="`${T.base}/${p.id}`" style="color:var(--info);font-weight:700">{{ p.title }}</NuxtLink><br /><small class="muted" dir="ltr">{{ p.number }}</small><small v-if="p.court_case_no" class="muted"> · {{ tt('رقم', 'No.') }} <span dir="ltr">{{ p.court_case_no }}</span></small></td>
            <td>{{ p.customer?.name || '—' }}</td>
            <td v-if="T.isCase">{{ p.court || '—' }}<br /><small class="muted">{{ caseStages[p.stage] || '' }}<template v-if="p.case_type"> · {{ caseTypes[p.case_type] }}</template></small></td>
            <td v-else style="min-width:110px"><div style="height:6px;background:var(--line);border-radius:4px;overflow:hidden"><div :style="{ width: (p.progress || 0) + '%', height: '100%', background: 'var(--ok)' }" /></div><small class="muted">{{ p.progress || 0 }}%</small></td>
            <td>{{ p.lead?.name || '—' }}</td>
            <td>{{ p.open_tasks || 0 }}</td>
            <td class="num">{{ +(p.hours || 0) }}<br /><small v-if="+p.unbilled" class="muted">{{ tt('غير مفوتر', 'unbilled') }} {{ f.money(p.unbilled) }}</small></td>
            <td><span class="badge" :class="projectStatus[p.status]?.cls">{{ projectStatus[p.status]?.label }}</span> <span v-if="['high', 'urgent'].includes(p.priority)" class="badge" :class="priorities[p.priority].cls">{{ priorities[p.priority].label }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.page.data.length" class="empty">{{ T.isCase ? tt('لا توجد قضايا مطابقة.', 'No matching cases.') : tt('لا توجد مشاريع مطابقة.', 'No matching projects.') }}</p>
      <UiPager :p="d?.page" @go="(n: number) => { filt.page = n; load() }" />
    </div>
  </div>
</template>
