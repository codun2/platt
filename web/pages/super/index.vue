<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'نظرة عامة على المنصّة', titleEn: 'Platform Overview' })
const api = useApi()
const s = ref<any>(null)
onMounted(async () => { s.value = await api.get('/super/stats') })
const statusLabel: any = { trial: 'تجريبي', active: 'فعّال', expired: 'منتهٍ', suspended: 'موقوف' }
const statusClass: any = { trial: 'warn', active: 'ok', expired: 'bad', suspended: 'muted' }
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-building-community" /></div><div><b>{{ s?.tenants ?? '…' }}</b><small>المشتركون</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b>{{ s?.by_status?.active ?? '…' }}</b><small>اشتراكات فعّالة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-hourglass" /></div><div><b>{{ s?.by_status?.trial ?? '…' }}</b><small>فترة تجريبية</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-cash" /></div><div><b>{{ s?.revenue_this_month?.toFixed(3) ?? '…' }}</b><small>إيراد هذا الشهر (ريال)</small></div></div>
    </div>
    <div class="grid grid-2 mt">
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-circle" /> تنبيهات</div>
        <p>اشتراكات تنتهي خلال 7 أيام: <b>{{ s?.due_this_week ?? '…' }}</b> · منتهية: <b>{{ s?.by_status?.expired ?? '…' }}</b> · موقوفة: <b>{{ s?.by_status?.suspended ?? '…' }}</b></p>
        <NuxtLink to="/super/subscriptions" class="btn ghost sm">عرض الاشتراكات</NuxtLink>
        <div class="card-title mt"><i class="ti ti-category" /> حسب القطاع</div>
        <div class="chips"><span v-for="p in s?.by_sector" :key="p.key" class="chip"><i :class="`ti ${p.icon}`" /> {{ p.name_ar }} <b>{{ p.count }}</b></span></div>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-clock" /> أحدث المشتركين</div>
        <table class="table">
          <thead><tr><th>الشركة</th><th>القطاع</th><th>الحالة</th></tr></thead>
          <tbody>
            <tr v-for="t in s?.recent" :key="t.id">
              <td><NuxtLink :to="`/super/tenants/${t.id}`" style="color:var(--info);font-weight:600">{{ t.name }}</NuxtLink></td>
              <td>{{ t.sector_preset?.name_ar || '—' }}</td>
              <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
