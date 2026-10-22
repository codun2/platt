<script setup lang="ts">
definePageMeta({ title: 'لوحة التحكم', titleEn: 'Dashboard' })
const api = useApi()
const auth = useAuth()
const f = useFmt()
const { t, tt, isEn, locale } = useI18n()
const data = ref<any>(null)
onMounted(async () => { data.value = await api.get('/app/dashboard') })

const tiles = computed(() => auth.state.value.sidebar.filter(i => i.key !== 'dashboard'))
const tileLabel = (i: any) => (isEn.value && i.name_en) ? i.name_en : i.name_ar
const today = computed(() => new Date().toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'ar', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))
</script>

<template>
  <div>
    <div class="row between" style="margin-bottom:16px">
      <h2 style="font-size:17px">{{ t('auth.welcome', { name: auth.state.value.user?.name }) }}</h2>
      <span class="muted">{{ today }}</span>
    </div>

    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ data?.counts?.users ?? '…' }}</b><small>{{ t('dashboard.users') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-apps" /></div><div><b>{{ data?.counts?.modules ?? '…' }}</b><small>{{ t('dashboard.activeModules') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-shield-lock" /></div><div><b>{{ data?.counts?.roles ?? '…' }}</b><small>{{ t('dashboard.roles') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b>{{ data?.tenant?.renews_at ?? '…' }}</b><small>{{ t('dashboard.renewDate') }} ({{ data?.tenant?.plan || t('dashboard.noPlan') }})</small></div></div>
    </div>

    <div v-if="data?.sales" class="grid grid-4 mt">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(data.sales.today_total) }}</b><small>{{ t('dashboard.todaySales') }} ({{ data.sales.today_invoices }} {{ t('dashboard.invoices') }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-chart-line" /></div><div><b>{{ f.money(data.sales.period_total) }}</b><small>{{ t('dashboard.monthSales') }} ({{ data.sales.period_invoices }} {{ t('dashboard.invoices') }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-clock-dollar" /></div><div><b>{{ f.money(data.sales.unpaid) }}</b><small>{{ t('dashboard.unpaid') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ data.low_stock }}</b><small>{{ t('dashboard.lowStock') }}</small></div></div>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-bolt" /> {{ tt('الوصول السريع', 'Quick access') }}</div>
      <div class="quick">
        <NuxtLink v-for="tile in tiles" :key="tile.key" :to="tile.route || '/'"><i :class="`ti ${tile.icon || 'ti-apps'}`" />{{ tileLabel(tile) }}</NuxtLink>
      </div>
      <p v-if="!tiles.length" class="empty">{{ tt('لا توجد وحدات مفعّلة لحسابك بعد.', 'No modules enabled for your account yet.') }}</p>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-building" /> {{ tt('الشركة', 'Company') }}</div>
      <div class="row" style="gap:28px">
        <div><small class="muted">{{ tt('القطاع', 'Sector') }}</small><br /><b>{{ data?.tenant?.sector || '—' }}</b></div>
        <div><small class="muted">{{ tt('الخطة', 'Plan') }}</small><br /><b>{{ data?.tenant?.plan || '—' }}</b></div>
        <div><small class="muted">{{ tt('حالة الاشتراك', 'Subscription status') }}</small><br /><span class="badge" :class="data?.tenant?.subscription_status === 'trial' ? 'warn' : 'ok'">{{ data?.tenant?.subscription_status }}</span></div>
      </div>
    </div>
  </div>
</template>
