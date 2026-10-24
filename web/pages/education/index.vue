<script setup lang="ts">
definePageMeta({ title: 'نظرة عامة', titleEn: 'Overview' })
const api = useApi(); const f = useFmt()
const d = ref<any>(null)
onMounted(async () => { d.value = await api.get('/app/education/summary') })
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d?.stats.students ?? '…' }}</b><small>{{ tt('طالب منتظم', 'Active students') }} · {{ d?.stats.classes ?? 0 }} {{ tt('شعبة', 'classes') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-user-check" /></div><div><b>{{ d?.stats.attendance_rate != null ? d.stats.attendance_rate + '%' : '—' }}</b><small>{{ tt('حضور اليوم', 'Attendance today') }} ({{ d?.stats.absent_today ?? 0 }} {{ tt('غائب', 'absent') }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(d?.stats.fees_paid) }}</b><small>{{ tt('محصّل من', 'Collected of') }} {{ f.money(d?.stats.fees_billed) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ f.money(d?.stats.fees_overdue) }}</b><small>{{ tt('رسوم متأخرة', 'Overdue fees') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="row between">
        <div class="card-title" style="margin:0"><i class="ti ti-bolt" /> {{ tt('إجراءات سريعة', 'Quick actions') }}</div>
        <div class="row">
          <NuxtLink class="btn" to="/education/attendance"><i class="ti ti-checklist" /> {{ tt('تحضير اليوم', 'Take attendance') }}</NuxtLink>
          <NuxtLink class="btn ghost" to="/education/students/new"><i class="ti ti-user-plus" /> {{ tt('تسجيل طالب', 'Enroll student') }}</NuxtLink>
          <NuxtLink class="btn ghost" to="/education/fees"><i class="ti ti-file-invoice" /> {{ tt('إصدار الأقساط', 'Issue fees') }}</NuxtLink>
        </div>
      </div>
    </div>
    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-chalkboard" /> {{ tt('الصفوف والشعب', 'Classes') }}</div>
        <div v-for="c in d?.classes" :key="c.id" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div class="row between"><NuxtLink :to="`/education/students?class_id=${c.id}`" style="font-weight:700;color:var(--info)">{{ c.name }}</NuxtLink><small class="muted">{{ c.students_count }}<template v-if="c.capacity"> / {{ c.capacity }}</template> {{ tt('طالب', 'students') }}</small></div>
          <div v-if="c.capacity" style="height:5px;background:var(--line);border-radius:4px;margin-top:5px;overflow:hidden"><div :style="{ width: Math.min(100, c.students_count * 100 / c.capacity) + '%', height: '100%', background: c.students_count >= c.capacity ? 'var(--primary)' : 'var(--ok)' }" /></div>
        </div>
        <p v-if="d && !d.classes.length" class="empty">{{ tt('ابدأ بإضافة الصفوف والشعب.', 'Start by adding classes.') }} <NuxtLink to="/education/classes" style="color:var(--info)">{{ tt('إضافة', 'Add') }}</NuxtLink></p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-checklist" /> {{ tt('شعب لم يُسجَّل حضورها اليوم', 'Classes not marked today') }}</div>
        <div v-for="c in d?.unmarked" :key="c.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)"><b>{{ c.name }}</b><NuxtLink :to="`/education/attendance?class_id=${c.id}`" class="btn ghost sm">{{ tt('تحضير', 'Mark') }}</NuxtLink></div>
        <p v-if="d && !d.unmarked.length" class="empty">{{ tt('تم تسجيل حضور كل الشعب اليوم 👏', 'All classes marked today 👏') }}</p>
      </div>
    </div>
  </div>
</template>
