<script setup lang="ts">
definePageMeta({ title: 'الإيجارات والتحصيل', titleEn: 'Rent Collection' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const terms = useRentalTerms(); const route = useRoute()
const d = ref<any>(null); const err = ref(''); const msg = ref('')
const filt = reactive({ scope: String(route.query.scope || 'overdue'), q: '', page: 1 })
const scopes = computed(() => [
  ['overdue', tt('المتأخرة', 'Overdue')], ['month', tt('مستحقة هذا الشهر', 'Due this month')], ['upcoming', tt('خلال 30 يوماً', 'Next 30 days')],
  ['open', tt('كل غير المدفوعة', 'All unpaid')], ['paid', tt('المدفوعة', 'Paid')],
])
const paying = ref<any>(null); const pay = reactive({ amount: 0, method: 'cash', date: f.today(), reference: '' })
async function load() { d.value = await api.get('/app/rental/installments', filt) }
onMounted(load)
function setScope(s: string) { filt.scope = s; filt.page = 1; load() }
function open(i: any) { paying.value = i; Object.assign(pay, { amount: +(+i.amount - +i.paid).toFixed(3), method: 'cash', date: f.today(), reference: '' }) }
async function collect() {
  err.value = ''; msg.value = ''
  try { await api.post(`/app/rental/contracts/${paying.value.contract_id}/collect`, { ...pay, installment_id: paying.value.id }); msg.value = tt('تم تسجيل الدفعة', 'Payment recorded'); paying.value = null; await load() }
  catch (e: any) { err.value = e.message }
}
const sum = computed(() => (d.value?.data || []).reduce((n: number, i: any) => n + (filt.scope === 'paid' ? +i.paid : +i.amount - +i.paid), 0))
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental">{{ tt('الرئيسية', 'Overview') }}</NuxtLink> › <b>{{ tt('التحصيل', 'Collection') }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="chips"><span v-for="[k, l] in scopes" :key="k" class="chip" :class="{ on: filt.scope === k }" @click="setScope(k)">{{ l }}</span></div>
        <div class="row"><input v-model="filt.q" :placeholder="tt('الاسم / الهاتف / العقار / العقد', 'Name / phone / asset / contract')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="filt.page = 1; load()" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
      </div>
      <table class="table">
        <thead><tr><th>{{ terms.renter }}</th><th>{{ terms.asset }}</th><th>{{ tt('العقد', 'Contract') }}</th><th>{{ tt('الاستحقاق', 'Due') }}</th><th>{{ tt('المبلغ', 'Amount') }}</th><th>{{ filt.scope === 'paid' ? tt('تاريخ السداد', 'Paid on') : tt('المتبقي', 'Balance') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="i in d?.data" :key="i.id">
            <td><b>{{ i.contract?.customer?.name }}</b><br /><small class="muted" dir="ltr">{{ i.contract?.customer?.phone }}</small></td>
            <td>{{ i.contract?.asset?.name }}<br /><small v-if="i.contract?.asset?.parent" class="muted">{{ i.contract.asset.parent.name }}</small></td>
            <td><NuxtLink :to="`/rental/contracts/${i.contract_id}`" style="color:var(--info)" dir="ltr">{{ i.contract?.number }}</NuxtLink></td>
            <td dir="ltr" style="text-align:right">{{ i.due_date }}<br /><small v-if="daysLate(i.due_date) && i.status !== 'paid'" style="color:var(--primary)">{{ tt(`متأخر ${daysLate(i.due_date)} يوم`, `${daysLate(i.due_date)}d late`) }}</small></td>
            <td class="num">{{ f.money(i.amount) }}</td>
            <td class="num"><span v-if="filt.scope === 'paid'" dir="ltr">{{ i.paid_at }}</span><b v-else style="color:var(--primary)">{{ f.money(+i.amount - +i.paid) }}</b></td>
            <td><span class="badge" :class="installmentStatus[i.status]?.cls">{{ installmentStatus[i.status]?.label }}</span></td>
            <td><button v-if="['due', 'partial'].includes(i.status) && i.contract?.status === 'active'" class="btn sm" @click="open(i)"><i class="ti ti-cash" /> {{ tt('تحصيل', 'Collect') }}</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.data.length" class="empty">{{ filt.scope === 'overdue' ? tt('لا توجد متأخرات 🎉', 'Nothing overdue 🎉') : tt('لا توجد دفعات.', 'No payments.') }}</p>
      <div v-if="d?.data.length" class="row between mt">
        <small class="muted">{{ tt('إجمالي هذه الصفحة', 'Page total') }}: <b>{{ f.money(sum, true) }}</b> · {{ d.total }} {{ tt('دفعة', 'payments') }}</small>
        <div v-if="d.last_page > 1" class="row"><button class="btn ghost sm" :disabled="filt.page <= 1" @click="filt.page--; load()">‹</button><span class="muted">{{ d.current_page }} / {{ d.last_page }}</span><button class="btn ghost sm" :disabled="filt.page >= d.last_page" @click="filt.page++; load()">›</button></div>
      </div>
    </div>

    <UiModal v-if="paying" :title="tt(`تحصيل — ${paying.contract?.customer?.name}`, `Collect — ${paying.contract?.customer?.name}`)" @close="paying = null">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <p class="muted" style="margin-top:0">{{ paying.contract?.asset?.name }} · {{ tt('استحقاق', 'due') }} <span dir="ltr">{{ paying.due_date }}</span> · {{ tt('المتبقي', 'balance') }} {{ f.money(+paying.amount - +paying.paid) }}</p>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('المبلغ', 'Amount') }}</label><input v-model.number="pay.amount" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="pay.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('التاريخ', 'Date') }}</label><input v-model="pay.date" type="date" dir="ltr" /></div>
        <div class="field"><label>{{ tt('المرجع', 'Reference') }}</label><input v-model="pay.reference" dir="ltr" /></div>
      </div>
      <template #footer><button class="btn ghost" @click="paying = null">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn ok" :disabled="!(pay.amount > 0)" @click="collect"><i class="ti ti-check" /> {{ tt('تسجيل', 'Record') }}</button></template>
    </UiModal>
  </div>
</template>
