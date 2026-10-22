<script setup lang="ts">
definePageMeta({ title: 'رحلة جديدة', titleEn: 'New Trip' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('travel.manage')) navigateTo('/travel/trips')

const packages = ref<any[]>([])
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankTrip())
onMounted(async () => { packages.value = await api.get('/app/travel/packages') })

async function save() {
  err.value = ''; saving.value = true
  try {
    const t = await api.post('/app/travel/trips', tripPayload(form))
    navigateTo(`/travel/trips/${t.id}`)
  } catch (e: any) { err.value = e.message } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel/trips">{{ tt('الرحلات','Trips') }}</NuxtLink> › <b>{{ tt('رحلة جديدة','New trip') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <TripForm :form="form" :packages="packages" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ ومتابعة','Save & continue') }}</button>
        <NuxtLink class="btn ghost" to="/travel/trips">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
