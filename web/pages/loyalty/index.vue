<script setup lang="ts">
definePageMeta({ title: 'الولاء والمحفظة', titleEn: 'Loyalty & Wallet' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const canManage = computed(() => auth.can('loyalty.manage'))
const tab = ref<'members' | 'log' | 'settings'>((route.query.tab as any) || 'members')
const sum = ref<any>(null)
const members = ref<any>(null); const mf = reactive({ q: '', filter: '', sort: 'points', page: 1 })
const log = ref<any>(null); const lf = reactive({ type: '', from: '', to: '', page: 1 })
const form = reactive<any>({ ...{ earn_rate: 1, point_value: 0.01, min_redeem: 100, max_redeem_percent: 50, wallet_enabled: true } })
const msg = ref(''); const err = ref('')

async function loadSum() { sum.value = await api.get('/app/loyalty/summary'); Object.assign(form, sum.value.settings) }
async function loadMembers(p: any = 1) { mf.page = typeof p === 'number' ? p : 1; members.value = await api.get('/app/loyalty/members', mf) }
async function loadLog(p: any = 1) { lf.page = typeof p === 'number' ? p : 1; log.value = await api.get('/app/loyalty/transactions', lf) }
onMounted(() => { loadSum(); loadMembers() })
watch(tab, t => { if (t === 'log' && !log.value) loadLog() })

async function saveSettings() {
  msg.value = ''; err.value = ''
  try { Object.assign(form, await api.raw('/app/loyalty/settings', { method: 'PUT', body: form })); msg.value = tt('تم حفظ إعدادات الولاء', 'Loyalty settings saved'); loadSum() } catch (e: any) { err.value = e.message }
}
// "Spend 100 → earn X points worth Y" — the rule in words, as the cashier will explain it.
const example = computed(() => {
  const pts = Math.floor(100 * (+form.earn_rate || 0)); const val = +(pts * (+form.point_value || 0)).toFixed(3)
  return { pts, val, pct: +(val).toFixed(2) }
})
</script>

<template>
  <div>
    <div v-if="sum" class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-users" /></div><div><b>{{ sum.members }}</b><small>{{ tt('عملاء لديهم نقاط أو رصيد', 'Members with points / balance') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-award" /></div><div><b>{{ f.qty(sum.points_outstanding) }}</b><small>{{ tt('نقاط قائمة بقيمة', 'Outstanding points worth') }} {{ f.money(sum.points_value) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(sum.wallet_total) }}</b><small>{{ tt('أرصدة المحافظ (التزام على المحل)', 'Wallet balances (owed to customers)') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-calendar-stats" /></div><div><b>+{{ f.qty(sum.month_earned) }} / −{{ f.qty(sum.month_redeemed) }}</b><small>{{ tt('نقاط هذا الشهر: مكتسبة / مستبدلة', 'This month: earned / redeemed') }}</small></div></div>
    </div>

    <div class="card mt">
      <div class="tabs">
        <button :class="{ on: tab === 'members' }" @click="tab = 'members'"><i class="ti ti-users" /> {{ tt('العملاء', 'Members') }}</button>
        <button :class="{ on: tab === 'log' }" @click="tab = 'log'"><i class="ti ti-list" /> {{ tt('سجل الحركات', 'Activity') }}</button>
        <button :class="{ on: tab === 'settings' }" @click="tab = 'settings'"><i class="ti ti-settings" /> {{ tt('قواعد النقاط والمحفظة', 'Rules') }}</button>
      </div>

      <template v-if="tab === 'members'">
        <div class="row between" style="margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <div class="chips">
            <span class="chip" :class="{ on: !mf.filter }" @click="mf.filter = ''; loadMembers()">{{ tt('الكل', 'All') }}</span>
            <span class="chip" :class="{ on: mf.filter === 'points' }" @click="mf.filter = 'points'; loadMembers()">{{ tt('لديهم نقاط', 'With points') }}</span>
            <span class="chip" :class="{ on: mf.filter === 'wallet' }" @click="mf.filter = 'wallet'; loadMembers()">{{ tt('لديهم رصيد', 'With balance') }}</span>
          </div>
          <div class="row"><input v-model="mf.q" :placeholder="tt('اسم / جوال / رمز العميل', 'Name / phone / code')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="loadMembers()" /><button class="btn ghost" @click="loadMembers()"><i class="ti ti-search" /></button></div>
        </div>
        <table class="table">
          <thead><tr><th>{{ tt('العميل', 'Customer') }}</th><th style="cursor:pointer" @click="mf.sort = 'points'; loadMembers()">{{ tt('النقاط', 'Points') }} <i v-if="mf.sort === 'points'" class="ti ti-arrow-down" /></th><th>{{ tt('قيمتها', 'Worth') }}</th><th style="cursor:pointer" @click="mf.sort = 'wallet'; loadMembers()">{{ tt('رصيد المحفظة', 'Wallet') }} <i v-if="mf.sort === 'wallet'" class="ti ti-arrow-down" /></th><th>{{ tt('آخر حركة', 'Last activity') }}</th><th></th></tr></thead>
          <tbody>
            <tr v-for="c in members?.data" :key="c.id">
              <td><NuxtLink :to="`/loyalty/${c.id}`"><b>{{ c.name }}</b></NuxtLink><br /><small class="muted" dir="ltr">{{ c.phone }}</small></td>
              <td class="num"><b>{{ f.qty(c.points) }}</b></td>
              <td class="num">{{ f.money(+c.points * +(sum?.settings.point_value || 0)) }}</td>
              <td class="num"><b :style="{ color: +c.wallet_balance > 0 ? 'var(--ok)' : '' }">{{ f.money(c.wallet_balance) }}</b></td>
              <td><small class="muted">{{ c.last_activity ? f.dt(c.last_activity) : '—' }}</small></td>
              <td><NuxtLink :to="`/loyalty/${c.id}`" class="btn ghost sm"><i class="ti ti-file-description" /> {{ tt('الكشف', 'Statement') }}</NuxtLink></td>
            </tr>
          </tbody>
        </table>
        <p v-if="members && !members.data.length" class="empty">{{ tt('لا يوجد عملاء مطابقون.', 'No matching customers.') }}</p>
        <div v-if="members?.data.length" class="mt"><UiPager :p="members" @go="loadMembers" /></div>
      </template>

      <template v-if="tab === 'log'">
        <div class="row" style="margin-bottom:12px;flex-wrap:wrap;gap:8px">
          <UiSelect v-model="lf.type" style="min-width:180px" @change="loadLog()"><option value="">{{ tt('كل الحركات', 'All types') }}</option><option v-for="(t, k) in loyaltyType" :key="k" :value="k">{{ t.label }}</option></UiSelect>
          <input v-model="lf.from" type="date" dir="ltr" @change="loadLog()" /><span class="muted">{{ tt('إلى', 'to') }}</span><input v-model="lf.to" type="date" dir="ltr" @change="loadLog()" />
        </div>
        <table class="table">
          <thead><tr><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ tt('العميل', 'Customer') }}</th><th>{{ tt('الحركة', 'Type') }}</th><th>{{ tt('النقاط', 'Points') }}</th><th>{{ tt('المحفظة', 'Wallet') }}</th><th>{{ tt('المرجع / ملاحظة', 'Reference / note') }}</th><th>{{ tt('بواسطة', 'By') }}</th></tr></thead>
          <tbody>
            <tr v-for="t in log?.data" :key="t.id" :style="{ opacity: t.reversed ? .55 : 1 }">
              <td><small>{{ f.dt(t.created_at) }}</small></td>
              <td><NuxtLink :to="`/loyalty/${t.customer_id}`">{{ t.customer?.name }}</NuxtLink></td>
              <td><span class="badge" :class="loyaltyType[t.type]?.cls"><i :class="`ti ${loyaltyType[t.type]?.icon}`" /> {{ loyaltyType[t.type]?.label || t.type }}</span><small v-if="t.reversed" class="muted"> ({{ tt('أُلغيت', 'reversed') }})</small></td>
              <td class="num" dir="ltr">{{ +t.points ? (+t.points > 0 ? '+' : '') + f.qty(t.points) : '—' }}</td>
              <td class="num" dir="ltr">{{ +t.amount && t.type !== 'redeem' ? (+t.amount > 0 ? '+' : '') + f.money(t.amount) : '—' }}</td>
              <td><small>{{ t.reference }} {{ t.note }}</small></td>
              <td><small class="muted">{{ t.user?.name }}</small></td>
            </tr>
          </tbody>
        </table>
        <p v-if="log && !log.data.length" class="empty">{{ tt('لا توجد حركات بعد.', 'No activity yet.') }}</p>
        <div v-if="log?.data.length" class="mt"><UiPager :p="log" @go="loadLog" /></div>
      </template>

      <template v-if="tab === 'settings'">
        <div v-if="msg" class="alert ok">{{ msg }}</div>
        <div v-if="err" class="alert bad">{{ err }}</div>
        <div class="grid grid-2" style="align-items:start">
          <div>
            <div class="card-title"><i class="ti ti-award" /> {{ tt('النقاط', 'Points') }}</div>
            <div class="grid grid-2">
              <div class="field"><label>{{ tt('نقاط لكل 1 ريال مدفوع', 'Points per 1 currency paid') }}</label><input v-model.number="form.earn_rate" type="number" step="0.01" min="0" dir="ltr" :disabled="!canManage" /></div>
              <div class="field"><label>{{ tt('قيمة النقطة عند الاستبدال (ريال)', 'Value of 1 point (currency)') }}</label><input v-model.number="form.point_value" type="number" step="0.001" min="0" dir="ltr" :disabled="!canManage" /></div>
              <div class="field"><label>{{ tt('أقل عدد نقاط للاستبدال', 'Minimum points to redeem') }}</label><input v-model.number="form.min_redeem" type="number" min="0" dir="ltr" :disabled="!canManage" /></div>
              <div class="field"><label>{{ tt('النقاط تغطي حتى % من الفاتورة', 'Points may pay up to % of a bill') }}</label><input v-model.number="form.max_redeem_percent" type="number" min="0" max="100" dir="ltr" :disabled="!canManage" /></div>
            </div>
            <div class="card-title" style="margin-top:6px"><i class="ti ti-wallet" /> {{ tt('المحفظة', 'Wallet') }}</div>
            <label class="row" style="gap:10px"><span class="switch"><input v-model="form.wallet_enabled" type="checkbox" :disabled="!canManage" /><span /></span> {{ tt('تفعيل محفظة الرصيد المسبق (شحن والدفع من الرصيد)', 'Enable the prepaid wallet (top-up & pay from balance)') }}</label>
            <button v-if="canManage" class="btn mt" @click="saveSettings"><i class="ti ti-device-floppy" /> {{ tt('حفظ', 'Save') }}</button>
          </div>
          <div class="totals-box">
            <div class="card-title" style="margin-top:0"><i class="ti ti-bulb" /> {{ tt('كيف يراها العميل', 'How the customer sees it') }}</div>
            <p style="line-height:1.9;margin:0">{{ tt(`كل مشتريات بـ 100 ريال = ${example.pts} نقطة، قيمتها ${example.val} ريال عند الاستبدال (أي استرداد ${example.pct}% تقريباً).`, `Every 100 spent = ${example.pts} points, worth ${example.val} when redeemed (≈ ${example.pct}% back).`) }}</p>
            <p class="muted" style="line-height:1.9">{{ tt(`يستبدل العميل نقاطه عند الكاشير بدءاً من ${form.min_redeem} نقطة، وحتى ${form.max_redeem_percent}% من قيمة الفاتورة. النقاط تُحتسب على المبلغ المدفوع فعلاً، وتُعكس تلقائياً إذا أُلغيت الفاتورة.`, `Customers redeem at the till from ${form.min_redeem} points, up to ${form.max_redeem_percent}% of a bill. Points are earned on what is actually paid and reversed automatically if the sale is cancelled.`) }}</p>
            <p class="muted" style="line-height:1.9;margin-bottom:0">{{ tt('رصيد المحفظة مال مدفوع مقدماً: يُسجَّل في الحسابات كالتزام (أرصدة محافظ العملاء) ويُخصم منه عند الدفع بالمحفظة.', 'Wallet money is prepaid: it is booked as a liability (customer wallet balances) and reduced when paying from the wallet.') }}</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
