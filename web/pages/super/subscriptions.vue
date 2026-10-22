<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'الاشتراكات الشهرية', titleEn: 'Monthly Subscriptions' })
const api = useApi()
const { tt, L } = useI18n()
const rows = ref<any[]>([]); const history = ref<any>(null)
const status = ref(''); const dueSoon = ref(false); const tab = ref<'current' | 'history'>('current')
const msg = ref('')
const statusLabel: any = { trial: L('تجريبي','Trial'), active: L('فعّال','Active'), expired: L('منتهٍ','Expired'), suspended: L('موقوف','Suspended') }
const statusClass: any = { trial: 'warn', active: 'ok', expired: 'bad', suspended: 'muted' }

async function load() {
  rows.value = await api.get('/super/subscriptions', { status: status.value, due_soon: dueSoon.value ? 1 : '' })
  history.value = await api.get('/super/subscriptions/history')
}
onMounted(load)
async function expire() { const r = await api.post('/super/subscriptions/expire-overdue'); msg.value = tt(`تم تعليم ${r.expired} اشتراك كمنتهٍ`,`Marked ${r.expired} subscription(s) as expired`); await load() }
const daysLeft = (d: string | null) => d ? Math.ceil((new Date(d).getTime() - Date.now()) / 86400000) : null
</script>

<template>
  <div class="card">
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div class="tabs"><button :class="{ on: tab === 'current' }" @click="tab = 'current'">{{ tt('الاشتراكات الحالية','Current subscriptions') }}</button><button :class="{ on: tab === 'history' }" @click="tab = 'history'">{{ tt('سجل المدفوعات','Payment history') }}</button></div>

    <div v-if="tab === 'current'">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <select v-model="status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('كل الحالات','All statuses') }}</option><option value="trial">{{ tt('تجريبي','Trial') }}</option><option value="active">{{ tt('فعّال','Active') }}</option><option value="expired">{{ tt('منتهٍ','Expired') }}</option><option value="suspended">{{ tt('موقوف','Suspended') }}</option></select>
          <label class="switch"><input v-model="dueSoon" type="checkbox" @change="load" /><span /> {{ tt('تنتهي خلال 7 أيام','Expiring within 7 days') }}</label>
        </div>
        <button class="btn ghost" @click="expire"><i class="ti ti-clock-x" /> {{ tt('تعليم المتأخرة كمنتهية','Mark overdue as expired') }}</button>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الشركة','Company') }}</th><th>{{ tt('القطاع','Sector') }}</th><th>{{ tt('الخطة','Plan') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('التجديد','Renewal') }}</th><th>{{ tt('المتبقي','Remaining') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in rows" :key="t.id">
            <td><b>{{ t.name }}</b></td><td>{{ t.sector_preset ? tt(t.sector_preset.name_ar, t.sector_preset.name_en || t.sector_preset.name_ar) : '—' }}</td>
            <td>{{ t.plan ? tt(t.plan.name_ar, t.plan.name_en || t.plan.name_ar) : '—' }} <small class="muted">{{ t.plan ? t.plan.price_monthly + ' ' + tt('ريال','SAR') : '' }}</small></td>
            <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            <td class="num">{{ t.subscription_renews_at || '—' }}</td>
            <td><span v-if="daysLeft(t.subscription_renews_at) !== null" class="badge" :class="daysLeft(t.subscription_renews_at)! < 0 ? 'bad' : daysLeft(t.subscription_renews_at)! <= 7 ? 'warn' : 'ok'">{{ daysLeft(t.subscription_renews_at) }} {{ tt('يوم','days') }}</span></td>
            <td><NuxtLink :to="`/super/tenants/${t.id}`" class="btn ghost sm">{{ tt('تجديد / إدارة','Renew / manage') }}</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">{{ tt('لا توجد نتائج.','No results.') }}</p>
    </div>

    <div v-else>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الشركة','Company') }}</th><th>{{ tt('الخطة','Plan') }}</th><th>{{ tt('من','From') }}</th><th>{{ tt('إلى','To') }}</th><th>{{ tt('المبلغ','Amount') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('ملاحظة','Note') }}</th></tr></thead>
        <tbody>
          <tr v-for="s in history?.data" :key="s.id">
            <td>{{ s.id }}</td><td>{{ s.tenant?.name }}</td><td>{{ s.plan ? tt(s.plan.name_ar, s.plan.name_en || s.plan.name_ar) : '—' }}</td>
            <td class="num">{{ s.period_start }}</td><td class="num">{{ s.period_end }}</td><td class="num">{{ s.amount }}</td>
            <td><span class="badge" :class="s.status === 'paid' ? 'ok' : 'warn'">{{ s.status === 'paid' ? tt('مدفوع','Paid') : s.status }}</span></td><td class="muted">{{ s.note }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="history && !history.data.length" class="empty">{{ tt('لا يوجد سجل بعد.','No history yet.') }}</p>
    </div>
  </div>
</template>
