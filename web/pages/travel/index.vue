<script setup lang="ts">
definePageMeta({ title: 'الحج والعمرة والسفر', titleEn: 'Hajj, Umrah & Travel' })
const api = useApi(); const f = useFmt()
const trips = ref<any[]>([]); const unpaid = ref<any>(null)
onMounted(async () => { trips.value = await api.get('/app/travel/trips', { all: 1 }); unpaid.value = await api.get('/app/travel/travelers', { unpaid: 1 }) })
const upcoming = computed(() => trips.value.filter(t => t.status === 'scheduled' || t.status === 'ongoing'))
const typeLabel: any = { umrah: 'عمرة', hajj: 'حج', tourism: 'سياحة' }
const stLabel: any = { scheduled: 'مجدولة', ongoing: 'جارية', completed: 'مكتملة', cancelled: 'ملغاة' }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const sum = (k: string) => upcoming.value.reduce((s, t) => s + Number(t[k] || 0), 0)
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-plane" /></div><div><b>{{ upcoming.length }}</b><small>رحلات قادمة/جارية</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-users" /></div><div><b>{{ sum('travelers_count') }}</b><small>مسافر مسجّل</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-cash" /></div><div><b>{{ f.money(sum('collected')) }}</b><small>محصّل من {{ f.money(sum('revenue')) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-clock-dollar" /></div><div><b>{{ unpaid?.total ?? '…' }}</b><small>مسافرون لم يسددوا كاملاً</small></div></div>
    </div>
    <div class="card mt">
      <div class="card-title"><i class="ti ti-bolt" /> الوصول السريع</div>
      <div class="quick"><NuxtLink to="/travel/trips"><i class="ti ti-map-pin" />الرحلات</NuxtLink><NuxtLink to="/travel/travelers"><i class="ti ti-users" />المسافرون</NuxtLink><NuxtLink to="/travel/packages"><i class="ti ti-package" />الباقات</NuxtLink><NuxtLink to="/crm/customers"><i class="ti ti-user" />العملاء</NuxtLink></div>
    </div>
    <div class="card mt">
      <div class="card-title"><i class="ti ti-calendar" /> الرحلات القادمة</div>
      <table class="table"><thead><tr><th>الرحلة</th><th>النوع</th><th>الوجهة</th><th>المغادرة</th><th>العودة</th><th>المسافرون</th><th>الحالة</th></tr></thead>
        <tbody><tr v-for="t in upcoming" :key="t.id"><td><NuxtLink :to="`/travel/trips/${t.id}`" style="color:var(--info);font-weight:700">{{ t.name }}</NuxtLink></td><td>{{ typeLabel[t.type] }}</td><td>{{ t.destination }}</td><td>{{ f.d(t.departure_date) }}</td><td>{{ f.d(t.return_date) }}</td><td>{{ t.travelers_count }} / {{ t.capacity || '∞' }}</td><td><span class="badge" :class="stClass[t.status]">{{ stLabel[t.status] }}</span></td></tr></tbody></table>
      <p v-if="!upcoming.length" class="empty">لا توجد رحلات قادمة.</p>
    </div>
  </div>
</template>
