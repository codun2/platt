<script setup lang="ts">
definePageMeta({ title: 'تعديل العرض', titleEn: 'Edit offer' })
const api = useApi(); const route = useRoute(); const f = useFmt()
const id = route.params.id as string
const err = ref(''); const saving = ref(false); const form = ref<any>(null); const p = ref<any>(null)
onMounted(async () => { p.value = await api.get(`/app/promotions/${id}`); form.value = reactive(promoForm(p.value)) })
usePageTitle(() => p.value?.name)
async function save() {
  err.value = ''; saving.value = true
  try { await api.patch(`/app/promotions/${id}`, promoPayload(form.value)); navigateTo('/promotions') }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div v-if="form">
    <div class="breadcrumb"><NuxtLink to="/promotions">{{ tt('العروض والكوبونات', 'Promotions') }}</NuxtLink> › <b>{{ p.name }}</b></div>
    <div class="card">
      <div class="alert info"><i class="ti ti-chart-bar" /> {{ tt('استُخدم', 'Used') }} <b>{{ p.used_count }}</b> {{ tt('مرة، بخصم إجمالي', 'times, total discount') }} <b>{{ f.money(p.discount_given) }}</b></div>
      <div v-if="err" class="alert bad">{{ err }}</div>
      <PromotionForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <NuxtLink class="btn ghost" to="/promotions">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
