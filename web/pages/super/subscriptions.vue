<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'الاشتراكات الشهرية', titleEn: 'Monthly Subscriptions' })
const api = useApi()
const rows = ref<any[]>([]); const history = ref<any>(null)
const status = ref(''); const dueSoon = ref(false); const tab = ref<'current' | 'history'>('current')
const msg = ref('')
const statusLabel: any = { trial: 'تجريبي', active: 'فعّال', expired: 'منتهٍ', suspended: 'موقوف' }
const statusClass: any = { trial: 'warn', active: 'ok', expired: 'bad', suspended: 'muted' }

async function load() {
  rows.value = await api.get('/super/subscriptions', { status: status.value, due_soon: dueSoon.value ? 1 : '' })
  history.value = await api.get('/super/subscriptions/history')
}
onMounted(load)
async function expire() { const r = await api.post('/super/subscriptions/expire-overdue'); msg.value = `تم تعليم ${r.expired} اشتراك كمنتهٍ`; await load() }
const daysLeft = (d: string | null) => d ? Math.ceil((new Date(d).getTime() - Date.now()) / 86400000) : null
</script>

<template>
  <div class="card">
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div class="tabs"><button :class="{ on: tab === 'current' }" @click="tab = 'current'">الاشتراكات الحالية</button><button :class="{ on: tab === 'history' }" @click="tab = 'history'">سجل المدفوعات</button></div>

    <div v-if="tab === 'current'">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <select v-model="status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الحالات</option><option value="trial">تجريبي</option><option value="active">فعّال</option><option value="expired">منتهٍ</option><option value="suspended">موقوف</option></select>
          <label class="switch"><input v-model="dueSoon" type="checkbox" @change="load" /><span /> تنتهي خلال 7 أيام</label>
        </div>
        <button class="btn ghost" @click="expire"><i class="ti ti-clock-x" /> تعليم المتأخرة كمنتهية</button>
      </div>
      <table class="table">
        <thead><tr><th>الشركة</th><th>القطاع</th><th>الخطة</th><th>الحالة</th><th>التجديد</th><th>المتبقي</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in rows" :key="t.id">
            <td><b>{{ t.name }}</b></td><td>{{ t.sector_preset?.name_ar || '—' }}</td>
            <td>{{ t.plan?.name_ar || '—' }} <small class="muted">{{ t.plan ? t.plan.price_monthly + ' ريال' : '' }}</small></td>
            <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            <td class="num">{{ t.subscription_renews_at || '—' }}</td>
            <td><span v-if="daysLeft(t.subscription_renews_at) !== null" class="badge" :class="daysLeft(t.subscription_renews_at)! < 0 ? 'bad' : daysLeft(t.subscription_renews_at)! <= 7 ? 'warn' : 'ok'">{{ daysLeft(t.subscription_renews_at) }} يوم</span></td>
            <td><NuxtLink :to="`/super/tenants/${t.id}`" class="btn ghost sm">تجديد / إدارة</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">لا توجد نتائج.</p>
    </div>

    <div v-else>
      <table class="table">
        <thead><tr><th>#</th><th>الشركة</th><th>الخطة</th><th>من</th><th>إلى</th><th>المبلغ</th><th>الحالة</th><th>ملاحظة</th></tr></thead>
        <tbody>
          <tr v-for="s in history?.data" :key="s.id">
            <td>{{ s.id }}</td><td>{{ s.tenant?.name }}</td><td>{{ s.plan?.name_ar || '—' }}</td>
            <td class="num">{{ s.period_start }}</td><td class="num">{{ s.period_end }}</td><td class="num">{{ s.amount }}</td>
            <td><span class="badge" :class="s.status === 'paid' ? 'ok' : 'warn'">{{ s.status === 'paid' ? 'مدفوع' : s.status }}</span></td><td class="muted">{{ s.note }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="history && !history.data.length" class="empty">لا يوجد سجل بعد.</p>
    </div>
  </div>
</template>
