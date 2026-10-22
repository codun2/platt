<script setup lang="ts">
definePageMeta({ title: 'المصروف' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id)
const e = ref<any>(null); const meta = ref<any>({ categories: [], money: [] }); const suppliers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankExpense())
const canManage = computed(() => auth.can('accounting.manage'))

async function load() {
  try { e.value = await api.get(`/app/accounting/expenses/${id}`) } catch (x: any) { if (x.status === 404) { notFound.value = true; return } throw x }
  Object.assign(form, expenseToForm(e.value))
}
onMounted(async () => { await load(); meta.value = await api.get('/app/accounting/expenses/meta'); try { suppliers.value = await api.get('/app/inventory/suppliers') } catch { suppliers.value = [] } })
async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/accounting/expenses/${id}`, expensePayload(form)); msg.value = 'تم حفظ التعديلات وإعادة ترحيل القيد'; editing.value = false; await load() }
  catch (x: any) { err.value = x.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!confirm('حذف المصروف وقيده؟')) return; try { await api.del(`/app/accounting/expenses/${id}`); navigateTo('/accounting/expenses') } catch (x: any) { err.value = x.message } }
</script>

<template>
  <div v-if="e">
    <div class="breadcrumb"><NuxtLink to="/accounting/expenses">المصروفات</NuxtLink> › <b dir="ltr">{{ e.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <ExpenseForm :form="form" :meta="meta" :suppliers="suppliers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b>{{ f.money(e.total, true) }}</b><small>الإجمالي المدفوع</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(e.amount) }}</b><small>المبلغ قبل الضريبة · ضريبة {{ f.money(e.tax) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-category" /></div><div><b style="font-size:15px">{{ e.account?.name }}</b><small>نوع المصروف</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b style="font-size:15px">{{ e.paid_from?.name }}</b><small>مدفوع من · {{ expenseMethods[e.payment_method] || e.payment_method }}</small></div></div>
      </div>
      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div><div class="row" style="gap:10px;align-items:center"><h2 style="margin:0" dir="ltr">{{ e.number }}</h2><span class="badge ok">مرحَّل</span></div><p class="muted" style="margin:6px 0 0">{{ f.d(e.date) }}<span v-if="e.creator"> · سجّله {{ e.creator.name }}</span></p></div>
          <div v-if="canManage" class="row" style="gap:8px"><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button><button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> حذف</button></div>
        </div>
        <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
        <div class="grid grid-3">
          <div><small class="muted">المستفيد</small><br /><b>{{ e.payee || '—' }}</b></div>
          <div><small class="muted">المورد</small><br /><b v-if="e.supplier"><NuxtLink :to="`/inventory/suppliers/${e.supplier.id}`" style="color:var(--info)">{{ e.supplier.name }}</NuxtLink></b><b v-else>—</b></div>
          <div><small class="muted">المرجع</small><br /><b dir="ltr">{{ e.reference || '—' }}</b></div>
          <div style="grid-column:1/-1"><small class="muted">الوصف</small><br /><b>{{ e.description || '—' }}</b></div>
          <div v-if="e.notes" style="grid-column:1/-1;background:var(--bg);padding:10px;border-radius:10px"><small class="muted">ملاحظات</small><br />{{ e.notes }}</div>
        </div>
      </div>
      <div v-if="e.entry" class="card">
        <div class="card-title"><i class="ti ti-book" /> القيد المحاسبي <NuxtLink :to="`/accounting/journal/${e.entry.id}`" style="color:var(--info);font-size:13px" dir="ltr">{{ e.entry.number }}</NuxtLink></div>
        <table class="table"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead>
          <tbody><tr v-for="l in e.entry.lines" :key="l.id"><td>{{ l.account?.code }} — {{ l.account?.name }}</td><td class="num">{{ +l.debit ? f.money(l.debit) : '' }}</td><td class="num">{{ +l.credit ? f.money(l.credit) : '' }}</td></tr></tbody></table>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>المصروف غير موجود</h2><NuxtLink to="/accounting/expenses" class="btn ghost mt">العودة</NuxtLink></div>
</template>
