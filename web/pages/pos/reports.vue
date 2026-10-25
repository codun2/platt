<script setup lang="ts">
definePageMeta({ title: 'تقارير المبيعات', titleEn: 'Sales Reports' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const from = ref(ymd(new Date(new Date().getFullYear(), new Date().getMonth(), 1))); const to = ref(f.today())
const r = ref<any>(null)
async function load() { r.value = await api.get('/app/pos/reports/summary', { from: from.value, to: to.value }) }
onMounted(load)
// Every day of the range (no sales = 0), or every month when the range is long.
const series = computed(() => {
  if (!r.value) return []
  const byDay: Record<string, number> = {}
  for (const d of r.value.by_day || []) byDay[d.date] = (byDay[d.date] || 0) + Number(d.total)
  const start = new Date(from.value + 'T00:00:00'); const end = new Date(to.value + 'T00:00:00')
  const days = Math.round((end.getTime() - start.getTime()) / 86400000) + 1
  const out: { key: string; label: string; total: number }[] = []
  if (days <= 62) {
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) { const k = ymd(d); out.push({ key: k, label: k.slice(8), total: byDay[k] || 0 }) }
  } else {
    for (let d = new Date(start.getFullYear(), start.getMonth(), 1); d <= end; d.setMonth(d.getMonth() + 1)) {
      const m = ymd(d).slice(0, 7)
      out.push({ key: m, label: m.slice(5) + '/' + m.slice(2, 4), total: Object.entries(byDay).filter(([k]) => k.startsWith(m)).reduce((n, [, v]) => n + v, 0) })
    }
  }
  return out
})
const byMonth = computed(() => series.value[0]?.key.length === 7)
const maxDay = computed(() => Math.max(1, ...series.value.map(d => d.total)))
// Thin out the labels when there are many bars.
const labelEvery = computed(() => series.value.length > 31 ? 7 : series.value.length > 16 ? 3 : 1)
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
        <div class="card-title"><i class="ti ti-chart-bar" /> {{ byMonth ? tt('المبيعات الشهرية','Monthly sales') : tt('المبيعات اليومية','Daily sales') }}</div>
        <div v-if="r.by_day.length" class="bars" dir="ltr">
          <div v-for="(d, i) in series" :key="d.key" class="bar" :title="`${d.key}: ${f.money(d.total)}`">
            <div class="col">
              <span v-if="d.total && series.length <= 16" class="val">{{ f.qty(d.total) }}</span>
              <div class="fill" :class="{ zero: !d.total }" :style="{ height: (d.total / maxDay * 100) + '%' }" />
            </div>
            <small :style="{ visibility: i % labelEvery === 0 || i === series.length - 1 ? 'visible' : 'hidden' }">{{ d.label }}</small>
          </div>
        </div>
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
.bars { display: flex; align-items: stretch; justify-content: center; gap: 3px; height: 200px; }
.bar { flex: 1 1 0; max-width: 34px; min-width: 5px; display: flex; flex-direction: column; }
.bar .col { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; border-bottom: 1px solid var(--line); padding-top: 16px; }
.bar .fill { width: 100%; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 2px; transition: opacity .15s; }
.bar .fill.zero { background: var(--line); }
.bar:hover .fill { opacity: .8; }
.bar .val { font-size: 9px; color: var(--muted); margin-bottom: 2px; white-space: nowrap; }
.bar small { font-size: 9px; color: var(--muted); text-align: center; height: 16px; line-height: 16px; white-space: nowrap; }
</style>
