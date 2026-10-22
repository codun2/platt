<script setup lang="ts">
definePageMeta({ title: 'المحاسبة', titleEn: 'Accounting' })
const api = useApi(); const f = useFmt()
const d = ref<any>(null); const from = ref(monthStart()); const to = ref(todayStr())
async function load() { d.value = await api.get('/app/accounting/summary', { from: from.value, to: to.value }) }
onMounted(load)
</script>

<template>
  <div>
    <div class="row between" style="margin-bottom:14px">
      <div class="breadcrumb" style="margin:0"><b>المحاسبة</b></div>
      <div class="row"><input v-model="from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">إلى</span><input v-model="to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /></div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(d?.revenue) }}</b><small>إيرادات الفترة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-trending-down" /></div><div><b>{{ f.money(d?.expense) }}</b><small>مصروفات الفترة</small></div></div>
      <div class="card stat"><div class="ic" :style="`background:${(d?.profit ?? 0) >= 0 ? 'var(--info)' : 'var(--primary)'}`"><i class="ti ti-chart-line" /></div><div><b>{{ f.money(d?.profit) }}</b><small>صافي الربح</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-book" /></div><div><b>{{ d?.entries_count ?? 0 }}</b><small>قيد في الفترة</small></div></div>
    </div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.cash) }}</b><small>رصيد الصندوق</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-building-bank" /></div><div><b>{{ f.money(d?.bank) }}</b><small>رصيد البنك</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-users" /></div><div><b>{{ f.money(d?.receivable) }}</b><small>مستحق من العملاء</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt-tax" /></div><div><b>{{ f.money(d?.vat_due) }}</b><small>ضريبة مستحقة للسداد</small></div></div>
    </div>

    <div class="card">
      <div class="quick">
        <NuxtLink to="/accounting/accounts"><i class="ti ti-sitemap" />شجرة الحسابات</NuxtLink>
        <NuxtLink to="/accounting/journal"><i class="ti ti-book" />القيود اليومية</NuxtLink>
        <NuxtLink to="/accounting/expenses"><i class="ti ti-receipt" />المصروفات</NuxtLink>
        <NuxtLink to="/accounting/reports"><i class="ti ti-report-analytics" />التقارير المالية</NuxtLink>
        <NuxtLink to="/accounting/expenses/new"><i class="ti ti-plus" />مصروف جديد</NuxtLink>
        <NuxtLink to="/accounting/journal/new"><i class="ti ti-plus" />قيد يدوي</NuxtLink>
      </div>
    </div>

    <div class="grid grid-2" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-history" /> آخر القيود</div>
        <table class="table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>البيان</th><th>المصدر</th><th>المبلغ</th></tr></thead>
          <tbody><tr v-for="e in d?.recent" :key="e.id"><td><NuxtLink :to="`/accounting/journal/${e.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ e.number }}</NuxtLink></td><td>{{ f.d(e.date) }}</td><td class="muted">{{ e.description || '—' }}</td><td><span class="badge muted">{{ journalSources[e.source_type || 'manual'] }}</span></td><td class="num"><b>{{ f.money(e.total) }}</b></td></tr></tbody></table>
        <p v-if="d && !d.recent.length" class="empty">لا توجد قيود بعد. ستظهر تلقائياً من الفواتير والمصروفات.</p>
      </div>
      <div>
        <div class="card">
          <div class="card-title"><i class="ti ti-chart-pie" /> المصروفات حسب النوع (الفترة)</div>
          <table class="table"><tbody><tr v-for="c in d?.expenses_by_category" :key="c.id"><td><NuxtLink :to="`/accounting/accounts/${c.id}`" style="color:var(--info)">{{ c.name }}</NuxtLink></td><td class="num"><b>{{ f.money(c.total) }}</b></td></tr></tbody></table>
          <p v-if="d && !d.expenses_by_category.length" class="empty" style="padding:16px 0">لا مصروفات في الفترة.</p>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-coins" /> الإيرادات حسب الحساب (الفترة)</div>
          <table class="table"><tbody><tr v-for="r in d?.revenue_accounts" :key="r.id"><td><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:var(--info)">{{ r.name }}</NuxtLink></td><td class="num"><b>{{ f.money(r.balance) }}</b></td></tr></tbody></table>
          <p v-if="d && !d.revenue_accounts.length" class="empty" style="padding:16px 0">لا إيرادات في الفترة.</p>
        </div>
      </div>
    </div>
  </div>
</template>
