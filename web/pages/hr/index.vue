<script setup lang="ts">
definePageMeta({ title: 'الموارد البشرية', titleEn: 'Human Resources' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
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
    <div class="breadcrumb"><b>{{ tt('الموارد البشرية','Human Resources') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ emp?.stats?.active ?? 0 }}</b><small>موظف على رأس العمل · {{ emp?.stats?.total ?? 0 }} إجمالاً</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-calendar-check" /></div><div><b>{{ day?.summary?.present ?? 0 }} / {{ (day?.rows?.length ?? 0) }}</b><small>حضور اليوم · غياب {{ day?.summary?.absent ?? 0 }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ leaves?.stats?.pending ?? 0 }}</b><small>{{ tt('طلبات إجازة بانتظار الموافقة','Leave requests awaiting approval') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-cash" /></div><div><b>{{ f.money(emp?.stats?.monthly_payroll) }}</b><small>{{ tt('كتلة الرواتب الشهرية المتوقعة','Expected monthly payroll') }}</small></div></div>
    </div>
    <div class="card">
      <div class="quick">
        <NuxtLink to="/hr/employees"><i class="ti ti-users" />{{ tt('الموظفون','Employees') }}</NuxtLink>
        <NuxtLink to="/hr/employees/new"><i class="ti ti-user-plus" />{{ tt('موظف جديد','New employee') }}</NuxtLink>
        <NuxtLink to="/hr/attendance"><i class="ti ti-calendar-check" />{{ tt('الحضور اليومي','Daily attendance') }}</NuxtLink>
        <NuxtLink to="/hr/attendance?tab=leaves"><i class="ti ti-beach" />{{ tt('الإجازات','Leaves') }}</NuxtLink>
        <NuxtLink to="/hr/leaves/new"><i class="ti ti-plus" />{{ tt('طلب إجازة','New leave') }}</NuxtLink>
        <NuxtLink to="/hr/payroll"><i class="ti ti-cash" />{{ tt('الرواتب','Payroll') }}</NuxtLink>
      </div>
    </div>
    <div class="grid grid-2" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-beach" /> {{ tt('طلبات الإجازة المعلّقة','Pending leave requests') }}</div>
        <table class="table"><thead><tr><th>{{ tt('الموظف','Employee') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('من','From') }}</th><th>{{ tt('إلى','To') }}</th><th>{{ tt('أيام','Days') }}</th></tr></thead>
          <tbody><tr v-for="l in leaves?.data" :key="l.id"><td><NuxtLink :to="`/hr/leaves/${l.id}`" style="color:var(--info);font-weight:700">{{ l.employee?.name }}</NuxtLink></td><td>{{ leaveTypes[l.type] }}</td><td>{{ f.d(l.from) }}</td><td>{{ f.d(l.to) }}</td><td>{{ l.days }}</td></tr></tbody></table>
        <p v-if="leaves && !leaves.data.length" class="empty" style="padding:16px 0">{{ tt('لا توجد طلبات معلّقة.','No pending requests.') }}</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-cash" /> {{ tt('آخر كشوف الرواتب','Recent payrolls') }}</div>
        <table class="table"><thead><tr><th>{{ tt('الشهر','Month') }}</th><th>{{ tt('الموظفون','Employees') }}</th><th>{{ tt('الصافي','Net') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody><tr v-for="p in (pay?.data || []).slice(0, 6)" :key="p.id"><td><NuxtLink :to="`/hr/payroll/${p.id}`" style="color:var(--info);font-weight:700">{{ monthLabel(p.period) }}</NuxtLink></td><td>{{ p.items_count }}</td><td class="num"><b>{{ f.money(p.total_net) }}</b></td><td><span class="badge" :class="payrollStatus[p.status]?.cls">{{ payrollStatus[p.status]?.label }}</span></td></tr></tbody></table>
        <p v-if="pay && !pay.data.length" class="empty" style="padding:16px 0">{{ tt('لم يُنشأ كشف رواتب بعد.','No payroll created yet.') }}</p>
      </div>
    </div>
  </div>
</template>
