<script setup lang="ts">
definePageMeta({ title: 'تقويم الحجوزات', titleEn: 'Bookings Calendar' })
const api = useApi(); const f = useFmt(); const { tt, isEn } = useI18n()
const cur = ref(new Date()); const events = ref<any[]>([]); const units = ref<any[]>([]); const unitId = ref('')
const stLabel: any = { pending: tt('معلّق','Pending'), confirmed: tt('مؤكد','Confirmed'), completed: tt('مكتمل','Completed') }
const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const first = computed(() => new Date(cur.value.getFullYear(), cur.value.getMonth(), 1))
const last = computed(() => new Date(cur.value.getFullYear(), cur.value.getMonth() + 1, 0))
const days = computed(() => {
  const out: { date: string; day: number; inMonth: boolean }[] = []
  const start = new Date(first.value); start.setDate(start.getDate() - ((start.getDay() + 1) % 7))   // week starts Saturday
  for (let i = 0; i < 42; i++) { const d = new Date(start); d.setDate(start.getDate() + i); out.push({ date: ymd(d), day: d.getDate(), inMonth: d.getMonth() === cur.value.getMonth() }) }
  return out
})
const title = computed(() => cur.value.toLocaleDateString('ar-OM', { month: 'long', year: 'numeric' }))
async function load() { events.value = await api.get('/app/bookings/calendar', { from: ymd(first.value), to: ymd(last.value), unit_id: unitId.value }) }
onMounted(async () => { units.value = await api.get('/app/bookings/units'); await load() })
function move(n: number) { cur.value = new Date(cur.value.getFullYear(), cur.value.getMonth() + n, 1); load() }
const forDay = (date: string) => events.value.filter(e => e.start_at.slice(0, 10) <= date && e.end_at.slice(0, 10) >= date)
const todayStr = ymd(new Date())
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">{{ tt('الحجوزات','Bookings') }}</NuxtLink> › <b>{{ tt('التقويم','Calendar') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:12px">
        <div class="row"><button class="btn ghost sm" :title="tt('الشهر السابق', 'Previous month')" @click="move(-1)"><i :class="`ti ${isEn ? 'ti-chevron-left' : 'ti-chevron-right'}`" /></button><b style="font-size:16px;min-width:140px;text-align:center">{{ title }}</b><button class="btn ghost sm" :title="tt('الشهر التالي', 'Next month')" @click="move(1)"><i :class="`ti ${isEn ? 'ti-chevron-right' : 'ti-chevron-left'}`" /></button><button class="btn ghost sm" @click="cur = new Date(); load()">{{ tt('اليوم','Today') }}</button></div>
        <div class="row"><UiSelect v-model="unitId" style="min-width:150px" @change="load"><option value="">{{ tt('كل الوحدات','All units') }}</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect><NuxtLink to="/bookings/new" class="btn sm"><i class="ti ti-plus" /> {{ tt('حجز جديد','New booking') }}</NuxtLink></div>
      </div>
      <div class="cal-head"><span v-for="d in (tt('ar','en')==='en' ? ['Sat','Sun','Mon','Tue','Wed','Thu','Fri'] : ['السبت','الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة'])" :key="d">{{ d }}</span></div>
      <div class="cal">
        <div v-for="d in days" :key="d.date" class="cell" :class="{ out: !d.inMonth, today: d.date === todayStr }">
          <div class="row between"><b>{{ d.day }}</b><NuxtLink :to="`/bookings/new?date=${d.date}${unitId ? `&unit_id=${unitId}` : ''}`" class="add" :title="tt('حجز جديد في هذا اليوم','New booking on this day')">+</NuxtLink></div>
          <NuxtLink v-for="e in forDay(d.date)" :key="e.id" :to="`/bookings/${e.id}`" class="ev" :style="{ background: e.color, opacity: e.status === 'pending' ? .55 : 1 }" :title="`${e.number} · ${e.unit} · ${e.customer || ''} · ${stLabel[e.status]}`">{{ e.unit }} — {{ e.customer || e.number }}</NuxtLink>
        </div>
      </div>
      <div class="chips mt"><span v-for="u in units" :key="u.id" class="chip"><span :style="`display:inline-block;width:10px;height:10px;border-radius:50%;background:${u.color};margin-left:6px`" />{{ u.name }}</span><span class="chip muted">{{ tt('باهت = معلّق','Faded = pending') }}</span></div>
    </div>
  </div>
</template>

<style scoped>
.cal-head { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 12px; color: var(--muted); font-weight: 700; margin-bottom: 4px; }
.cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.cell { border: 1px solid var(--line); border-radius: 8px; min-height: 92px; padding: 6px; font-size: 12px; background: #fff; }
.cell.out { background: #f8fafc; color: var(--muted); }
.cell.today { border-color: var(--primary); }
.cell .add { color: var(--muted); font-weight: 700; padding: 0 4px; }
.cell .add:hover { color: var(--primary); }
.ev { display: block; color: #fff; border-radius: 5px; padding: 2px 6px; margin-top: 3px; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
