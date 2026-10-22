<script setup lang="ts">
definePageMeta({ title: 'عرض سعر جديد', titleEn: 'New Quote' })
const api = useApi(); const auth = useAuth(); const route = useRoute()
if (!auth.can('crm.manage')) navigateTo('/crm/quotes')

const customers = ref<any[]>([]); const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('quote', +(auth.state.value.tenant?.tax_rate ?? 0)))
if (route.query.customer_id) form.customer_id = String(route.query.customer_id)
// Default validity: 15 days.
{ const d = new Date(); d.setDate(d.getDate() + 15); form.valid_until = d.toISOString().slice(0, 10) }
onMounted(async () => { customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) })

async function save(status: 'draft' | 'sent') {
  err.value = ''; saving.value = true
  try { const q = await api.post('/app/quotes', { ...docPayload(form), status }); navigateTo(`/crm/quotes/${q.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/crm/quotes">عروض الأسعار</NuxtLink> › <b>عرض سعر جديد</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <DocForm kind="quote" :form="form" :customers="customers" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save('sent')"><i class="ti ti-send" /> حفظ وإرسال</button>
        <button class="btn ghost" :disabled="saving" @click="save('draft')"><i class="ti ti-device-floppy" /> حفظ كمسودّة</button>
        <NuxtLink class="btn ghost" to="/crm/quotes">إلغاء</NuxtLink>
      </div>
    </div>
  </div>
</template>
