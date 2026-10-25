<script setup lang="ts">
definePageMeta({ title: 'أوامر العمل', titleEn: 'Work Orders' })
const api = useApi(); const f = useFmt(); const route = useRoute(); const S = useServiceSector()
const d = ref<any>(null); const techs = ref<any[]>([])
const view = ref<'list' | 'board'>(route.query.view === 'board' ? 'board' : 'list')
const filt = reactive({ q: '', status: String(route.query.status || 'open'), technician_id: '', late: false, page: 1 })
async function load() { d.value = await api.get('/app/service/orders', { ...filt, late: filt.late ? 1 : '', board: view.value === 'board' ? 1 : '', status: view.value === 'board' ? 'open' : filt.status }) }
onMounted(async () => { await load(); techs.value = await api.get('/app/service/technicians', { active_only: 1 }) })
watch(view, load)
const who = (o: any) => o.customer?.name || o.customer_name
const late = (o: any) => OPEN_ORDER.includes(o.status) && o.promised_at && new Date(o.promised_at.replace(' ', 'T')) < new Date()
async function move(o: any, status: string) { try { await api.post(`/app/service/orders/${o.id}/status`, { status }); await load() } catch (e: any) { uiAlert(e.message) } }
const columns = computed(() => OPEN_ORDER.map(k => ({ key: k, ...orderStatus[k], items: (d.value?.page.data || []).filter((o: any) => o.status === k) })))
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/service">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ S.orders }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <div class="chips"><span class="chip" :class="{ on: view === 'list' }" @click="view = 'list'"><i class="ti ti-list" /> {{ tt('قائمة', 'List') }}</span><span class="chip" :class="{ on: view === 'board' }" @click="view = 'board'"><i class="ti ti-layout-kanban" /> {{ tt('لوحة', 'Board') }}</span></div>
          <input v-model="filt.q" :placeholder="tt('رقم / اسم / جوال / لوحة…', 'No. / name / phone / plate…')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="filt.page = 1; load()" />
          <UiSelect v-if="view === 'list'" v-model="filt.status" style="min-width:130px" @change="filt.page = 1; load()"><option value="open">{{ tt('المفتوحة', 'Open') }}</option><option value="all">{{ tt('الكل', 'All') }}</option><option v-for="(s, k) in orderStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <UiSelect v-if="techs.length" v-model="filt.technician_id" style="min-width:120px" @change="load"><option value="">{{ tt('الكل', 'Everyone') }}</option><option v-for="t in techs" :key="t.id" :value="t.id">{{ t.name }}</option></UiSelect>
          <label class="switch"><input v-model="filt.late" type="checkbox" @change="load" /><span /> {{ tt('المتأخرة', 'Late') }}</label>
        </div>
        <NuxtLink class="btn" to="/service/tickets/new"><i class="ti ti-plus" /> {{ S.order }} {{ tt('جديد', 'new') }}</NuxtLink>
      </div>

      <div v-if="view === 'board'" class="board">
        <div v-for="c in columns" :key="c.key" class="board-col">
          <div class="board-head"><i :class="`ti ${c.icon}`" /> {{ c.label }} <span class="badge muted">{{ c.items.length }}</span></div>
          <div v-for="o in c.items" :key="o.id" class="board-card" :class="{ late: late(o) }">
            <NuxtLink :to="`/service/tickets/${o.id}`" style="font-weight:700;color:var(--ink)">{{ who(o) }}</NuxtLink>
            <small class="muted" style="display:block">{{ o.title }}</small>
            <small v-if="detailsSummary(S.key, o.details)" style="display:block">{{ detailsSummary(S.key, o.details) }}</small>
            <div class="row between" style="margin-top:6px"><small :style="late(o) ? 'color:var(--primary)' : 'color:var(--muted)'"><i class="ti ti-clock" /> {{ o.promised_at ? o.promised_at.slice(5, 16) : '—' }}</small><small class="muted">{{ o.technician?.name }}</small></div>
            <div class="row" style="gap:4px;margin-top:6px;flex-wrap:wrap">
              <button v-for="n in OPEN_ORDER.filter(k => k !== o.status)" :key="n" class="btn ghost sm" style="font-size:11px;padding:3px 7px" @click="move(o, n)">→ {{ orderStatus[n].label }}</button>
            </div>
          </div>
          <p v-if="!c.items.length" class="muted" style="text-align:center;font-size:12px">—</p>
        </div>
      </div>

      <template v-else>
        <table class="table">
          <thead><tr><th>{{ tt('الرقم', 'No.') }}</th><th>{{ tt('العميل', 'Customer') }}</th><th>{{ S.titleLabel }}</th><th>{{ tt(S.spec.title[0], S.spec.title[1]) }}</th><th>{{ S.tech }}</th><th>{{ tt('موعد التسليم', 'Promised') }}</th><th>{{ tt('القيمة', 'Value') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="o in d?.page.data" :key="o.id">
              <td><NuxtLink :to="`/service/tickets/${o.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ o.number }}</NuxtLink></td>
              <td>{{ who(o) }}<br /><small class="muted" dir="ltr">{{ o.customer?.phone || o.customer_phone }}</small></td>
              <td style="max-width:220px">{{ o.title }}</td><td>{{ detailsSummary(S.key, o.details) || '—' }}</td><td>{{ o.technician?.name || '—' }}</td>
              <td dir="ltr" style="text-align:right" :style="late(o) ? 'color:var(--primary);font-weight:700' : ''">{{ o.promised_at || '—' }}</td>
              <td class="num">{{ f.money(+o.subtotal - +o.discount) }}</td>
              <td><span class="badge" :class="orderStatus[o.status]?.cls">{{ orderStatus[o.status]?.label }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="d && !d.page.data.length" class="empty">{{ tt('لا توجد طلبات مطابقة.', 'No matching orders.') }}</p>
        <UiPager :p="d?.page" @go="(n: number) => { filt.page = n; load() }" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.board { display: grid; grid-template-columns: repeat(4, minmax(200px, 1fr)); gap: 12px; overflow-x: auto; }
.board-col { background: var(--bg); border-radius: 12px; padding: 10px; min-height: 200px; }
.board-head { font-weight: 700; font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.board-card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 10px; margin-bottom: 8px; }
.board-card.late { border-color: var(--primary); }
</style>
