<script setup lang="ts">
definePageMeta({ title: 'المحاسبة', titleEn: 'Accounting' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const d = ref<any>(null); const from = ref(monthStart()); const to = ref(todayStr())
async function load() { d.value = await api.get('/app/accounting/summary', { from: from.value, to: to.value }) }
onMounted(load)
</script>

<template>
  <div>
    <div class="row between" style="margin-bottom:14px">
      <div class="breadcrumb" style="margin:0"><b>{{ tt('المحاسبة','Accounting') }}</b></div>
      <div class="row"><input v-model="from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">إلى</span><input v-model="to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /></div>
    </div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(d?.revenue) }}</b><small>{{ tt('إيرادات الفترة','Period revenue') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-trending-down" /></div><div><b>{{ f.money(d?.expense) }}</b><small>{{ tt('مصروفات الفترة','Period expenses') }}</small></div></div>
      <div class="card stat"><div class="ic" :style="`background:${(d?.profit ?? 0) >= 0 ? 'var(--info)' : 'var(--primary)'}`"><i class="ti ti-chart-line" /></div><div><b>{{ f.money(d?.profit) }}</b><small>{{ tt('صافي الربح','Net profit') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-book" /></div><div><b>{{ d?.entries_count ?? 0 }}</b><small>{{ tt('قيد في الفترة','entries in period') }}</small></div></div>
    </div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.cash) }}</b><small>{{ tt('رصيد الصندوق','Cash balance') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-building-bank" /></div><div><b>{{ f.money(d?.bank) }}</b><small>{{ tt('رصيد البنك','Bank balance') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-users" /></div><div><b>{{ f.money(d?.receivable) }}</b><small>{{ tt('مستحق من العملاء','Receivable') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt-tax" /></div><div><b>{{ f.money(d?.vat_due) }}</b><small>{{ tt('ضريبة مستحقة للسداد','VAT due') }}</small></div></div>
    </div>

    <div class="card">
      <div class="quick">
        <NuxtLink to="/accounting/accounts"><i class="ti ti-sitemap" />{{ tt('شجرة الحسابات','Chart of accounts') }}</NuxtLink>
        <NuxtLink to="/accounting/journal"><i class="ti ti-book" />{{ tt('القيود اليومية','Journal') }}</NuxtLink>
        <NuxtLink to="/accounting/expenses"><i class="ti ti-receipt" />{{ tt('المصروفات','Expenses') }}</NuxtLink>
        <NuxtLink to="/accounting/reports"><i class="ti ti-report-analytics" />{{ tt('التقارير المالية','Reports') }}</NuxtLink>
        <NuxtLink to="/accounting/expenses/new"><i class="ti ti-plus" />{{ tt('مصروف جديد','New expense') }}</NuxtLink>
        <NuxtLink to="/accounting/journal/new"><i class="ti ti-plus" />{{ tt('قيد يدوي','Manual entry') }}</NuxtLink>
      </div>
    </div>

    <div class="grid grid-2" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-history" /> {{ tt('آخر القيود','Recent entries') }}</div>
        <table class="table"><thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('البيان','Description') }}</th><th>{{ tt('المصدر','Source') }}</th><th>{{ tt('المبلغ','Amount') }}</th></tr></thead>
          <tbody><tr v-for="e in d?.recent" :key="e.id"><td><NuxtLink :to="`/accounting/journal/${e.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ e.number }}</NuxtLink></td><td>{{ f.d(e.date) }}</td><td class="muted">{{ e.description || '—' }}</td><td><span class="badge muted">{{ journalSources[e.source_type || 'manual'] }}</span></td><td class="num"><b>{{ f.money(e.total) }}</b></td></tr></tbody></table>
        <p v-if="d && !d.recent.length" class="empty">{{ tt('لا توجد قيود بعد. ستظهر تلقائياً من الفواتير والمصروفات.','No entries yet. They appear automatically from invoices and expenses.') }}</p>
      </div>
      <div>
        <div class="card">
          <div class="card-title"><i class="ti ti-chart-pie" /> {{ tt('المصروفات حسب النوع (الفترة)','Expenses by type (period)') }}</div>
          <table class="table"><tbody><tr v-for="c in d?.expenses_by_category" :key="c.id"><td><NuxtLink :to="`/accounting/accounts/${c.id}`" style="color:var(--info)">{{ c.name }}</NuxtLink></td><td class="num"><b>{{ f.money(c.total) }}</b></td></tr></tbody></table>
          <p v-if="d && !d.expenses_by_category.length" class="empty" style="padding:16px 0">{{ tt('لا مصروفات في الفترة.','No expenses in period.') }}</p>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-coins" /> {{ tt('الإيرادات حسب الحساب (الفترة)','Revenue by account (period)') }}</div>
          <table class="table"><tbody><tr v-for="r in d?.revenue_accounts" :key="r.id"><td><NuxtLink :to="`/accounting/accounts/${r.id}`" style="color:var(--info)">{{ r.name }}</NuxtLink></td><td class="num"><b>{{ f.money(r.balance) }}</b></td></tr></tbody></table>
          <p v-if="d && !d.revenue_accounts.length" class="empty" style="padding:16px 0">{{ tt('لا إيرادات في الفترة.','No revenue in period.') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
