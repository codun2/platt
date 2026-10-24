<script setup lang="ts">
definePageMeta({ title: 'مالك / وسيط جديد', titleEn: 'New Owner' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('rental.manage')) navigateTo('/rental/owners')
const err = ref(''); const saving = ref(false); const form = reactive<any>(blankOwner())
async function save() { err.value = ''; saving.value = true; try { const o = await api.post('/app/rental/owners', form); navigateTo(`/rental/owners/${o.id}`) } catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental/owners">{{ tt('الملّاك والوسطاء', 'Owners & brokers') }}</NuxtLink> › <b>{{ tt('جديد', 'New') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <RentalOwnerForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
        <NuxtLink class="btn ghost" to="/rental/owners">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
