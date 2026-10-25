<script setup lang="ts">
definePageMeta({ title: 'طلب جديد', titleEn: 'New order' })
const api = useApi()
const err = ref(''); const saving = ref(false)
const customers = ref<any[]>([])
const form = reactive<any>(blankPreOrder())
useDraft('preorder-new', form)
onMounted(async () => { customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) })
async function save(print = false) {
  err.value = ''; saving.value = true
  try { const r = await api.post('/app/orders', preOrderPayload(form)); navigateTo(`/orders/${r.order.id}${print ? '?print=ticket' : ''}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/orders">{{ tt('الطلبات المسبقة', 'Pre-orders') }}</NuxtLink> › <b>{{ tt('طلب جديد', 'New order') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <PreOrderForm :form="form" :customers="customers" is-new />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save(false)"><i class="ti ti-check" /> {{ tt('حفظ الطلب', 'Save order') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save(true)"><i class="ti ti-printer" /> {{ tt('حفظ وطباعة تذكرة التحضير', 'Save & print prep ticket') }}</button>
        <NuxtLink class="btn ghost" to="/orders">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
