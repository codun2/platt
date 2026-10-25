<script setup lang="ts">
definePageMeta({ title: 'حركات المخزون', titleEn: 'Stock Movements' })
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const meta = ref<any>({ warehouses: [] })
const filt = reactive({ warehouse_id: '', type: '', from: '', to: '' })
async function load(p: any = 1) { const n = typeof p === 'number' ? p : 1; page.value = await api.get('/app/inventory/movements', { ...filt, page: n }) }
onMounted(async () => { meta.value = await api.get('/app/inventory/items/meta'); await load() })
const typeLabel = movementLabel
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">{{ tt('المخزون','Inventory') }}</NuxtLink> › <b>{{ tt('حركات المخزون','Stock movements') }}</b></div>
    <div class="card">
      <div class="row" style="margin-bottom:14px">
        <select v-model="filt.warehouse_id" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('كل المستودعات','All warehouses') }}</option><option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
        <select v-model="filt.type" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('كل الأنواع','All types') }}</option><option v-for="t in ['purchase','sale','sale_cancel','purchase_cancel','adjustment','transfer_in','transfer_out','opening','production_in','production_out','service']" :key="t" :value="t">{{ typeLabel(t) }}</option></select>
        <input v-model="filt.from" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        <input v-model="filt.to" type="date" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('التاريخ','Date') }}</th><th>{{ tt('الصنف','Item') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('المستودع','Warehouse') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('الرصيد بعد','Balance after') }}</th><th>{{ tt('المرجع','Reference') }}</th><th>{{ tt('ملاحظة','Note') }}</th><th>{{ tt('بواسطة','By') }}</th></tr></thead>
        <tbody>
          <tr v-for="m in page?.data" :key="m.id">
            <td>{{ f.dt(m.created_at) }}</td><td>{{ m.item?.name }}</td><td><span class="badge" :class="Number(m.qty) < 0 ? 'bad' : 'ok'">{{ typeLabel(m.type) }}</span></td><td>{{ m.warehouse?.name }}</td>
            <td class="num" :style="Number(m.qty) < 0 ? 'color:var(--primary)' : 'color:var(--ok)'"><b>{{ Number(m.qty) > 0 ? '+' : '' }}{{ f.qty(m.qty) }}</b></td><td class="num">{{ f.qty(m.balance_after) }}</td>
            <td class="muted">{{ m.ref_type ? `${m.ref_type} #${m.ref_id}` : '—' }}</td><td class="muted">{{ m.note || '' }}</td><td>{{ m.user?.name || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="page" @go="load" />
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد حركات.','No movements.') }}</p>
    </div>
  </div>
</template>
