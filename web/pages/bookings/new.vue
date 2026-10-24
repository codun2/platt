<script setup lang="ts">
definePageMeta({ title: 'حجز جديد', titleEn: 'New Booking' })
const api = useApi(); const auth = useAuth(); const route = useRoute(); const { tt } = useI18n()
if (!auth.can('bookings.manage')) navigateTo('/bookings')
const units = ref<any[]>([]); const services = ref<any[]>([]); const customers = ref<any[]>([])
const err = ref(''); const saving = ref(false); const formRef = ref<any>(null)
const form = reactive<any>(blankBooking(typeof route.query.date === 'string' ? route.query.date : undefined))
useDraft('booking-new', form)   // kept if the user jumps to an add page from a dropdown
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
    <div class="breadcrumb"><NuxtLink to="/bookings">{{ tt('الحجوزات','Bookings') }}</NuxtLink> › <b>{{ tt('حجز جديد','New booking') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <BookingForm ref="formRef" :form="form" :units="units" :services="services" :customers="customers" is-new />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || blocked" @click="save"><i class="ti ti-check" /> {{ form.status === 'confirmed' ? tt('حفظ وتأكيد الحجز','Save & confirm') : tt('حفظ الحجز','Save booking') }}</button>
        <NuxtLink class="btn ghost" to="/bookings">{{ tt('إلغاء','Cancel') }}</NuxtLink>
        <small v-if="blocked" class="muted">{{ tt('لا يمكن التأكيد: الوحدة محجوزة في هذه الفترة.','Cannot confirm: unit is booked in this period.') }}</small>
      </div>
    </div>
  </div>
</template>
