<script setup lang="ts">
definePageMeta({ title: 'الرواتب', titleEn: 'Payroll' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const d = ref<any>(null); const period = ref(thisMonth()); const err = ref(''); const generating = ref(false)
const canManage = computed(() => auth.can('hr.manage'))
async function load() { d.value = await api.get('/app/hr/payroll') }
// Period picker: 12 months back → 2 ahead; months that already have a run are marked.
const periods = computed(() => {
  const now = new Date(); const out: string[] = []
  for (let i = 2; i >= -12; i--) { const x = new Date(now.getFullYear(), now.getMonth() + i, 1); out.push(`${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}`) }
  return out
})
const done = computed(() => new Set((d.value?.data || []).map((p: any) => p.period)))
onMounted(load)
async function generate() {
  err.value = ''; generating.value = true
  try { const p = await api.post('/app/hr/payroll/generate', { period: period.value }); navigateTo(`/hr/payroll/${p.id}`) } catch (x: any) { err.value = x.message } finally { generating.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr">{{ tt('الموارد البشرية','HR') }}</NuxtLink> › <b>{{ tt('الرواتب','Payroll') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d?.stats?.employees ?? 0 }}</b><small>{{ tt('موظف يُصرف له راتب','Paid employees') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-calculator" /></div><div><b>{{ f.money(d?.stats?.expected) }}</b><small>{{ tt('الكتلة الشهرية المتوقعة (صافي)','Expected monthly total (net)') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats?.paid_total) }}</b><small>إجمالي المدفوع ({{ d?.stats?.count ?? 0 }} كشف)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar" /></div><div><b>{{ d?.stats?.last ? monthLabel(d.stats.last) : '—' }}</b><small>{{ tt('آخر كشف','Last run') }}</small></div></div>
    </div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="row between" style="margin-bottom:14px">
        <div class="card-title" style="margin:0"><i class="ti ti-cash" /> {{ tt('كشوف الرواتب','Payroll runs') }}</div>
        <div v-if="canManage" class="row"><UiSelect v-model="period" style="min-width:190px"><option v-for="m in periods" :key="m" :value="m">{{ monthLabel(m) }}{{ done.has(m) ? ' — ' + tt('صادر', 'issued') : '' }}</option></UiSelect><button class="btn" :disabled="generating" @click="generate"><i class="ti ti-player-play" /> {{ tt('إنشاء كشف','Generate') }} {{ monthLabel(period) }}</button></div>
      </div>
      <p class="muted" style="font-size:12px;margin-top:0">{{ tt('يُحسب الكشف من رواتب الموظفين وبدلاتهم، ويُخصم الغياب ونصف الأيام والإجازات بدون راتب تلقائياً بالأجر اليومي (الإجمالي ÷ 30). بعد المراجعة: اعتماد ثم صرف (يُرحَّل قيد الرواتب للمحاسبة).','Computed from salaries and allowances; absences, half-days and unpaid leave are auto-deducted at the daily rate (gross ÷ 30). After review: approve then pay (posts a payroll journal entry).') }}</p>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('الشهر','Month') }}</th><th>{{ tt('الموظفون','Employees') }}</th><th>{{ tt('الأساسي','Basic') }}</th><th>{{ tt('البدلات','Allow.') }}</th><th>{{ tt('إضافات','Additions') }}</th><th>{{ tt('خصومات','Deductions') }}</th><th>{{ tt('الصافي','Net') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in d?.data" :key="p.id">
            <td><NuxtLink :to="`/hr/payroll/${p.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ p.number }}</NuxtLink></td><td><b>{{ monthLabel(p.period) }}</b></td><td>{{ p.items_count }}</td>
            <td class="num">{{ f.money(p.total_basic) }}</td><td class="num">{{ f.money(p.total_allowances) }}</td><td class="num" style="color:var(--ok)">{{ f.money(p.total_additions) }}</td><td class="num" style="color:var(--primary)">{{ f.money(p.total_deductions) }}</td><td class="num"><b>{{ f.money(p.total_net) }}</b></td>
            <td><span class="badge" :class="payrollStatus[p.status]?.cls">{{ payrollStatus[p.status]?.label }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/hr/payroll/${p.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.data.length" class="empty">{{ tt('لم يُنشأ أي كشف بعد.','No payroll created yet.') }}</p>
    </div>
  </div>
</template>
