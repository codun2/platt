<script setup lang="ts">
definePageMeta({ title: 'الطلبات المسبقة والتوصيل', titleEn: 'Pre-orders & Delivery' })
const api = useApi(); const f = useFmt(); const route = useRoute()
const view = ref<'board' | 'list'>((route.query.view as any) || 'board')
const scope = ref(String(route.query.scope || 'open'))
const q = ref(''); const sum = ref<any>(null)
const board = ref<any[]>([]); const list = ref<any>(null); const busy = ref<number | null>(null)
const scopes = computed(() => view.value === 'board'
  ? [['open', tt('كل المفتوحة', 'All open')], ['today', tt('اليوم', 'Today')], ['tomorrow', tt('غداً', 'Tomorrow')], ['week', tt('خلال أسبوع', 'This week')], ['late', tt('متأخرة', 'Late')]]
  : [['open', tt('المفتوحة', 'Open')], ['delivered', tt('المسلّمة', 'Delivered')], ['cancelled', tt('الملغاة', 'Cancelled')], ['all', tt('الكل', 'All')]])
const columns = ['new', 'confirmed', 'preparing', 'ready', 'out']

async function load(p: any = 1) {
  sum.value = await api.get('/app/orders/summary')
  if (view.value === 'board') board.value = await api.get('/app/orders', { board: 1, scope: scope.value, q: q.value })
  else list.value = await api.get('/app/orders', { scope: scope.value, q: q.value, page: typeof p === 'number' ? p : 1 })
}
onMounted(load)
watch(view, v => { scope.value = 'open'; load() })
const col = (s: string) => board.value.filter((o: any) => o.status === s)
const remaining = (o: any) => Math.max(0, +o.total - +o.paid)
async function advance(o: any) {
  const next = preOrderNext(o); if (!next) return
  if (next === 'delivered') { navigateTo(`/orders/${o.id}?deliver=1`); return }
  busy.value = o.id
  try { await api.post(`/app/orders/${o.id}/status`, { status: next }); await load() } catch (e: any) { uiAlert(e.message) } finally { busy.value = null }
}
let timer: any = null
onMounted(() => { timer = setInterval(() => { if (view.value === 'board' && document.visibilityState === 'visible') load() }, 60000) })
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div>
    <div v-if="sum" class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar-event" /></div><div><b>{{ sum.today }}</b><small>{{ tt('للتسليم اليوم', 'Due today') }} · {{ tt('غداً', 'tomorrow') }} {{ sum.tomorrow }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alarm" /></div><div><b>{{ sum.late }}</b><small>{{ tt('متأخرة عن موعدها', 'Late') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(sum.deposits_held) }}</b><small>{{ tt('عرابين مستلمة لطلبات مفتوحة', 'Deposits held') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-checks" /></div><div><b>{{ sum.delivered_today }}</b><small>{{ tt('سُلّمت اليوم', 'Delivered today') }} · {{ tt('مفتوح بقيمة', 'open worth') }} {{ f.money(sum.open_value) }}</small></div></div>
    </div>

    <div class="card mt">
      <div class="row between" style="flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <div class="row" style="gap:10px;flex-wrap:wrap">
          <div class="tabs" style="margin:0;border:none"><button :class="{ on: view === 'board' }" @click="view = 'board'"><i class="ti ti-layout-kanban" /> {{ tt('لوحة التحضير', 'Board') }}</button><button :class="{ on: view === 'list' }" @click="view = 'list'"><i class="ti ti-list" /> {{ tt('السجل', 'History') }}</button></div>
          <div class="chips"><span v-for="[k, l] in scopes" :key="k" class="chip" :class="{ on: scope === k }" @click="scope = k; load()">{{ l }}</span></div>
        </div>
        <div class="row">
          <input v-model="q" :placeholder="tt('رقم / اسم / جوال', 'No. / name / phone')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <NuxtLink to="/orders/new" class="btn"><i class="ti ti-plus" /> {{ tt('طلب جديد', 'New order') }}</NuxtLink>
        </div>
      </div>

      <div v-if="view === 'board'" class="board">
        <div v-for="s in columns" :key="s" class="board-col">
          <div class="board-head" :style="{ borderColor: preOrderStatus[s].color }"><i :class="`ti ${preOrderStatus[s].icon}`" :style="{ color: preOrderStatus[s].color }" /> {{ preOrderStatus[s].label }} <span class="muted">({{ col(s).length }})</span></div>
          <div v-for="o in col(s)" :key="o.id" class="board-card" :class="{ late: isLate(o) }">
            <div class="row between"><NuxtLink :to="`/orders/${o.id}`"><b dir="ltr">{{ o.number }}</b></NuxtLink><span class="due"><i class="ti ti-clock" /> {{ dueLabel(o.due_at) }}</span></div>
            <div style="margin:4px 0"><b>{{ o.customer?.name || o.customer_name }}</b> <i :class="`ti ${o.fulfilment === 'delivery' ? 'ti-truck-delivery' : 'ti-building-store'}`" :title="o.fulfilment === 'delivery' ? tt('توصيل', 'Delivery') : tt('استلام', 'Pickup')" /></div>
            <ul class="lines"><li v-for="l in o.lines.slice(0, 4)" :key="l.id"><b>{{ f.qty(l.qty) }}×</b> {{ l.description }}</li><li v-if="o.lines.length > 4" class="muted">+{{ o.lines.length - 4 }}</li></ul>
            <div class="row between" style="margin-top:6px">
              <small :style="{ color: remaining(o) > 0 ? 'var(--primary)' : 'var(--ok)' }">{{ remaining(o) > 0 ? tt('متبقٍ', 'Due') + ' ' + f.money(remaining(o)) : tt('مدفوع', 'Paid') }}</small>
              <button v-if="preOrderNext(o)" class="btn sm" :class="preOrderNext(o) === 'delivered' ? 'ok' : 'ghost'" :disabled="busy === o.id" @click="advance(o)"><i :class="`ti ${preOrderStatus[preOrderNext(o)!].icon}`" /> {{ preOrderStatus[preOrderNext(o)!].label }}</button>
            </div>
          </div>
          <p v-if="!col(s).length" class="muted" style="text-align:center;font-size:12px;margin:14px 0">—</p>
        </div>
      </div>

      <template v-else>
        <table class="table">
          <thead><tr><th>{{ tt('الطلب', 'Order') }}</th><th>{{ tt('العميل', 'Customer') }}</th><th>{{ tt('الموعد', 'Due') }}</th><th>{{ tt('النوع', 'Type') }}</th><th>{{ tt('الإجمالي', 'Total') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="o in list?.data" :key="o.id">
              <td><NuxtLink :to="`/orders/${o.id}`"><b dir="ltr">{{ o.number }}</b></NuxtLink><br /><small class="muted">{{ preOrderChannel[o.channel]?.label }}</small></td>
              <td>{{ o.customer?.name || o.customer_name }}<br /><small class="muted" dir="ltr">{{ o.customer?.phone || o.customer_phone }}</small></td>
              <td :style="{ color: isLate(o) ? 'var(--primary)' : '' }">{{ dueLabel(o.due_at) }}</td>
              <td>{{ o.fulfilment === 'delivery' ? tt('توصيل', 'Delivery') : tt('استلام', 'Pickup') }}</td>
              <td class="num">{{ f.money(o.total) }}</td>
              <td><span class="badge" :class="preOrderStatus[o.status]?.cls">{{ preOrderStatus[o.status]?.label }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="list && !list.data.length" class="empty">{{ tt('لا توجد طلبات.', 'No orders.') }}</p>
        <div v-if="list?.data.length" class="mt"><UiPager :p="list" @go="load" /></div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.board { display: grid; grid-template-columns: repeat(5, minmax(200px, 1fr)); gap: 10px; overflow-x: auto; }
.board-col { background: var(--bg); border-radius: 12px; padding: 8px; min-height: 200px; }
.board-head { font-weight: 700; padding: 6px 4px 8px; border-bottom: 3px solid; margin-bottom: 8px; font-size: 13px; }
.board-card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 10px; margin-bottom: 8px; font-size: 13px; }
.board-card.late { border-color: var(--primary); box-shadow: inset 3px 0 0 var(--primary); }
.board-card .due { font-size: 12px; color: var(--muted); }
.board-card.late .due { color: var(--primary); font-weight: 700; }
.lines { margin: 4px 0 0; padding-inline-start: 16px; color: var(--ink); font-size: 12px; line-height: 1.6; }
@media (max-width: 900px) { .board { grid-template-columns: repeat(5, 240px); } }
</style>
