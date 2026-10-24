<script setup lang="ts">
definePageMeta({ title: 'فاتورة جديدة', titleEn: 'New Invoice' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const { tt } = useI18n()
if (!auth.can('crm.manage')) navigateTo('/crm/invoices')

const customers = ref<any[]>([]); const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('invoice', +(auth.state.value.tenant?.tax_rate ?? 0)))
useDraft('invoice-new', form)   // kept if the user jumps to an add page from a dropdown
if (route.query.customer_id) form.customer_id = String(route.query.customer_id)
onMounted(async () => { customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) })

async function save(status: 'draft' | 'sent') {
  err.value = ''; saving.value = true
  try { const inv = await api.post('/app/invoices', { ...docPayload(form), status }); navigateTo(`/crm/invoices/${inv.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/crm/invoices">{{ tt('الفواتير','Invoices') }}</NuxtLink> › <b>{{ tt('فاتورة جديدة','New invoice') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <DocForm kind="invoice" :form="form" :customers="customers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save('sent')"><i class="ti ti-send" /> {{ tt('حفظ وإصدار','Save & issue') }}</button>
        <button class="btn ghost" :disabled="saving" @click="save('draft')"><i class="ti ti-device-floppy" /> {{ tt('حفظ كمسودّة','Save as draft') }}</button>
        <NuxtLink class="btn ghost" to="/crm/invoices">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
