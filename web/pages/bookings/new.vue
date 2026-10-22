<script setup lang="ts">
definePageMeta({ title: 'حجز جديد' })
const api = useApi(); const auth = useAuth(); const route = useRoute()
if (!auth.can('bookings.manage')) navigateTo('/bookings')
const units = ref<any[]>([]); const services = ref<any[]>([]); const customers = ref<any[]>([])
const err = ref(''); const saving = ref(false); const formRef = ref<any>(null)
const form = reactive<any>(blankBooking(typeof route.query.date === 'string' ? route.query.date : undefined))
if (route.query.unit_id) form.unit_id = String(route.query.unit_id)
if (route.query.customer_id) form.customer_id = String(route.query.customer_id)
onMounted(async () => { units.value = await api.get('/app/bookings/units'); services.value = await api.get('/app/bookings/services'); customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }) })
const blocked = computed(() => formRef.value?.avail && !formRef.value.avail.available && form.status === 'confirmed')

async function save() {
  err.value = ''; saving.value = true
  try { const b = await api.post('/app/bookings/list', bookingPayload(form)); navigateTo(`/bookings/${b.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/bookings">الحجوزات</NuxtLink> › <b>حجز جديد</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <BookingForm ref="formRef" :form="form" :units="units" :services="services" :customers="customers" is-new />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || blocked" @click="save"><i class="ti ti-check" /> {{ form.status === 'confirmed' ? 'حفظ وتأكيد الحجز' : 'حفظ الحجز' }}</button>
        <NuxtLink class="btn ghost" to="/bookings">إلغاء</NuxtLink>
        <small v-if="blocked" class="muted">لا يمكن التأكيد: الوحدة محجوزة في هذه الفترة.</small>
      </div>
    </div>
  </div>
</template>
