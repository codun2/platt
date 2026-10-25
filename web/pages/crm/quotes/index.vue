<script setup lang="ts">
definePageMeta({ title: 'عروض الأسعار', titleEn: 'Quotes' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const customers = ref<any[]>([]); const pg = ref(1)
const filt = reactive({ q: '', status: '', customer_id: '', from: '', to: '' })
const canManage = computed(() => auth.can('crm.manage'))

async function load(p = 1) { pg.value = p; page.value = await api.get('/app/quotes', { ...filt, page: p }) }
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }) })
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('العملاء والفواتير','Customers & Invoices') }} › <b>{{ tt('عروض الأسعار','Quotes') }}</b></div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-dollar" /></div><div><b>{{ page?.stats?.count ?? 0 }}</b><small>{{ tt('إجمالي العروض','Total quotes') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ page?.stats?.open ?? 0 }}</b><small>{{ tt('مفتوحة','Open') }} · {{ f.money(page?.stats?.open_total) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b style="font-size:17px">{{ f.money(page?.stats?.accepted_total) }}</b><small>{{ tt('قيمة المقبول','Accepted value') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-arrows-exchange" /></div><div><b>{{ page?.stats?.converted ?? 0 }}</b><small>{{ tt('محوّلة إلى فواتير','Converted') }}</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('رقم / عميل / موضوع','No. / customer / subject')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.status" @change="load()"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option v-for="(s, k) in quoteStatus" :key="k" :value="k">{{ s.label }}</option><option value="expired_open">{{ tt('تجاوزت الصلاحية','Past validity') }}</option></UiSelect>
          <UiSelect v-model="filt.customer_id" style="min-width:160px" @change="load()"><option value="">{{ tt('كل العملاء','All customers') }}</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/crm/quotes/new"><i class="ti ti-plus" /> {{ tt('عرض سعر جديد','New quote') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('صالح حتى','Valid until') }}</th><th>{{ tt('العميل','Customer') }}</th><th>{{ tt('الموضوع','Subject') }}</th><th>{{ tt('البنود','Lines') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('الفاتورة','Invoice') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="q in page?.data" :key="q.id">
            <td><NuxtLink :to="`/crm/quotes/${q.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ q.number }}</NuxtLink></td>
            <td>{{ f.d(q.date) }}</td>
            <td :style="q.is_expired ? 'color:var(--primary);font-weight:700' : ''">{{ f.d(q.valid_until) }}</td>
            <td>{{ q.customer?.name || '—' }}</td>
            <td class="muted">{{ q.subject || '—' }}</td>
            <td>{{ q.items_count }}</td>
            <td class="num"><b>{{ f.money(q.total) }}</b></td>
            <td><span class="badge" :class="quoteStatus[q.status]?.cls">{{ quoteStatus[q.status]?.label }}</span> <span v-if="q.is_expired" class="badge warn">{{ tt('تجاوز الصلاحية','Expired') }}</span></td>
            <td><NuxtLink v-if="q.invoice" :to="`/crm/invoices/${q.invoice.id}`" style="color:var(--info)" dir="ltr">{{ q.invoice.number }}</NuxtLink><span v-else class="muted">—</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/crm/quotes/${q.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد عروض أسعار.','No quotes.') }}</p>
      <UiPager :p="page" @go="load" />
    </div>
  </div>
</template>
