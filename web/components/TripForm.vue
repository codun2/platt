<script setup lang="ts">
/** Full trip form — shared by the new + edit trip pages. */
const props = defineProps<{ form: any; packages: any[] }>()
const f = useFmt(); const { tt } = useI18n()
const typeLabel: any = { umrah: L('عمرة','Umrah'), hajj: L('حج','Hajj'), tourism: L('سياحة','Tourism') }
const stLabel: any = { scheduled: L('مجدولة','Scheduled'), ongoing: L('جارية','Ongoing'), completed: L('مكتملة','Completed'), cancelled: L('ملغاة','Cancelled') }
const transportLabel: any = { flight: L('طيران','Flight'), bus: L('حافلة','Bus'), private: L('خاص','Private'), mixed: L('مختلط','Mixed') }
const isHoly = computed(() => props.form.type === 'umrah' || props.form.type === 'hajj')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-plane" /> {{ tt('بيانات الرحلة','Trip details') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('اسم الرحلة','Trip name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" :placeholder="tt('مثال: عمرة رمضان 2026','e.g. Ramadan Umrah 2026')" /></div>
      <div class="field"><label>{{ tt('كود الرحلة','Trip code') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('اختياري','Optional')" /></div>
      <div class="field"><label>{{ tt('النوع','Type') }}</label><UiSelect v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('الوجهة','Destination') }}</label><input v-model="form.destination" :placeholder="isHoly ? tt('مكة / المدينة','Makkah / Madinah') : tt('المدينة أو الدولة','City or country')" /></div>
      <div class="field"><label>{{ tt('المغادرة','Departure') }} <span style="color:var(--primary)">*</span></label><input v-model="form.departure_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('العودة','Return') }}</label><input v-model="form.return_date" type="date" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-plane-departure" /> {{ tt('النقل','Transport') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('وسيلة النقل','Transport') }}</label><UiSelect v-model="form.transport"><option value="">—</option><option v-for="(l, k) in transportLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('شركة الطيران','Airline') }}</label><input v-model="form.airline" /></div>
      <div class="field"><label>{{ tt('رقم الرحلة الجوية','Flight no.') }}</label><input v-model="form.flight_no" dir="ltr" /></div>
      <div class="field"><label>{{ tt('نقطة التجمّع / الانطلاق','Boarding point') }}</label><input v-model="form.boarding_point" /></div>
    </div>

    <template v-if="isHoly">
      <div class="card-title" style="margin-top:8px"><i class="ti ti-building-skyscraper" /> {{ tt('الإقامة','Accommodation') }}</div>
      <div class="grid grid-4">
        <div class="field"><label>{{ tt('فندق مكة','Makkah hotel') }}</label><input v-model="form.hotel_makkah" /></div>
        <div class="field"><label>{{ tt('ليالي مكة','Makkah nights') }}</label><input v-model.number="form.nights_makkah" type="number" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('فندق المدينة','Madinah hotel') }}</label><input v-model="form.hotel_madinah" /></div>
        <div class="field"><label>{{ tt('ليالي المدينة','Madinah nights') }}</label><input v-model.number="form.nights_madinah" type="number" min="0" dir="ltr" /></div>
      </div>
    </template>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user-star" /> {{ tt('الإشراف والتسعير','Supervision & pricing') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('مشرف الرحلة','Trip supervisor') }}</label><input v-model="form.supervisor" /></div>
      <div class="field"><label>{{ tt('هاتف المشرف','Supervisor phone') }}</label><input v-model="form.supervisor_phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الطاقة الاستيعابية','Capacity') }}</label><input v-model.number="form.capacity" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('السعر الافتراضي للمسافر','Default traveler price') }}</label><input v-model.number="form.price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الباقة الافتراضية','Default package') }}</label><UiSelect v-model="form.package_id"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} ({{ f.money(p.price) }})</option></UiSelect></div>
      <div class="field"><label>{{ tt('الحالة','Status') }}</label><UiSelect v-model="form.status"><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></UiSelect></div>
    </div>

    <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.note" rows="3" /></div>
  </div>
</template>
