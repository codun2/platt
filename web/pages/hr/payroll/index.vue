<script setup lang="ts">
definePageMeta({ title: 'الرواتب' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const d = ref<any>(null); const period = ref(thisMonth()); const err = ref(''); const generating = ref(false)
const canManage = computed(() => auth.can('hr.manage'))
async function load() { d.value = await api.get('/app/hr/payroll') }
onMounted(load)
async function generate() {
  err.value = ''; generating.value = true
  try { const p = await api.post('/app/hr/payroll/generate', { period: period.value }); navigateTo(`/hr/payroll/${p.id}`) } catch (x: any) { err.value = x.message } finally { generating.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr">الموارد البشرية</NuxtLink> › <b>الرواتب</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d?.stats?.employees ?? 0 }}</b><small>موظف يُصرف له راتب</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-calculator" /></div><div><b>{{ f.money(d?.stats?.expected) }}</b><small>الكتلة الشهرية المتوقعة (صافي)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats?.paid_total) }}</b><small>إجمالي المدفوع ({{ d?.stats?.count ?? 0 }} كشف)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar" /></div><div><b>{{ d?.stats?.last ? monthLabel(d.stats.last) : '—' }}</b><small>آخر كشف</small></div></div>
    </div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="row between" style="margin-bottom:14px">
        <div class="card-title" style="margin:0"><i class="ti ti-cash" /> كشوف الرواتب</div>
        <div v-if="canManage" class="row"><input v-model="period" type="month" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" /><button class="btn" :disabled="generating" @click="generate"><i class="ti ti-player-play" /> إنشاء كشف {{ monthLabel(period) }}</button></div>
      </div>
      <p class="muted" style="font-size:12px;margin-top:0">يُحسب الكشف من رواتب الموظفين وبدلاتهم، ويُخصم الغياب ونصف الأيام والإجازات بدون راتب تلقائياً بالأجر اليومي (الإجمالي ÷ 30). بعد المراجعة: اعتماد ثم صرف (يُرحَّل قيد الرواتب للمحاسبة).</p>
      <table class="table">
        <thead><tr><th>الرقم</th><th>الشهر</th><th>الموظفون</th><th>الأساسي</th><th>البدلات</th><th>إضافات</th><th>خصومات</th><th>الصافي</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in d?.data" :key="p.id">
            <td><NuxtLink :to="`/hr/payroll/${p.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ p.number }}</NuxtLink></td><td><b>{{ monthLabel(p.period) }}</b></td><td>{{ p.items_count }}</td>
            <td class="num">{{ f.money(p.total_basic) }}</td><td class="num">{{ f.money(p.total_allowances) }}</td><td class="num" style="color:var(--ok)">{{ f.money(p.total_additions) }}</td><td class="num" style="color:var(--primary)">{{ f.money(p.total_deductions) }}</td><td class="num"><b>{{ f.money(p.total_net) }}</b></td>
            <td><span class="badge" :class="payrollStatus[p.status]?.cls">{{ payrollStatus[p.status]?.label }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/hr/payroll/${p.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.data.length" class="empty">لم يُنشأ أي كشف بعد.</p>
    </div>
  </div>
</template>
