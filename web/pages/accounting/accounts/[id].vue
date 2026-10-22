<script setup lang="ts">
definePageMeta({ title: 'كشف حساب', titleEn: 'Account Statement' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const d = ref<any>(null); const from = ref(monthStart()); const to = ref(todayStr()); const notFound = ref(false)
async function load() { try { d.value = await api.get(`/app/accounting/accounts/${id}`, { from: from.value, to: to.value }) } catch (e: any) { if (e.status === 404) notFound.value = true; else throw e } }
onMounted(load)
const print = () => window.print()
</script>

<template>
  <div v-if="d">
    <div class="breadcrumb no-print"><NuxtLink to="/accounting">{{ tt('المحاسبة','Accounting') }}</NuxtLink> › <NuxtLink to="/accounting/accounts">{{ tt('شجرة الحسابات','Chart of Accounts') }}</NuxtLink> › <b>{{ d.account.name }}</b></div>
    <div class="grid grid-4 no-print" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-hash" /></div><div><b dir="ltr">{{ d.account.code }}</b><small>{{ accountTypes[d.account.type]?.label }}<span v-if="d.account.parent"> · {{ d.account.parent.name }}</span></small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--muted)"><i class="ti ti-arrow-bar-to-right" /></div><div><b>{{ f.money(d.opening) }}</b><small>رصيد أول الفترة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-arrows-left-right" /></div><div><b>{{ f.money(d.totals.debit) }} / {{ f.money(d.totals.credit) }}</b><small>مدين / دائن الفترة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(d.closing) }}</b><small>رصيد آخر الفترة</small></div></div>
    </div>
    <div class="card">
      <div class="row between no-print" style="margin-bottom:14px">
        <div class="row"><input v-model="from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">إلى</span><input v-model="to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /></div>
        <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button>
      </div>
      <h3 style="margin:0 0 10px">{{ tt('كشف حساب','Statement') }}: {{ d.account.code }} — {{ d.account.name }} <small class="muted">({{ from }} → {{ to }})</small></h3>
      <table class="table">
        <thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('القيد','Entry') }}</th><th>{{ tt('البيان','Description') }}</th><th>{{ tt('المرجع','Ref.') }}</th><th>{{ tt('مدين','Debit') }}</th><th>{{ tt('دائن','Credit') }}</th><th>{{ tt('الرصيد','Balance') }}</th></tr></thead>
        <tbody>
          <tr style="background:#f8fafc"><td colspan="6"><b>{{ tt('رصيد أول الفترة','Opening balance') }}</b></td><td class="num"><b>{{ f.money(d.opening) }}</b></td></tr>
          <tr v-for="l in d.lines" :key="l.id"><td>{{ l.date }}</td><td><NuxtLink :to="`/accounting/journal/${l.entry_id}`" style="color:var(--info)" dir="ltr">{{ l.number }}</NuxtLink></td><td>{{ l.description || '—' }}<small class="muted"> · {{ journalSources[l.source_type || 'manual'] }}</small></td><td dir="ltr" style="text-align:right">{{ l.reference || '' }}</td><td class="num">{{ +l.debit ? f.money(l.debit) : '' }}</td><td class="num">{{ +l.credit ? f.money(l.credit) : '' }}</td><td class="num"><b>{{ f.money(l.balance) }}</b></td></tr>
          <tr style="background:#f8fafc"><td colspan="4"><b>{{ tt('الإجمالي','Total') }}</b></td><td class="num"><b>{{ f.money(d.totals.debit) }}</b></td><td class="num"><b>{{ f.money(d.totals.credit) }}</b></td><td class="num"><b>{{ f.money(d.closing) }}</b></td></tr>
        </tbody>
      </table>
      <p v-if="!d.lines.length" class="empty">{{ tt('لا توجد حركات في الفترة.','No movements in period.') }}</p>
    </div>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>{{ tt('الحساب غير موجود','Account not found') }}</h2><NuxtLink to="/accounting/accounts" class="btn ghost mt">{{ tt('العودة','Back') }}</NuxtLink></div>
</template>
