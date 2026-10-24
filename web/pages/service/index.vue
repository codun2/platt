<script setup lang="ts">
definePageMeta({ title: 'نظرة عامة', titleEn: 'Overview' })
const api = useApi(); const f = useFmt(); const S = useServiceSector()
const d = ref<any>(null)
onMounted(async () => { d.value = await api.get('/app/service/summary') })
const who = (o: any) => o.customer?.name || o.customer_name
const phone = (o: any) => o.customer?.phone || o.customer_phone
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-clipboard-list" /></div><div><b>{{ d?.stats.open ?? '…' }}</b><small>{{ tt('قيد العمل الآن', 'Open now') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b>{{ d?.stats.by_status?.ready ?? 0 }}</b><small>{{ tt('جاهزة بانتظار العميل', 'Ready for pickup') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alarm" /></div><div><b>{{ d?.stats.late ?? 0 }}</b><small>{{ tt('تجاوزت موعد التسليم', 'Past promised time') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats.revenue_month) }}</b><small>{{ tt('إيراد هذا الشهر', 'Revenue this month') }} ({{ d?.stats.delivered_month ?? 0 }})</small></div></div>
    </div>
    <div class="card mt">
      <div class="row between">
        <div class="chips"><NuxtLink v-for="k in OPEN_ORDER" :key="k" :to="`/service/tickets?status=${k}`" class="chip"><i :class="`ti ${orderStatus[k].icon}`" /> {{ orderStatus[k].label }} <b>{{ d?.stats.by_status?.[k] || 0 }}</b></NuxtLink></div>
        <div class="row"><NuxtLink class="btn" to="/service/tickets/new"><i class="ti ti-plus" /> {{ S.order }} {{ tt('جديد', 'new') }}</NuxtLink><NuxtLink class="btn ghost" to="/service/tickets?view=board"><i class="ti ti-layout-kanban" /> {{ tt('لوحة العمل', 'Board') }}</NuxtLink></div>
      </div>
    </div>
    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-circle-check" style="color:var(--ok)" /> {{ tt('جاهزة للتسليم — تواصل مع العميل', 'Ready — contact the customer') }}</div>
        <div v-for="o in d?.ready" :key="o.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div><NuxtLink :to="`/service/tickets/${o.id}`" style="color:var(--info);font-weight:700">{{ who(o) }}</NuxtLink><br /><small class="muted">{{ o.title }} · {{ detailsSummary(S.key, o.details) }}</small></div>
          <a v-if="phone(o)" :href="`https://wa.me/${String(phone(o)).replace(/\D/g, '')}`" target="_blank" class="btn ghost sm" dir="ltr"><i class="ti ti-brand-whatsapp" /> {{ phone(o) }}</a>
        </div>
        <p v-if="d && !d.ready.length" class="empty">{{ tt('لا شيء جاهز الآن.', 'Nothing ready.') }}</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-alarm" style="color:var(--primary)" /> {{ tt('متأخرة عن الموعد', 'Running late') }}</div>
        <div v-for="o in d?.late" :key="o.id" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
          <div><NuxtLink :to="`/service/tickets/${o.id}`" style="color:var(--info);font-weight:700">{{ who(o) }}</NuxtLink><br /><small class="muted">{{ o.title }}<template v-if="o.technician"> · {{ o.technician.name }}</template></small></div>
          <div style="text-align:left"><span class="badge" :class="orderStatus[o.status]?.cls">{{ orderStatus[o.status]?.label }}</span><br /><small style="color:var(--primary)">{{ fmtWhen(o.promised_at) }}</small></div>
        </div>
        <p v-if="d && !d.late.length" class="empty">{{ tt('كل الأعمال في موعدها 🎉', 'Everything on time 🎉') }}</p>
      </div>
    </div>
  </div>
</template>
