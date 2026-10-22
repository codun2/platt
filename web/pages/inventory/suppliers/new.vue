<script setup lang="ts">
definePageMeta({ title: 'مورد جديد', titleEn: 'New Supplier' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('inventory.manage')) navigateTo('/inventory/suppliers')
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankSupplier())
async function save(stay = false) {
  err.value = ''; saving.value = true
  try { const s = await api.post('/app/inventory/suppliers', supplierPayload(form)); if (stay) Object.assign(form, blankSupplier()); else navigateTo(`/inventory/suppliers/${s.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory/suppliers">{{ tt('الموردون','Suppliers') }}</NuxtLink> › <b>{{ tt('مورد جديد','New supplier') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <SupplierForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ','Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> {{ tt('حفظ وإضافة آخر','Save & add another') }}</button>
        <NuxtLink class="btn ghost" to="/inventory/suppliers">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
