<script setup lang="ts">
definePageMeta({ title: 'مصروف جديد', titleEn: 'New Expense' })
const api = useApi(); const auth = useAuth()
if (!auth.can('accounting.manage')) navigateTo('/accounting/expenses')
const meta = ref<any>({ categories: [], money: [] }); const suppliers = ref<any[]>([])
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankExpense())
onMounted(async () => {
  meta.value = await api.get('/app/accounting/expenses/meta')
  try { suppliers.value = await api.get('/app/inventory/suppliers') } catch { suppliers.value = [] }   // tenant may not have inventory
  if (!form.paid_from_account_id) form.paid_from_account_id = meta.value.money.find((m: any) => m.system_key === 'cash')?.id || ''
})
async function save(stay = false) {
  err.value = ''; saving.value = true
  try { const e = await api.post('/app/accounting/expenses', expensePayload(form)); if (stay) { Object.assign(form, blankExpense(), { paid_from_account_id: form.paid_from_account_id, account_id: form.account_id }) } else navigateTo(`/accounting/expenses/${e.id}`) }
  catch (x: any) { err.value = x.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting/expenses">المصروفات</NuxtLink> › <b>مصروف جديد</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <ExpenseForm :form="form" :meta="meta" :suppliers="suppliers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> حفظ وترحيل</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> حفظ وإضافة آخر</button>
        <NuxtLink class="btn ghost" to="/accounting/expenses">إلغاء</NuxtLink>
      </div>
    </div>
  </div>
</template>
