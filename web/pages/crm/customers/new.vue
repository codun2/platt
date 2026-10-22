<script setup lang="ts">
definePageMeta({ title: 'عميل جديد', titleEn: 'New Customer' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('crm.manage')) navigateTo('/crm/customers')

const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankCustomer())

async function save(stay = false) {
  err.value = ''; saving.value = true
  try {
    const c = await api.post('/app/customers', form)
    if (stay) { Object.assign(form, blankCustomer()); err.value = '' }
    else navigateTo(`/crm/customers/${c.id}`)
  } catch (e: any) { err.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/crm/customers">{{ tt('العملاء', 'Customers') }}</NuxtLink> › <b>{{ tt('عميل جديد', 'New customer') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <CustomerForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> {{ tt('حفظ وإضافة آخر', 'Save & add another') }}</button>
        <NuxtLink class="btn ghost" to="/crm/customers">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
