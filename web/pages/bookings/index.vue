<script setup lang="ts">
definePageMeta({ title: 'الحجوزات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const units = ref<any[]>([]); const pg = ref(1)
const filt = reactive({ q: '', status: '', unit_id: '', from: '', to: '', upcoming: false })
const canManage = computed(() => auth.can('bookings.manage'))
async function load(p = 1) { pg.value = p; page.value = await api.get('/app/bookings/list', { ...filt, upcoming: filt.upcoming ? 1 : '', page: p }) }
onMounted(async () => { await load(); units.value = await api.get('/app/bookings/units') })
const sum = (k: string) => (page.value?.data || []).filter((b: any) => b.status !== 'cancelled').reduce((s: number, b: any) => s + (+b[k] || 0), 0)
</script>

<template>
  <div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar-event" /></div><div><b>{{ page?.total ?? 0 }}</b><small>حجز</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ (page?.data || []).filter((b: any) => b.status === 'pending').length }}</b><small>معلّق (في الصفحة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(sum('paid')) }}</b><small>المحصّل (الصفحة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(sum('total') - sum('paid')) }}</b><small>المتبقي (الصفحة)</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="رقم / عميل / هاتف" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <UiSelect v-model="filt.status" @change="load()"><option value="">كل الحالات</option><option v-for="(s, k) in bookingStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <UiSelect v-model="filt.unit_id" style="min-width:150px" @change="load()"><option value="">كل الوحدات</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <label class="switch"><input v-model="filt.upcoming" type="checkbox" @change="load()" /><span /> القادمة فقط</label>
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <div class="row"><NuxtLink to="/bookings/calendar" class="btn ghost"><i class="ti ti-calendar" /> التقويم</NuxtLink><NuxtLink v-if="canManage" to="/bookings/new" class="btn"><i class="ti ti-plus" /> حجز جديد</NuxtLink></div>
      </div>
      <table class="table">
        <thead><tr><th>رقم الحجز</th><th>الوحدة</th><th>العميل</th><th>النوع</th><th>من</th><th>إلى</th><th>الإجمالي</th><th>مدفوع</th><th>الباقي</th><th>التأمين</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="b in page?.data" :key="b.id">
            <td><NuxtLink :to="`/bookings/${b.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ b.number }}</NuxtLink></td>
            <td><span :style="`display:inline-block;width:10px;height:10px;border-radius:50%;background:${b.unit?.color};margin-left:6px`" />{{ b.unit?.name }}</td>
            <td>{{ b.customer?.name || b.customer_name || '—' }}<br /><small class="muted" dir="ltr">{{ b.customer?.phone || b.customer_phone }}</small></td>
            <td>{{ periodLabel[b.period] }} × {{ f.qty(b.qty) }}</td><td>{{ f.dt(b.start_at) }}</td><td>{{ f.dt(b.end_at) }}</td>
            <td class="num"><b>{{ f.money(b.total) }}</b></td><td class="num" style="color:var(--ok)">{{ f.money(b.paid) }}</td><td class="num" :style="+b.total - +b.paid > 0 && b.status !== 'cancelled' ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(+b.total - +b.paid) }}</td>
            <td><span class="badge" :class="b.deposit_status === 'held' ? 'info' : 'muted'">{{ depositStatus[b.deposit_status] }}</span></td>
            <td><span class="badge" :class="bookingStatus[b.status]?.cls">{{ bookingStatus[b.status]?.label }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/bookings/${b.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد حجوزات.</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">صفحة {{ page.current_page }} من {{ page.last_page }} · {{ page.total }} حجز</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">السابق</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">التالي</button></div>
      </div>
    </div>
  </div>
</template>
