<script setup lang="ts">
definePageMeta({ title: 'الطلاب', titleEn: 'Students' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const d = ref<any>(null); const classes = ref<any[]>([])
const filt = reactive({ q: '', class_id: String(route.query.class_id || ''), status: 'active', owing: false, page: 1 })
async function load() { d.value = await api.get('/app/education/students', { ...filt, owing: filt.owing ? 1 : '' }) }
onMounted(async () => { await load(); classes.value = await api.get('/app/education/classes') })
const bal = (s: any) => +(s.billed || 0) - +(s.paid || 0)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/education">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('الطلاب', 'Students') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d?.stats.active ?? 0 }}</b><small>{{ tt('طالب منتظم', 'Active students') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:#0284c7"><i class="ti ti-gender-male" /></div><div><b>{{ d?.stats.boys ?? 0 }}</b><small>{{ tt('بنين', 'Boys') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:#db2777"><i class="ti ti-gender-female" /></div><div><b>{{ d?.stats.girls ?? 0 }}</b><small>{{ tt('بنات', 'Girls') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-user-question" /></div><div><b>{{ d?.stats.no_guardian ?? 0 }}</b><small>{{ tt('بدون ولي أمر (لا تُصدر لهم رسوم)', 'No guardian (no fees)') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('اسم / رقم / هوية / ولي أمر', 'Name / no. / ID / guardian')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="filt.page = 1; load()" />
          <UiSelect v-model="filt.class_id" style="min-width:150px" @change="filt.page = 1; load()"><option value="">{{ tt('كل الصفوف', 'All classes') }}</option><option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <UiSelect v-model="filt.status" style="min-width:110px" @change="filt.page = 1; load()"><option value="all">{{ tt('الكل', 'All') }}</option><option v-for="(s, k) in studentStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <label class="switch"><input v-model="filt.owing" type="checkbox" @change="load" /><span /> {{ tt('عليهم رسوم', 'Owing fees') }}</label>
        </div>
        <NuxtLink v-if="auth.can('education.manage')" class="btn" to="/education/students/new"><i class="ti ti-user-plus" /> {{ tt('تسجيل طالب', 'Enroll student') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الطالب', 'Student') }}</th><th>{{ tt('الصف', 'Class') }}</th><th>{{ tt('ولي الأمر', 'Guardian') }}</th><th>{{ tt('الرسوم', 'Fees') }}</th><th>{{ tt('المتبقي', 'Balance') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
        <tbody>
          <tr v-for="s in d?.page.data" :key="s.id">
            <td><NuxtLink :to="`/education/students/${s.id}`" style="color:var(--info);font-weight:700">{{ s.name }}</NuxtLink><br /><small class="muted" dir="ltr">{{ s.student_no }}</small></td>
            <td>{{ s.edu_class?.name || '—' }}</td>
            <td>{{ s.guardian?.name || '—' }}<br /><small class="muted" dir="ltr">{{ s.guardian?.phone }}</small></td>
            <td class="num">{{ f.money(s.billed || 0) }}</td>
            <td class="num"><b :style="bal(s) > 0 ? 'color:var(--primary)' : 'color:var(--ok)'">{{ f.money(bal(s)) }}</b></td>
            <td><span class="badge" :class="studentStatus[s.status]?.cls">{{ studentStatus[s.status]?.label }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.page.data.length" class="empty">{{ tt('لا يوجد طلاب مطابقون.', 'No matching students.') }}</p>
      <div v-if="d && d.page.last_page > 1" class="row mt" style="justify-content:center"><button class="btn ghost sm" :disabled="filt.page <= 1" @click="filt.page--; load()">‹</button><span class="muted">{{ d.page.current_page }} / {{ d.page.last_page }}</span><button class="btn ghost sm" :disabled="filt.page >= d.page.last_page" @click="filt.page++; load()">›</button></div>
    </div>
  </div>
</template>
