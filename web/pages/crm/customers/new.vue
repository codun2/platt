<script setup lang="ts">
definePageMeta({ title: 'عميل جديد', titleEn: 'New Customer' })
const api = useApi(); const auth = useAuth()
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
    <div class="breadcrumb"><NuxtLink to="/crm/customers">العملاء</NuxtLink> › <b>عميل جديد</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <CustomerForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> حفظ</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-plus" /> حفظ وإضافة آخر</button>
        <NuxtLink class="btn ghost" to="/crm/customers">إلغاء</NuxtLink>
      </div>
    </div>
  </div>
</template>
