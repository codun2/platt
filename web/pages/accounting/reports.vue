<script setup lang="ts">
definePageMeta({ title: 'التقارير المالية', titleEn: 'Financial Reports' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const tab = ref<'tb' | 'is' | 'bs'>('is')
const from = ref(monthStart()); const to = ref(todayStr())
const tb = ref<any>(null); const is = ref<any>(null); const bs = ref<any>(null); const loading = ref(false)
const company = computed(() => auth.state.value.tenant?.name)
async function load() {
  loading.value = true
  try {
    if (tab.value === 'tb') tb.value = await api.get('/app/accounting/reports/trial-balance', { from: from.value, to: to.value })
    if (tab.value === 'is') is.value = await api.get('/app/accounting/reports/income-statement', { from: from.value, to: to.value })
    if (tab.value === 'bs') bs.value = await api.get('/app/accounting/reports/balance-sheet', { to: to.value })
  } finally { loading.value = false }
}
onMounted(load); watch(tab, load)
function preset(k: 'month' | 'quarter' | 'year') {
  const d = new Date(); const y = d.getFullYear(); const m = d.getMonth()
  from.value = k === 'month' ? `${y}-${String(m + 1).padStart(2, '0')}-01` : k === 'quarter' ? `${y}-${String(Math.floor(m / 3) * 3 + 1).padStart(2, '0')}-01` : `${y}-01-01`
  to.value = todayStr(); load()
}
const print = () => window.print()
</script>

<template>
  <div>
    <div class="breadcrumb no-print"><NuxtLink to="/accounting">المحاسبة</NuxtLink> › <b>التقارير المالية</b></div>
    <div class="card">
      <div class="row between no-print" style="margin-bottom:4px">
        <div class="tabs" style="margin-bottom:0;border:none">
          <button :class="{ on: tab === 'is' }" @click="tab = 'is'">قائمة الدخل</button>
          <button :class="{ on: tab === 'bs' }" @click="tab = 'bs'">المركز المالي</button>
          <button :class="{ on: tab === 'tb' }" @click="tab = 'tb'">ميزان المراجعة</button>
        </div>
        <div class="row">
          <button class="btn ghost sm" @click="preset('month')">هذا الشهر</button><button class="btn ghost sm" @click="preset('quarter')">الربع</button><button class="btn ghost sm" @click="preset('year')">السنة</button>
          <input v-if="tab !== 'bs'" v-model="from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span v-if="tab !== 'bs'" class="muted">إلى</span>
          <input v-model="to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
          <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> طباعة</button>
        </div>
      </div>
      <hr class="no-print" style="border:none;border-top:1px solid var(--line);margin:0 0 16px">
      <p v-if="loading" class="muted">جاري التحميل…</p>

      <!-- Income statement -->
      <div v-if="tab === 'is' && is" class="report">
        <h2 style="text-align:center;margin:0">{{ company }}</h2><h3 style="text-align:center;margin:4px 0 16px">قائمة الدخل <small class="muted">({{ is.period.from }} → {{ is.period.to }})</small></h3>
        <table class="table">
          <tbody>
            <tr class="rep-head"><td colspan="2">الإيرادات</td></tr>
            <tr v-for="r in is.revenue" :key="r.id"><td style="padding-right:28px"><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:inherit">{{ r.code }} — {{ r.name }}</NuxtLink></td><td class="num">{{ f.money(r.amount) }}</td></tr>
            <tr class="rep-total"><td>إجمالي الإيرادات</td><td class="num">{{ f.money(is.total_revenue) }}</td></tr>
            <template v-if="is.cogs.length">
              <tr class="rep-head"><td colspan="2">تكلفة المبيعات</td></tr>
              <tr v-for="r in is.cogs" :key="r.id"><td style="padding-right:28px">{{ r.code }} — {{ r.name }}</td><td class="num">({{ f.money(r.amount) }})</td></tr>
              <tr class="rep-total"><td>مجمل الربح</td><td class="num">{{ f.money(is.gross_profit) }}</td></tr>
            </template>
            <tr class="rep-head"><td colspan="2">المصروفات التشغيلية</td></tr>
            <tr v-for="r in is.expenses" :key="r.id"><td style="padding-right:28px"><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:inherit">{{ r.code }} — {{ r.name }}</NuxtLink></td><td class="num">({{ f.money(r.amount) }})</td></tr>
            <tr class="rep-total"><td>إجمالي المصروفات</td><td class="num">({{ f.money(is.total_expenses) }})</td></tr>
            <tr class="rep-grand" :style="is.net_profit < 0 ? 'color:var(--primary)' : 'color:var(--ok)'"><td>صافي {{ is.net_profit < 0 ? 'الخسارة' : 'الربح' }}</td><td class="num">{{ f.money(is.net_profit, true) }}</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Balance sheet -->
      <div v-if="tab === 'bs' && bs" class="report">
        <h2 style="text-align:center;margin:0">{{ company }}</h2><h3 style="text-align:center;margin:4px 0 16px">قائمة المركز المالي <small class="muted">(كما في {{ bs.as_of }})</small></h3>
        <div class="grid grid-2" style="align-items:start">
          <table class="table"><tbody>
            <tr class="rep-head"><td colspan="2">الأصول</td></tr>
            <tr v-for="r in bs.assets" :key="r.id"><td style="padding-right:28px"><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:inherit">{{ r.code }} — {{ r.name }}</NuxtLink></td><td class="num">{{ f.money(r.amount) }}</td></tr>
            <tr class="rep-grand"><td>إجمالي الأصول</td><td class="num">{{ f.money(bs.total_assets) }}</td></tr>
          </tbody></table>
          <table class="table"><tbody>
            <tr class="rep-head"><td colspan="2">الالتزامات</td></tr>
            <tr v-for="r in bs.liabilities" :key="r.id"><td style="padding-right:28px">{{ r.code }} — {{ r.name }}</td><td class="num">{{ f.money(r.amount) }}</td></tr>
            <tr class="rep-total"><td>إجمالي الالتزامات</td><td class="num">{{ f.money(bs.total_liabilities) }}</td></tr>
            <tr class="rep-head"><td colspan="2">حقوق الملكية</td></tr>
            <tr v-for="r in bs.equity" :key="r.id"><td style="padding-right:28px">{{ r.code }} — {{ r.name }}</td><td class="num">{{ f.money(r.amount) }}</td></tr>
            <tr><td style="padding-right:28px">أرباح (خسائر) الفترة الحالية</td><td class="num">{{ f.money(bs.current_profit) }}</td></tr>
            <tr class="rep-total"><td>إجمالي حقوق الملكية</td><td class="num">{{ f.money(bs.total_equity) }}</td></tr>
            <tr class="rep-grand"><td>إجمالي الالتزامات وحقوق الملكية</td><td class="num">{{ f.money(bs.total_liabilities + bs.total_equity) }}</td></tr>
          </tbody></table>
        </div>
        <p class="mt" style="text-align:center"><span class="badge" :class="bs.balanced ? 'ok' : 'bad'">{{ bs.balanced ? 'الميزانية متوازنة ✔' : 'الميزانية غير متوازنة — راجع القيود' }}</span></p>
      </div>

      <!-- Trial balance -->
      <div v-if="tab === 'tb' && tb" class="report">
        <h2 style="text-align:center;margin:0">{{ company }}</h2><h3 style="text-align:center;margin:4px 0 16px">ميزان المراجعة <small class="muted">({{ tb.period.from }} → {{ tb.period.to }})</small></h3>
        <table class="table">
          <thead><tr><th>الرمز</th><th>الحساب</th><th>النوع</th><th>حركة مدين</th><th>حركة دائن</th><th>رصيد مدين</th><th>رصيد دائن</th></tr></thead>
          <tbody>
            <tr v-for="r in tb.rows" :key="r.id"><td dir="ltr" style="text-align:right">{{ r.code }}</td><td><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:inherit">{{ r.name }}</NuxtLink></td><td><span class="badge" :class="accountTypes[r.type]?.cls">{{ accountTypes[r.type]?.label }}</span></td><td class="num">{{ f.money(r.debit_moves) }}</td><td class="num">{{ f.money(r.credit_moves) }}</td><td class="num">{{ r.debit ? f.money(r.debit) : '' }}</td><td class="num">{{ r.credit ? f.money(r.credit) : '' }}</td></tr>
            <tr class="rep-grand"><td colspan="5">الإجمالي</td><td class="num">{{ f.money(tb.total_debit) }}</td><td class="num">{{ f.money(tb.total_credit) }}</td></tr>
          </tbody>
        </table>
        <p class="mt" style="text-align:center"><span class="badge" :class="tb.balanced ? 'ok' : 'bad'">{{ tb.balanced ? 'الميزان متوازن ✔' : 'الميزان غير متوازن' }}</span></p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rep-head td { background: #f8fafc; font-weight: 700; }
.rep-total td { font-weight: 700; border-top: 1px dashed var(--line); }
.rep-grand td { font-weight: 800; font-size: 15px; border-top: 2px solid var(--ink); }
@media print { .report { font-size: 13px; } }
</style>
