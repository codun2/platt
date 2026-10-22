<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'نظرة عامة على المنصّة', titleEn: 'Platform Overview' })
const api = useApi()
const { tt, L } = useI18n()
const s = ref<any>(null)
onMounted(async () => { s.value = await api.get('/super/stats') })
const statusLabel: any = { trial: L('تجريبي','Trial'), active: L('فعّال','Active'), expired: L('منتهٍ','Expired'), suspended: L('موقوف','Suspended') }
const statusClass: any = { trial: 'warn', active: 'ok', expired: 'bad', suspended: 'muted' }
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-building-community" /></div><div><b>{{ s?.tenants ?? '…' }}</b><small>{{ tt('المشتركون','Tenants') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b>{{ s?.by_status?.active ?? '…' }}</b><small>{{ tt('اشتراكات فعّالة','Active subscriptions') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ s?.by_status?.trial ?? '…' }}</b><small>{{ tt('فترة تجريبية','Trial period') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-cash" /></div><div><b>{{ s?.revenue_this_month?.toFixed(3) ?? '…' }}</b><small>{{ tt('إيراد هذا الشهر (ريال)','Revenue this month (SAR)') }}</small></div></div>
    </div>
    <div class="grid grid-2 mt">
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-circle" /> {{ tt('تنبيهات','Alerts') }}</div>
        <p>{{ tt('اشتراكات تنتهي خلال 7 أيام:','Subscriptions expiring within 7 days:') }} <b>{{ s?.due_this_week ?? '…' }}</b> · {{ tt('منتهية:','Expired:') }} <b>{{ s?.by_status?.expired ?? '…' }}</b> · {{ tt('موقوفة:','Suspended:') }} <b>{{ s?.by_status?.suspended ?? '…' }}</b></p>
        <NuxtLink to="/super/subscriptions" class="btn ghost sm">{{ tt('عرض الاشتراكات','View subscriptions') }}</NuxtLink>
        <div class="card-title mt"><i class="ti ti-category" /> {{ tt('حسب القطاع','By sector') }}</div>
        <div class="chips"><span v-for="p in s?.by_sector" :key="p.key" class="chip"><i :class="`ti ${p.icon}`" /> {{ tt(p.name_ar, p.name_en || p.name_ar) }} <b>{{ p.count }}</b></span></div>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-clock" /> {{ tt('أحدث المشتركين','Recent tenants') }}</div>
        <table class="table">
          <thead><tr><th>{{ tt('الشركة','Company') }}</th><th>{{ tt('القطاع','Sector') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in s?.recent" :key="t.id">
              <td><NuxtLink :to="`/super/tenants/${t.id}`" style="color:var(--info);font-weight:600">{{ t.name }}</NuxtLink></td>
              <td>{{ t.sector_preset ? tt(t.sector_preset.name_ar, t.sector_preset.name_en || t.sector_preset.name_ar) : '—' }}</td>
              <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
