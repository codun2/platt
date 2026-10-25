<script setup lang="ts">
definePageMeta({ title: 'عرض جديد', titleEn: 'New offer' })
const api = useApi()
const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankPromo())
async function save() {
  err.value = ''; saving.value = true
  try { await api.post('/app/promotions', promoPayload(form)); navigateTo('/promotions') }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/promotions">{{ tt('العروض والكوبونات', 'Promotions') }}</NuxtLink> › <b>{{ tt('عرض جديد', 'New offer') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <PromotionForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ العرض', 'Save offer') }}</button>
        <NuxtLink class="btn ghost" to="/promotions">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
