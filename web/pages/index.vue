<script setup lang="ts">
definePageMeta({ title: 'لوحة التحكم' })
const api = useApi()
const auth = useAuth()
const f = useFmt()
const data = ref<any>(null)
onMounted(async () => { data.value = await api.get('/app/dashboard') })

// Quick-access tiles = top-level modules the user can see (from the dynamic sidebar).
const tiles = computed(() => auth.state.value.sidebar.filter(i => i.key !== 'dashboard'))
const today = new Date().toLocaleDateString('ar-OM', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <div>
    <div class="row between" style="margin-bottom:16px">
      <h2 style="font-size:17px">أهلاً {{ auth.state.value.user?.name }}، مرحباً بعودتك</h2>
      <span class="muted">{{ today }}</span>
    </div>

    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ data?.counts?.users ?? '…' }}</b><small>المستخدمون</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-apps" /></div><div><b>{{ data?.counts?.modules ?? '…' }}</b><small>الوحدات المفعّلة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-shield-lock" /></div><div><b>{{ data?.counts?.roles ?? '…' }}</b><small>الأدوار</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b style="font-size:16px">{{ data?.tenant?.renews_at ?? '…' }}</b><small>تاريخ التجديد ({{ data?.tenant?.plan || 'بدون خطة' }})</small></div></div>
    </div>

    <div v-if="data?.sales" class="grid grid-4 mt">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(data.sales.today_total) }}</b><small>مبيعات اليوم ({{ data.sales.today_invoices }} فاتورة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-chart-line" /></div><div><b>{{ f.money(data.sales.period_total) }}</b><small>مبيعات الشهر ({{ data.sales.period_invoices }} فاتورة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-clock-dollar" /></div><div><b>{{ f.money(data.sales.unpaid) }}</b><small>مستحقات غير مسددة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ data.low_stock }}</b><small>أصناف تحت الحد الأدنى</small></div></div>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-bolt" /> الوصول السريع</div>
      <div class="quick">
        <NuxtLink v-for="t in tiles" :key="t.key" :to="t.route || '/'"><i :class="`ti ${t.icon || 'ti-apps'}`" />{{ t.name_ar }}</NuxtLink>
      </div>
      <p v-if="!tiles.length" class="empty">لا توجد وحدات مفعّلة لحسابك بعد.</p>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-building" /> الشركة</div>
      <div class="row" style="gap:28px">
        <div><small class="muted">القطاع</small><br /><b>{{ data?.tenant?.sector || '—' }}</b></div>
        <div><small class="muted">الخطة</small><br /><b>{{ data?.tenant?.plan || '—' }}</b></div>
        <div><small class="muted">حالة الاشتراك</small><br /><span class="badge" :class="data?.tenant?.subscription_status === 'trial' ? 'warn' : 'ok'">{{ data?.tenant?.subscription_status }}</span></div>
      </div>
    </div>
  </div>
</template>
