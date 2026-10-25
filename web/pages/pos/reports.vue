<script setup lang="ts">
definePageMeta({ title: 'تقارير المبيعات', titleEn: 'Sales Reports' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const from = ref(ymd(new Date(new Date().getFullYear(), new Date().getMonth(), 1))); const to = ref(f.today())
const r = ref<any>(null)
async function load() { r.value = await api.get('/app/pos/reports/summary', { from: from.value, to: to.value }) }
onMounted(load)
const maxDay = computed(() => Math.max(1, ...(r.value?.by_day || []).map((d: any) => Number(d.total))))
function preset(p: 'today' | 'week' | 'month' | 'year') {
  const n = new Date(); const d = (x: Date) => ymd(x)
  if (p === 'today') { from.value = d(n); to.value = d(n) }
  if (p === 'week') { const s = new Date(n); s.setDate(n.getDate() - 6); from.value = d(s); to.value = d(n) }
  if (p === 'month') { from.value = d(new Date(n.getFullYear(), n.getMonth(), 1)); to.value = d(n) }
  if (p === 'year') { from.value = d(new Date(n.getFullYear(), 0, 1)); to.value = d(n) }
  load()
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/pos">{{ tt('نقطة البيع','Point of Sale') }}</NuxtLink> › <b>{{ tt('تقارير المبيعات','Sales Reports') }}</b></div>
    <div class="card">
      <div class="row">
        <input v-model="from" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <input v-model="to" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <div class="chips"><span class="chip" @click="preset('today')">{{ tt('اليوم','Today') }}</span><span class="chip" @click="preset('week')">{{ tt('7 أيام','7 days') }}</span><span class="chip" @click="preset('month')">{{ tt('هذا الشهر','This month') }}</span><span class="chip" @click="preset('year')">{{ tt('هذا العام','This year') }}</span></div>
      </div>
    </div>
    <div v-if="r" class="grid grid-4 mt">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-receipt" /></div><div><b>{{ r.totals.invoices }}</b><small>{{ tt('فاتورة','invoices') }} ({{ r.cancelled }} {{ tt('ملغاة','cancelled') }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(r.totals.total) }}</b><small>{{ tt('إجمالي المبيعات','Total sales') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(r.gross_profit) }}</b><small>{{ tt('الربح الإجمالي','Gross profit') }} ({{ tt('تكلفة','cost') }} {{ f.money(r.cost) }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-clock-dollar" /></div><div><b>{{ f.money(Number(r.totals.total) - Number(r.totals.paid)) }}</b><small>{{ tt('غير مسدد (آجل)','Unpaid (credit)') }}</small></div></div>
    </div>
    <div v-if="r" class="grid grid-2 mt">
      <div class="card">
        <div class="card-title"><i class="ti ti-chart-bar" /> {{ tt('المبيعات اليومية','Daily sales') }}</div>
        <div class="bars"><div v-for="d in r.by_day" :key="d.date" class="bar" :title="`${d.date}: ${f.money(d.total)}`"><div class="fill" :style="{ height: (Number(d.total) / maxDay * 100) + '%' }" /><small>{{ d.date.slice(5) }}</small></div></div>
        <p v-if="!r.by_day.length" class="empty">{{ tt('لا مبيعات في الفترة.','No sales in period.') }}</p>
        <div class="card-title mt"><i class="ti ti-credit-card" /> {{ tt('حسب طريقة الدفع','By payment method') }}</div>
        <table class="table"><tbody><tr v-for="m in r.by_method" :key="m.payment_method"><td>{{ f.methodLabel(m.payment_method) }}</td><td>{{ m.invoices }}</td><td class="num">{{ f.money(m.total) }}</td><td class="num muted">{{ tt('مدفوع','paid') }} {{ f.money(m.paid) }}</td></tr></tbody></table>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-star" /> {{ tt('الأصناف الأكثر مبيعاً','Top-selling items') }}</div>
        <table class="table"><thead><tr><th>{{ tt('الصنف','Item') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('المبيعات','Sales') }}</th><th>{{ tt('الربح','Profit') }}</th></tr></thead>
          <tbody><tr v-for="i in r.top_items" :key="i.item_id"><td>{{ i.name }}</td><td class="num">{{ f.qty(i.qty) }}</td><td class="num">{{ f.money(i.total) }}</td><td class="num" style="color:var(--ok)">{{ f.money(Number(i.total) - Number(i.cost)) }}</td></tr></tbody></table>
        <div class="card-title mt"><i class="ti ti-users" /> {{ tt('أفضل العملاء','Top customers') }}</div>
        <table class="table"><tbody><tr v-for="c in r.top_customers" :key="c.customer_id"><td>{{ c.customer?.name }}</td><td>{{ c.invoices }} {{ tt('فاتورة','invoices') }}</td><td class="num">{{ f.money(c.total) }}</td></tr></tbody></table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bars { display: flex; align-items: flex-end; gap: 4px; height: 160px; }
.bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 14px; }
.bar .fill { width: 100%; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 2px; }
.bar small { font-size: 9px; color: var(--muted); margin-top: 4px; }
</style>
