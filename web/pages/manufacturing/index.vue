<script setup lang="ts">
definePageMeta({ title: 'نظرة عامة', titleEn: 'Overview' })
const api = useApi(); const f = useFmt()
const d = ref<any>(null)
onMounted(async () => { d.value = await api.get('/app/manufacturing/summary') })
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar" /></div><div><b>{{ d?.stats.planned ?? '…' }}</b><small>{{ tt('أوامر مخططة', 'Planned orders') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-settings-automation" /></div><div><b>{{ d?.stats.in_progress ?? '…' }}</b><small>{{ tt('قيد الإنتاج الآن', 'In production') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b>{{ d?.stats.completed_month ?? 0 }}</b><small>{{ tt('مكتملة هذا الشهر', 'Completed this month') }} · {{ f.money(d?.stats.produced_value) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ d?.stats.shortages ?? 0 }}</b><small>{{ tt('مواد ناقصة للأوامر المفتوحة', 'Material shortages') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="row between">
        <div class="card-title" style="margin:0"><i class="ti ti-bolt" /> {{ tt('إجراءات سريعة', 'Quick actions') }}</div>
        <div class="row"><NuxtLink class="btn" to="/manufacturing/orders/new"><i class="ti ti-plus" /> {{ tt('أمر إنتاج', 'Production order') }}</NuxtLink><NuxtLink class="btn ghost" to="/manufacturing/bom/new"><i class="ti ti-list-details" /> {{ tt('وصفة جديدة', 'New recipe') }}</NuxtLink></div>
      </div>
    </div>
    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-settings-automation" /> {{ tt('الأوامر المفتوحة', 'Open orders') }}</div>
        <div v-for="o in d?.open" :key="o.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div><NuxtLink :to="`/manufacturing/orders/${o.id}`" style="color:var(--info);font-weight:700">{{ o.item }}</NuxtLink><br /><small class="muted" dir="ltr">{{ o.number }}</small></div>
          <div style="text-align:left"><b>{{ f.qty(o.planned_qty) }}</b> <span class="badge" :class="prodStatus[o.status]?.cls">{{ prodStatus[o.status]?.label }}</span><br /><small class="muted" dir="ltr">{{ o.planned_date || '' }}</small></div>
        </div>
        <p v-if="d && !d.open.length" class="empty">{{ tt('لا توجد أوامر مفتوحة.', 'No open orders.') }}</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-triangle" style="color:var(--primary)" /> {{ tt('مواد يجب شراؤها', 'Materials to buy') }}</div>
        <table v-if="d?.shortages.length" class="table">
          <thead><tr><th>{{ tt('المادة', 'Material') }}</th><th>{{ tt('المطلوب', 'Needed') }}</th><th>{{ tt('المتوفر', 'On hand') }}</th><th>{{ tt('الناقص', 'Missing') }}</th></tr></thead>
          <tbody><tr v-for="s in d.shortages" :key="s.item_id"><td><NuxtLink :to="`/inventory/items/${s.item_id}`" style="color:var(--info)">{{ s.name }}</NuxtLink></td><td class="num">{{ f.qty(s.need) }}</td><td class="num">{{ f.qty(s.have) }}</td><td class="num"><b style="color:var(--primary)">{{ f.qty(s.missing) }}</b></td></tr></tbody>
        </table>
        <p v-else class="empty">{{ d ? tt('المواد كافية لكل الأوامر المفتوحة ✔', 'Enough materials for all open orders ✔') : '…' }}</p>
        <NuxtLink v-if="d?.shortages.length" to="/inventory/purchases/new" class="btn ghost sm mt"><i class="ti ti-truck-delivery" /> {{ tt('فاتورة شراء', 'Purchase') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
