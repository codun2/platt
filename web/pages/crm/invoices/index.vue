<script setup lang="ts">
definePageMeta({ title: 'الفواتير', titleEn: 'Invoices' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const customers = ref<any[]>([]); const pg = ref(1)
const filt = reactive({ q: '', status: '', customer_id: '', from: '', to: '' })
const canManage = computed(() => auth.can('crm.manage'))

async function load(p = 1) { pg.value = p; page.value = await api.get('/app/invoices', { ...filt, page: p }) }
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }) })
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('العملاء والفواتير','Customers & Invoices') }} › <b>{{ tt('الفواتير','Invoices') }}</b></div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b>{{ page?.stats?.count ?? 0 }}</b><small>{{ tt('فاتورة','invoices') }} · {{ f.money(page?.stats?.total) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b style="font-size:17px">{{ f.money(page?.stats?.collected) }}</b><small>{{ tt('المحصّل','Collected') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b style="font-size:17px">{{ f.money(page?.stats?.outstanding) }}</b><small>{{ tt('المستحق غير المسدّد','Outstanding') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b style="font-size:17px">{{ f.money(page?.stats?.overdue) }}</b><small>{{ tt('متأخرة','Overdue') }} ({{ page?.stats?.overdue_count ?? 0 }})</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('رقم / عميل / موضوع / مرجع','No. / customer / subject / ref')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.status" @change="load()"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option v-for="(s, k) in invoiceStatus" :key="k" :value="k">{{ s.label }}</option><option value="overdue">{{ tt('متأخرة','Overdue') }}</option></UiSelect>
          <UiSelect v-model="filt.customer_id" style="min-width:160px" @change="load()"><option value="">{{ tt('كل العملاء','All customers') }}</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/crm/invoices/new"><i class="ti ti-plus" /> {{ tt('فاتورة جديدة','New invoice') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الاستحقاق','Due') }}</th><th>{{ tt('العميل','Customer') }}</th><th>{{ tt('الموضوع','Subject') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('المتبقي','Balance') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="inv in page?.data" :key="inv.id">
            <td><NuxtLink :to="`/crm/invoices/${inv.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ inv.number }}</NuxtLink></td>
            <td>{{ f.d(inv.date) }}</td>
            <td :style="inv.is_overdue ? 'color:var(--primary);font-weight:700' : ''">{{ f.d(inv.due_date) }}</td>
            <td>{{ inv.customer?.name || tt('نقدي','Cash') }}</td>
            <td class="muted">{{ inv.subject || '—' }}</td>
            <td class="num"><b>{{ f.money(inv.total) }}</b></td>
            <td class="num" style="color:var(--ok)">{{ f.money(inv.paid) }}</td>
            <td class="num" :style="+inv.balance > 0 && inv.status !== 'cancelled' ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(inv.balance) }}</td>
            <td><span class="badge" :class="invoiceStatus[inv.status]?.cls">{{ invoiceStatus[inv.status]?.label }}</span> <span v-if="inv.is_overdue" class="badge bad">{{ tt('متأخرة','Overdue') }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/crm/invoices/${inv.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد فواتير.','No invoices.') }}</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">{{ tt('صفحة','Page') }} {{ page.current_page }} {{ tt('من','of') }} {{ page.last_page }} · {{ page.total }}</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">{{ tt('السابق','Prev') }}</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">{{ tt('التالي','Next') }}</button></div>
      </div>
    </div>
  </div>
</template>
