<script setup lang="ts">
definePageMeta({ title: 'عقد جديد', titleEn: 'New Contract' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const terms = useRentalTerms()
if (!auth.can('rental.manage')) navigateTo('/rental/contracts')
const err = ref(''); const saving = ref(false); const assets = ref<any[]>([]); const customers = ref<any[]>([])
const form = reactive<any>(blankContract(terms.isSale))
useDraft('rental-contract-new', form)   // kept if the user jumps to an add page from a dropdown
onMounted(async () => {
  const all = (await api.get('/app/rental/assets', { leasable: 1 })).data
  assets.value = all.filter((a: any) => ['available', 'rented'].includes(a.status))
  customers.value = await api.get('/app/customers', { all: 1, active_only: 1 })
  if (route.query.asset_id) form.asset_id = Number(route.query.asset_id)
})
async function save() {
  err.value = ''; saving.value = true
  try { const c = await api.post('/app/rental/contracts', contractPayload(form)); navigateTo(`/rental/contracts/${c.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental/contracts">{{ tt('العقود', 'Contracts') }}</NuxtLink> › <b>{{ tt('عقد جديد', 'New contract') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <RentalContractForm :form="form" :assets="assets" :customers="customers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ العقد وتوليد الدفعات', 'Save & generate schedule') }}</button>
        <NuxtLink class="btn ghost" to="/rental/contracts">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
