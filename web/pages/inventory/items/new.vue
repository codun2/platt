<script setup lang="ts">
definePageMeta({ title: 'صنف جديد', titleEn: 'New Item' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('inventory.manage')) navigateTo('/inventory/items')
const meta = ref<any>({ categories: [], units: [], warehouses: [] }); const suppliers = ref<any[]>([])
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankItem())
useDraft('item-new', form)   // kept if the user jumps to an add page from a dropdown
onMounted(async () => { meta.value = await api.get('/app/inventory/items/meta'); suppliers.value = await api.get('/app/inventory/suppliers') })

async function save(stay = false) {
  err.value = ''; saving.value = true
  try { const i = await api.post('/app/inventory/items', itemPayload(form)); if (stay) Object.assign(form, blankItem()); else navigateTo(`/inventory/items/${i.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory/items">{{ tt('الأصناف','Items') }}</NuxtLink> › <b>{{ tt('صنف جديد','New item') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <ItemForm :form="form" :meta="meta" :suppliers="suppliers" is-new />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ','Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> {{ tt('حفظ وإضافة آخر','Save & add another') }}</button>
        <NuxtLink class="btn ghost" to="/inventory/items">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
