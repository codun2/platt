<script setup lang="ts">
definePageMeta({ title: 'عقار / وحدة جديدة', titleEn: 'New Property' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const terms = useRentalTerms()
if (!auth.can('rental.manage')) navigateTo('/rental/assets')
const err = ref(''); const saving = ref(false); const owners = ref<any[]>([]); const buildings = ref<any[]>([])
const form = reactive<any>(blankAsset(terms.isVehicle))
if (route.query.parent_id) { form.parent_id = Number(route.query.parent_id); form.type = 'apartment' }
onMounted(async () => { owners.value = await api.get('/app/rental/owners'); buildings.value = (await api.get('/app/rental/assets', { type: 'building' })).data })
// A unit inherits its building's owner/location when added from the building page.
watch(() => form.parent_id, id => { const b = buildings.value.find((x: any) => x.id === Number(id)); if (b) { form.owner_id = form.owner_id || b.owner_id || ''; form.city = form.city || b.city || ''; form.address = form.address || b.address || '' } })
async function save(stay = false) {
  err.value = ''; saving.value = true
  try {
    const a = await api.post('/app/rental/assets', assetPayload(form))
    if (stay) { const keep = { parent_id: form.parent_id, owner_id: form.owner_id, type: form.type, city: form.city, address: form.address, rent_frequency: form.rent_frequency }; Object.assign(form, blankAsset(terms.isVehicle), keep) }
    else navigateTo(`/rental/assets/${a.id}`)
  } catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental/assets">{{ terms.assets }}</NuxtLink> › <b>{{ terms.newAsset }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <RentalAssetForm :form="form" :owners="owners" :buildings="buildings" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> {{ tt('حفظ وإضافة أخرى', 'Save & add another') }}</button>
        <NuxtLink class="btn ghost" to="/rental/assets">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
