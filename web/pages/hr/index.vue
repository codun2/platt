<script setup lang="ts">
definePageMeta({ title: 'الموارد البشرية', titleEn: 'Human Resources' })
const api = useApi(); const f = useFmt()
const emp = ref<any>(null); const day = ref<any>(null); const leaves = ref<any>(null); const pay = ref<any>(null)
onMounted(async () => {
  emp.value = await api.get('/app/hr/employees', { all: 1 })
  try { day.value = await api.get('/app/hr/attendance/day') } catch {}
  try { leaves.value = await api.get('/app/hr/leaves', { status: 'pending' }) } catch {}
  try { pay.value = await api.get('/app/hr/payroll') } catch {}
})
</script>

<template>
  <div>
    <div class="breadcrumb"><b>الموارد البشرية</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ emp?.stats?.active ?? 0 }}</b><small>موظف على رأس العمل · {{ emp?.stats?.total ?? 0 }} إجمالاً</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-calendar-check" /></div><div><b>{{ day?.summary?.present ?? 0 }} / {{ (day?.rows?.length ?? 0) }}</b><small>حضور اليوم · غياب {{ day?.summary?.absent ?? 0 }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ leaves?.stats?.pending ?? 0 }}</b><small>طلبات إجازة بانتظار الموافقة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-cash" /></div><div><b>{{ f.money(emp?.stats?.monthly_payroll) }}</b><small>كتلة الرواتب الشهرية المتوقعة</small></div></div>
    </div>
    <div class="card">
      <div class="quick">
        <NuxtLink to="/hr/employees"><i class="ti ti-users" />الموظفون</NuxtLink>
        <NuxtLink to="/hr/employees/new"><i class="ti ti-user-plus" />موظف جديد</NuxtLink>
        <NuxtLink to="/hr/attendance"><i class="ti ti-calendar-check" />الحضور اليومي</NuxtLink>
        <NuxtLink to="/hr/attendance?tab=leaves"><i class="ti ti-beach" />الإجازات</NuxtLink>
        <NuxtLink to="/hr/leaves/new"><i class="ti ti-plus" />طلب إجازة</NuxtLink>
        <NuxtLink to="/hr/payroll"><i class="ti ti-cash" />الرواتب</NuxtLink>
      </div>
    </div>
    <div class="grid grid-2" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-beach" /> طلبات الإجازة المعلّقة</div>
        <table class="table"><thead><tr><th>الموظف</th><th>النوع</th><th>من</th><th>إلى</th><th>أيام</th></tr></thead>
          <tbody><tr v-for="l in leaves?.data" :key="l.id"><td><NuxtLink :to="`/hr/leaves/${l.id}`" style="color:var(--info);font-weight:700">{{ l.employee?.name }}</NuxtLink></td><td>{{ leaveTypes[l.type] }}</td><td>{{ f.d(l.from) }}</td><td>{{ f.d(l.to) }}</td><td>{{ l.days }}</td></tr></tbody></table>
        <p v-if="leaves && !leaves.data.length" class="empty" style="padding:16px 0">لا توجد طلبات معلّقة.</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-cash" /> آخر كشوف الرواتب</div>
        <table class="table"><thead><tr><th>الشهر</th><th>الموظفون</th><th>الصافي</th><th>الحالة</th></tr></thead>
          <tbody><tr v-for="p in (pay?.data || []).slice(0, 6)" :key="p.id"><td><NuxtLink :to="`/hr/payroll/${p.id}`" style="color:var(--info);font-weight:700">{{ monthLabel(p.period) }}</NuxtLink></td><td>{{ p.items_count }}</td><td class="num"><b>{{ f.money(p.total_net) }}</b></td><td><span class="badge" :class="payrollStatus[p.status]?.cls">{{ payrollStatus[p.status]?.label }}</span></td></tr></tbody></table>
        <p v-if="pay && !pay.data.length" class="empty" style="padding:16px 0">لم يُنشأ كشف رواتب بعد.</p>
      </div>
    </div>
  </div>
</template>
