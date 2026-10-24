<script setup lang="ts">
definePageMeta({ title: 'أمر عمل جديد', titleEn: 'New Work Order' })
const api = useApi(); const S = useServiceSector()
const err = ref(''); const saving = ref(false)
const customers = ref<any[]>([]); const technicians = ref<any[]>([]); const catalog = ref<any[]>([])
const form = reactive<any>(blankOrder())
useDraft('service-order-new', form)
usePageTitle(() => `${S.order} ${tt('جديد', 'new')}`)
onMounted(async () => {
  [customers.value, technicians.value, catalog.value] = await Promise.all([api.get('/app/customers', { all: 1, active_only: 1 }), api.get('/app/service/technicians', { active_only: 1 }), api.get('/app/service/catalog', { active_only: 1 })])
})
async function save(print = false) {
  err.value = ''; saving.value = true
  try { const o = await api.post('/app/service/orders', orderPayload(form)); navigateTo(`/service/tickets/${o.id}${print ? '?print=1' : ''}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/service/tickets">{{ S.orders }}</NuxtLink> › <b>{{ S.order }} {{ tt('جديد', 'new') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <ServiceOrderForm :form="form" :customers="customers" :technicians="technicians" :catalog="catalog" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ الاستلام', 'Save intake') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-printer" /> {{ tt('حفظ وطباعة إيصال الاستلام', 'Save & print receipt') }}</button>
        <NuxtLink class="btn ghost" to="/service/tickets">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
