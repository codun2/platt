<script setup lang="ts">
definePageMeta({ title: 'الحج والعمرة والسفر', titleEn: 'Hajj, Umrah & Travel' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const trips = ref<any[]>([]); const unpaid = ref<any>(null)
onMounted(async () => { trips.value = await api.get('/app/travel/trips', { all: 1 }); unpaid.value = await api.get('/app/travel/travelers', { unpaid: 1 }) })
const upcoming = computed(() => trips.value.filter(t => t.status === 'scheduled' || t.status === 'ongoing'))
const typeLabel: any = { umrah: L('عمرة','Umrah'), hajj: L('حج','Hajj'), tourism: L('سياحة','Tourism') }
const stLabel: any = { scheduled: L('مجدولة','Scheduled'), ongoing: L('جارية','Ongoing'), completed: L('مكتملة','Completed'), cancelled: L('ملغاة','Cancelled') }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const sum = (k: string) => upcoming.value.reduce((s, t) => s + Number(t[k] || 0), 0)
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-plane" /></div><div><b>{{ upcoming.length }}</b><small>{{ tt('رحلات قادمة/جارية','Upcoming/ongoing trips') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-users" /></div><div><b>{{ sum('travelers_count') }}</b><small>{{ tt('مسافر مسجّل','Registered travelers') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-cash" /></div><div><b>{{ f.money(sum('collected')) }}</b><small>محصّل من {{ f.money(sum('revenue')) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-clock-dollar" /></div><div><b>{{ unpaid?.total ?? '…' }}</b><small>{{ tt('مسافرون لم يسددوا كاملاً','Travelers not fully paid') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="card-title"><i class="ti ti-bolt" /> {{ tt('الوصول السريع','Quick access') }}</div>
      <div class="quick"><NuxtLink to="/travel/trips"><i class="ti ti-map-pin" />{{ tt('الرحلات','Trips') }}</NuxtLink><NuxtLink to="/travel/travelers"><i class="ti ti-users" />{{ tt('المسافرون','Travelers') }}</NuxtLink><NuxtLink to="/travel/packages"><i class="ti ti-package" />{{ tt('الباقات','Packages') }}</NuxtLink><NuxtLink to="/crm/customers"><i class="ti ti-user" />{{ tt('العملاء','Customers') }}</NuxtLink></div>
    </div>
    <div class="card mt">
      <div class="card-title"><i class="ti ti-calendar" /> {{ tt('الرحلات القادمة','Upcoming trips') }}</div>
      <table class="table"><thead><tr><th>{{ tt('الرحلة','Trip') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('الوجهة','Destination') }}</th><th>{{ tt('المغادرة','Departure') }}</th><th>{{ tt('العودة','Return') }}</th><th>{{ tt('المسافرون','Travelers') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
        <tbody><tr v-for="t in upcoming" :key="t.id"><td><NuxtLink :to="`/travel/trips/${t.id}`" style="color:var(--info);font-weight:700">{{ t.name }}</NuxtLink></td><td>{{ typeLabel[t.type] }}</td><td>{{ t.destination }}</td><td>{{ f.d(t.departure_date) }}</td><td>{{ f.d(t.return_date) }}</td><td>{{ t.travelers_count }} / {{ t.capacity || '∞' }}</td><td><span class="badge" :class="stClass[t.status]">{{ stLabel[t.status] }}</span></td></tr></tbody></table>
      <p v-if="!upcoming.length" class="empty">{{ tt('لا توجد رحلات قادمة.','No upcoming trips.') }}</p>
    </div>
  </div>
</template>
