<script setup lang="ts">
definePageMeta({ title: 'فاتورة شراء جديدة', titleEn: 'New Purchase' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const { tt } = useI18n()
if (!auth.can('inventory.manage')) navigateTo('/inventory/purchases')
const suppliers = ref<any[]>([]); const meta = ref<any>({ warehouses: [] })
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankPurchase())
useDraft('purchase-new', form)   // kept if the user jumps to an add page from a dropdown
if (route.query.supplier_id) form.supplier_id = String(route.query.supplier_id)
onMounted(async () => {
  suppliers.value = await api.get('/app/inventory/suppliers'); meta.value = await api.get('/app/inventory/items/meta')
  if (!form.warehouse_id) form.warehouse_id = meta.value.warehouses.find((w: any) => w.is_default)?.id || meta.value.warehouses[0]?.id || ''
})
async function save() {
  err.value = ''; saving.value = true
  try { const p = await api.post('/app/inventory/purchases', purchasePayload(form)); navigateTo(`/inventory/purchases/${p.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory/purchases">{{ tt('المشتريات','Purchases') }}</NuxtLink> › <b>{{ tt('فاتورة شراء جديدة','New purchase') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <PurchaseForm :form="form" :suppliers="suppliers" :warehouses="meta.warehouses" is-new />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !form.lines.length" @click="save"><i class="ti ti-check" /> {{ form.receive ? tt('حفظ واستلام','Save & receive') : tt('حفظ كمسودّة','Save as draft') }}</button>
        <NuxtLink class="btn ghost" to="/inventory/purchases">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
