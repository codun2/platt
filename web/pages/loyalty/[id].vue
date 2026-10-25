<script setup lang="ts">
definePageMeta({ title: 'كشف ولاء العميل', titleEn: 'Customer loyalty statement' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const id = route.params.id as string
const canManage = computed(() => auth.can('loyalty.manage'))
const d = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const action = ref<'' | 'topup' | 'refund' | 'adjust'>('')
const w = reactive({ amount: null as number | null, method: 'cash', note: '' })
const adj = reactive({ sign: 1, points: null as number | null, note: '' })
async function load(p: any = 1) { d.value = await api.get(`/app/loyalty/members/${id}`, { page: typeof p === 'number' ? p : 1 }) }
onMounted(load)
usePageTitle(() => d.value?.customer?.name)

function start(a: typeof action.value) { action.value = a; err.value = ''; msg.value = ''; Object.assign(w, { amount: null, method: 'cash', note: '' }); Object.assign(adj, { sign: 1, points: null, note: '' }) }
async function submit() {
  err.value = ''; busy.value = true
  try {
    if (action.value === 'adjust') await api.post(`/app/loyalty/members/${id}/adjust`, { points: adj.sign * (+adj.points! || 0), note: adj.note })
    else await api.post(`/app/loyalty/members/${id}/wallet`, { action: action.value, amount: w.amount, method: w.method, note: w.note || null })
    msg.value = ({ topup: tt('تم شحن المحفظة', 'Wallet topped up'), refund: tt('تم استرداد الرصيد', 'Balance refunded'), adjust: tt('تم تعديل النقاط', 'Points adjusted') } as any)[action.value]
    action.value = ''; await load()
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
</script>

<template>
  <div v-if="d">
    <div class="breadcrumb"><NuxtLink to="/loyalty">{{ tt('الولاء والمحفظة', 'Loyalty') }}</NuxtLink> › <b>{{ d.customer.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div class="grid grid-3">
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-award" /></div><div><b>{{ f.qty(d.customer.points) }}</b><small>{{ tt('نقطة — قيمتها', 'points — worth') }} {{ f.money(d.points_value) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(d.customer.wallet_balance) }}</b><small>{{ tt('رصيد المحفظة', 'Wallet balance') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--muted)"><i class="ti ti-user" /></div><div><b style="font-size:15px">{{ d.customer.name }}</b><small dir="ltr" style="text-align:right">{{ d.customer.phone || '—' }}</small></div></div>
    </div>

    <div v-if="canManage" class="card mt">
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button v-if="d.settings.wallet_enabled" class="btn ok" @click="start('topup')"><i class="ti ti-wallet" /> {{ tt('شحن المحفظة', 'Top up wallet') }}</button>
        <button v-if="d.settings.wallet_enabled" class="btn ghost" :disabled="!(+d.customer.wallet_balance > 0)" @click="start('refund')"><i class="ti ti-arrow-back-up" /> {{ tt('استرداد رصيد للعميل', 'Refund balance') }}</button>
        <button class="btn ghost" @click="start('adjust')"><i class="ti ti-adjustments" /> {{ tt('إضافة / خصم نقاط', 'Add / deduct points') }}</button>
        <NuxtLink :to="`/crm/customers/${id}`" class="btn ghost"><i class="ti ti-id" /> {{ tt('ملف العميل', 'Customer file') }}</NuxtLink>
      </div>
      <div v-if="action" class="mt" style="border-top:1px solid var(--line);padding-top:14px">
        <div v-if="err" class="alert bad">{{ err }}</div>
        <div v-if="action !== 'adjust'" class="grid grid-3">
          <div class="field"><label>{{ action === 'topup' ? tt('المبلغ المستلم', 'Amount received') : tt('المبلغ المُعاد للعميل', 'Amount paid back') }} <span style="color:var(--primary)">*</span></label><input v-model.number="w.amount" type="number" step="0.001" min="0" dir="ltr" /><small v-if="action === 'refund'" class="muted">{{ tt('المتاح', 'Available') }}: {{ f.money(d.customer.wallet_balance) }}</small></div>
          <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="w.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option></UiSelect></div>
          <div class="field"><label>{{ tt('ملاحظة', 'Note') }}</label><input v-model="w.note" /></div>
        </div>
        <div v-else class="grid grid-3">
          <div class="field"><label>{{ tt('العملية', 'Operation') }}</label><UiSelect v-model.number="adj.sign"><option :value="1">{{ tt('إضافة نقاط (هدية / تصحيح)', 'Add points (gift / correction)') }}</option><option :value="-1">{{ tt('خصم نقاط (انتهاء / تصحيح)', 'Deduct points (expiry / correction)') }}</option></UiSelect></div>
          <div class="field"><label>{{ tt('عدد النقاط', 'Points') }} <span style="color:var(--primary)">*</span></label><input v-model.number="adj.points" type="number" min="1" step="1" dir="ltr" /></div>
          <div class="field"><label>{{ tt('السبب', 'Reason') }} <span style="color:var(--primary)">*</span></label><input v-model="adj.note" :placeholder="tt('مثال: هدية عيد ميلاد', 'e.g. birthday gift')" /></div>
        </div>
        <div class="row"><button class="btn" :disabled="busy" @click="submit"><i class="ti ti-check" /> {{ tt('تأكيد', 'Confirm') }}</button><button class="btn ghost" @click="action = ''">{{ tt('إلغاء', 'Cancel') }}</button></div>
      </div>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-list" /> {{ tt('كشف الحركات', 'Statement') }}</div>
      <table class="table">
        <thead><tr><th>{{ tt('التاريخ', 'Date') }}</th><th>{{ tt('الحركة', 'Type') }}</th><th>{{ tt('النقاط', 'Points') }}</th><th>{{ tt('رصيد النقاط', 'Points balance') }}</th><th>{{ tt('المحفظة', 'Wallet') }}</th><th>{{ tt('رصيد المحفظة', 'Wallet balance') }}</th><th>{{ tt('المرجع / ملاحظة', 'Reference / note') }}</th></tr></thead>
        <tbody>
          <tr v-for="t in d.transactions.data" :key="t.id" :style="{ opacity: t.reversed ? .55 : 1 }">
            <td><small>{{ f.dt(t.created_at) }}</small><br /><small class="muted">{{ t.user?.name }}</small></td>
            <td><span class="badge" :class="loyaltyType[t.type]?.cls">{{ loyaltyType[t.type]?.label || t.type }}</span></td>
            <td class="num" dir="ltr">{{ +t.points ? (+t.points > 0 ? '+' : '') + f.qty(t.points) : '—' }}</td>
            <td class="num">{{ f.qty(t.points_after) }}</td>
            <td class="num" dir="ltr">{{ +t.amount ? (+t.amount > 0 ? '+' : '') + f.money(t.amount) : '—' }}</td>
            <td class="num">{{ f.money(t.wallet_after) }}</td>
            <td><small>{{ t.reference }} {{ t.method ? '· ' + f.methodLabel(t.method) : '' }} {{ t.note }}</small></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!d.transactions.data.length" class="empty">{{ tt('لا توجد حركات بعد — تُكتسب النقاط تلقائياً عند البيع لهذا العميل من الكاشير.', 'No activity yet — points are earned automatically when selling to this customer at the till.') }}</p>
      <div v-if="d.transactions.data.length" class="mt"><UiPager :p="d.transactions" @go="load" /></div>
    </div>
  </div>
</template>
