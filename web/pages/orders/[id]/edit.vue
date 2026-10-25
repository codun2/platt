<script setup lang="ts">
definePageMeta({ title: 'تعديل الطلب', titleEn: 'Edit order' })
const api = useApi(); const route = useRoute()
const id = route.params.id as string
const err = ref(''); const saving = ref(false)
const customers = ref<any[]>([]); const form = ref<any>(null); const number = ref('')
onMounted(async () => {
  const [r, c] = await Promise.all([api.get(`/app/orders/${id}`), api.get('/app/customers', { all: 1, active_only: 1 })])
  customers.value = c; number.value = r.order.number; form.value = reactive(preOrderForm(r.order))
})
usePageTitle(() => number.value ? `${tt('تعديل الطلب', 'Edit order')} ${number.value}` : null)
async function save() {
  err.value = ''; saving.value = true
  try { await api.patch(`/app/orders/${id}`, preOrderPayload(form.value)); navigateTo(`/orders/${id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div v-if="form">
    <div class="breadcrumb"><NuxtLink to="/orders">{{ tt('الطلبات المسبقة', 'Pre-orders') }}</NuxtLink> › <NuxtLink :to="`/orders/${id}`" dir="ltr">{{ number }}</NuxtLink> › <b>{{ tt('تعديل', 'Edit') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <PreOrderForm :form="form" :customers="customers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <NuxtLink class="btn ghost" :to="`/orders/${id}`">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
