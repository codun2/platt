<script setup lang="ts">
definePageMeta({ title: 'الموظفون', titleEn: 'Employees' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const pg = ref(1)
const filt = reactive({ q: '', department: '', status: '' })
const canManage = computed(() => auth.can('hr.manage'))
async function load(p = 1) { pg.value = p; page.value = await api.get('/app/hr/employees', { ...filt, page: p }) }
onMounted(load)
async function remove(e: any) { if (!confirm(tt(`حذف الموظف ${e.name}؟`,`Delete employee ${e.name}?`))) return; try { const r = await api.del(`/app/hr/employees/${e.id}`); if (r.deactivated) alert(r.message); await load(pg.value) } catch (x: any) { alert(x.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/hr">{{ tt('الموارد البشرية','HR') }}</NuxtLink> › <b>{{ tt('الموظفون','Employees') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ page?.stats?.total ?? 0 }}</b><small>{{ tt('إجمالي الموظفين','Total employees') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-user-check" /></div><div><b>{{ page?.stats?.active ?? 0 }}</b><small>{{ tt('على رأس العمل','Active') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ page?.stats?.on_leave ?? 0 }}</b><small>{{ tt('في إجازة','On leave') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-cash" /></div><div><b>{{ f.money(page?.stats?.monthly_payroll) }}</b><small>{{ tt('الرواتب الشهرية (صافي متوقع)','Monthly payroll (expected net)') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('اسم / رقم وظيفي / هاتف / هوية','Name / ID / phone')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.department" style="min-width:140px" @change="load()"><option value="">{{ tt('كل الأقسام','All departments') }}</option><option v-for="d in page?.stats?.departments" :key="d" :value="d">{{ d }}</option></UiSelect>
          <UiSelect v-model="filt.status" @change="load()"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option v-for="(s, k) in employeeStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/hr/employees/new"><i class="ti ti-user-plus" /> {{ tt('موظف جديد','New employee') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الموظف','Employee') }}</th><th>{{ tt('المسمى','Title') }}</th><th>{{ tt('القسم','Department') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('التعيين','Hired') }}</th><th>{{ tt('الهاتف','Phone') }}</th><th>{{ tt('الإجمالي','Gross') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(e, i) in page?.data" :key="e.id">
            <td class="muted">{{ i + 1 }}</td>
            <td><NuxtLink :to="`/hr/employees/${e.id}`" style="color:var(--info);font-weight:700">{{ e.name }}</NuxtLink><small v-if="e.code" class="muted" dir="ltr"> {{ e.code }}</small></td>
            <td>{{ e.job_title || '—' }}</td><td>{{ e.department || '—' }}</td><td>{{ employmentTypes[e.employment_type] }}</td><td>{{ f.d(e.hire_date) }}</td>
            <td dir="ltr" style="text-align:right">{{ e.phone || '—' }}</td><td class="num"><b>{{ f.money(e.gross_salary) }}</b></td>
            <td><span class="badge" :class="employeeStatus[e.status]?.cls">{{ employeeStatus[e.status]?.label }}</span></td>
            <td class="row" style="gap:4px"><NuxtLink class="btn ghost sm icon" :to="`/hr/employees/${e.id}`"><i class="ti ti-eye" /></NuxtLink><template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/hr/employees/${e.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(e)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا يوجد موظفون.','No employees.') }}</p>
    </div>
  </div>
</template>
