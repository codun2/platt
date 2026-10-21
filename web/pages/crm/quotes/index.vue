<script setup lang="ts">
definePageMeta({ title: 'عروض الأسعار' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const customers = ref<any[]>([]); const pg = ref(1)
const filt = reactive({ q: '', status: '', customer_id: '', from: '', to: '' })
const canManage = computed(() => auth.can('crm.manage'))

async function load(p = 1) { pg.value = p; page.value = await api.get('/app/quotes', { ...filt, page: p }) }
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }) })
</script>

<template>
  <div>
    <div class="breadcrumb">العملاء والفواتير › <b>عروض الأسعار</b></div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-dollar" /></div><div><b>{{ page?.stats?.count ?? 0 }}</b><small>إجمالي العروض</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ page?.stats?.open ?? 0 }}</b><small>مفتوحة · {{ f.money(page?.stats?.open_total) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b style="font-size:17px">{{ f.money(page?.stats?.accepted_total) }}</b><small>قيمة المقبول</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-arrows-exchange" /></div><div><b>{{ page?.stats?.converted ?? 0 }}</b><small>محوّلة إلى فواتير</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="رقم / عميل / موضوع" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.status" @change="load()"><option value="">كل الحالات</option><option v-for="(s, k) in quoteStatus" :key="k" :value="k">{{ s.label }}</option><option value="expired_open">تجاوزت الصلاحية</option></UiSelect>
          <UiSelect v-model="filt.customer_id" style="min-width:160px" @change="load()"><option value="">كل العملاء</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/crm/quotes/new"><i class="ti ti-plus" /> عرض سعر جديد</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>صالح حتى</th><th>العميل</th><th>الموضوع</th><th>البنود</th><th>الإجمالي</th><th>الحالة</th><th>الفاتورة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="q in page?.data" :key="q.id">
            <td><NuxtLink :to="`/crm/quotes/${q.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ q.number }}</NuxtLink></td>
            <td>{{ f.d(q.date) }}</td>
            <td :style="q.is_expired ? 'color:var(--primary);font-weight:700' : ''">{{ f.d(q.valid_until) }}</td>
            <td>{{ q.customer?.name || '—' }}</td>
            <td class="muted">{{ q.subject || '—' }}</td>
            <td>{{ q.items_count }}</td>
            <td class="num"><b>{{ f.money(q.total) }}</b></td>
            <td><span class="badge" :class="quoteStatus[q.status]?.cls">{{ quoteStatus[q.status]?.label }}</span> <span v-if="q.is_expired" class="badge warn">تجاوز الصلاحية</span></td>
            <td><NuxtLink v-if="q.invoice" :to="`/crm/invoices/${q.invoice.id}`" style="color:var(--info)" dir="ltr">{{ q.invoice.number }}</NuxtLink><span v-else class="muted">—</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/crm/quotes/${q.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد عروض أسعار.</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">صفحة {{ page.current_page }} من {{ page.last_page }} · {{ page.total }} عرض</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">السابق</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">التالي</button></div>
      </div>
    </div>
  </div>
</template>
