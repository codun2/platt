<script setup lang="ts">
definePageMeta({ title: 'أوامر الإنتاج', titleEn: 'Production Orders' })
const api = useApi(); const f = useFmt()
const d = ref<any>(null); const filt = reactive({ status: 'open', page: 1 })
async function load() { d.value = await api.get('/app/manufacturing/orders', filt) }
onMounted(load)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/manufacturing">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('أوامر الإنتاج', 'Production orders') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="chips"><span v-for="[k, l] in [['open', tt('المفتوحة', 'Open')], ['completed', tt('المكتملة', 'Completed')], ['cancelled', tt('الملغاة', 'Cancelled')], ['all', tt('الكل', 'All')]]" :key="k" class="chip" :class="{ on: filt.status === k }" @click="filt.status = k; filt.page = 1; load()">{{ l }}</span></div>
        <NuxtLink class="btn" to="/manufacturing/orders/new"><i class="ti ti-plus" /> {{ tt('أمر إنتاج', 'Production order') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الرقم', 'No.') }}</th><th>{{ tt('المنتج', 'Product') }}</th><th>{{ tt('المخطط', 'Planned') }}</th><th>{{ tt('المنتج فعلياً', 'Produced') }}</th><th>{{ tt('تكلفة الوحدة', 'Unit cost') }}</th><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
        <tbody>
          <tr v-for="o in d?.data" :key="o.id">
            <td><NuxtLink :to="`/manufacturing/orders/${o.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ o.number }}</NuxtLink></td>
            <td>{{ o.item?.name }}<br /><small class="muted">{{ o.bom?.name }}</small></td>
            <td>{{ f.qty(o.planned_qty) }} {{ o.item?.unit?.symbol || '' }}</td><td>{{ o.actual_qty != null ? f.qty(o.actual_qty) : '—' }}</td>
            <td class="num">{{ +o.unit_cost ? f.money(o.unit_cost) : '—' }}</td>
            <td dir="ltr" style="text-align:right">{{ o.completed_at || o.planned_date || '—' }}</td>
            <td><span class="badge" :class="prodStatus[o.status]?.cls">{{ prodStatus[o.status]?.label }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.data.length" class="empty">{{ tt('لا توجد أوامر.', 'No orders.') }}</p>
      <div v-if="d && d.last_page > 1" class="row mt" style="justify-content:center"><button class="btn ghost sm" :disabled="filt.page <= 1" @click="filt.page--; load()">‹</button><span class="muted">{{ d.current_page }} / {{ d.last_page }}</span><button class="btn ghost sm" :disabled="filt.page >= d.last_page" @click="filt.page++; load()">›</button></div>
    </div>
  </div>
</template>
