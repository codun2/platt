<script setup lang="ts">
definePageMeta({ title: 'المشتريات', titleEn: 'Purchases' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const suppliers = ref<any[]>([]); const pg = ref(1)
const filt = reactive({ q: '', status: '', from: '', to: '' })
const canManage = computed(() => auth.can('inventory.manage'))
async function load(p = 1) { pg.value = p; page.value = await api.get('/app/inventory/purchases', { ...filt, page: p }) }
onMounted(async () => { await load(); suppliers.value = await api.get('/app/inventory/suppliers') })
const sum = (k: string, only?: (p: any) => boolean) => (page.value?.data || []).filter((p: any) => p.status !== 'cancelled' && (!only || only(p))).reduce((s: number, p: any) => s + (+p[k] || 0), 0)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">{{ tt('المخزون','Inventory') }}</NuxtLink> › <b>{{ tt('المشتريات','Purchases') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-truck-delivery" /></div><div><b>{{ page?.total ?? 0 }}</b><small>{{ tt('فاتورة شراء','purchase invoices') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-shopping-cart" /></div><div><b>{{ f.money(sum('total')) }}</b><small>{{ tt('إجمالي الصفحة','Page total') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(sum('paid')) }}</b><small>{{ tt('المدفوع','Paid') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-hourglass" /></div><div><b>{{ f.money(sum('total') - sum('paid')) }}</b><small>{{ tt('مستحق للموردين (الصفحة)','Payable (page)') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('رقم / مورد / فاتورة المورد','No. / supplier / supplier inv.')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.status" @change="load()"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option v-for="(s, k) in purchaseStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <div class="row"><NuxtLink class="btn ghost" to="/inventory/suppliers"><i class="ti ti-truck" /> {{ tt('الموردون','Suppliers') }} ({{ suppliers.length }})</NuxtLink><NuxtLink v-if="canManage" class="btn" to="/inventory/purchases/new"><i class="ti ti-plus" /> {{ tt('فاتورة شراء','New purchase') }}</NuxtLink></div>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم','No.') }}</th><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الاستحقاق','Due') }}</th><th>{{ tt('المورد','Supplier') }}</th><th>{{ tt('المستودع','Warehouse') }}</th><th>{{ tt('بنود','Lines') }}</th><th>{{ tt('الإجمالي','Total') }}</th><th>{{ tt('المدفوع','Paid') }}</th><th>{{ tt('المتبقي','Balance') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in page?.data" :key="p.id">
            <td><NuxtLink :to="`/inventory/purchases/${p.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ p.number }}</NuxtLink></td>
            <td>{{ f.d(p.date) }}</td><td>{{ f.d(p.due_date) }}</td><td>{{ p.supplier?.name || '—' }}</td><td>{{ p.warehouse?.name }}</td><td>{{ p.items_count }}</td>
            <td class="num"><b>{{ f.money(p.total) }}</b></td><td class="num" style="color:var(--ok)">{{ f.money(p.paid) }}</td>
            <td class="num" :style="+p.total - +p.paid > 0 && p.status !== 'cancelled' ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(+p.total - +p.paid) }}</td>
            <td><span class="badge" :class="purchaseStatus[p.status]?.cls">{{ purchaseStatus[p.status]?.label }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/inventory/purchases/${p.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد فواتير شراء.','No purchases.') }}</p>
      <UiPager :p="page" @go="load" />
    </div>
  </div>
</template>
