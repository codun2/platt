<script setup lang="ts">
/** Full trip form — shared by the new + edit trip pages. */
const props = defineProps<{ form: any; packages: any[] }>()
const f = useFmt()
const typeLabel: any = { umrah: 'عمرة', hajj: 'حج', tourism: 'سياحة' }
const stLabel: any = { scheduled: 'مجدولة', ongoing: 'جارية', completed: 'مكتملة', cancelled: 'ملغاة' }
const transportLabel: any = { flight: 'طيران', bus: 'حافلة', private: 'خاص', mixed: 'مختلط' }
const isHoly = computed(() => props.form.type === 'umrah' || props.form.type === 'hajj')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-plane" /> بيانات الرحلة</div>
    <div class="grid grid-3">
      <div class="field"><label>اسم الرحلة <span style="color:var(--primary)">*</span></label><input v-model="form.name" placeholder="مثال: عمرة رمضان 2026" /></div>
      <div class="field"><label>كود الرحلة</label><input v-model="form.code" dir="ltr" placeholder="اختياري" /></div>
      <div class="field"><label>النوع</label><select v-model="form.type"><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></select></div>
      <div class="field"><label>الوجهة</label><input v-model="form.destination" :placeholder="isHoly ? 'مكة / المدينة' : 'المدينة أو الدولة'" /></div>
      <div class="field"><label>المغادرة <span style="color:var(--primary)">*</span></label><input v-model="form.departure_date" type="date" dir="ltr" /></div>
      <div class="field"><label>العودة</label><input v-model="form.return_date" type="date" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-plane-departure" /> النقل</div>
    <div class="grid grid-4">
      <div class="field"><label>وسيلة النقل</label><select v-model="form.transport"><option value="">—</option><option v-for="(l, k) in transportLabel" :key="k" :value="k">{{ l }}</option></select></div>
      <div class="field"><label>شركة الطيران</label><input v-model="form.airline" /></div>
      <div class="field"><label>رقم الرحلة الجوية</label><input v-model="form.flight_no" dir="ltr" /></div>
      <div class="field"><label>نقطة التجمّع / الانطلاق</label><input v-model="form.boarding_point" /></div>
    </div>

    <template v-if="isHoly">
      <div class="card-title" style="margin-top:8px"><i class="ti ti-building-skyscraper" /> الإقامة</div>
      <div class="grid grid-4">
        <div class="field"><label>فندق مكة</label><input v-model="form.hotel_makkah" /></div>
        <div class="field"><label>ليالي مكة</label><input v-model.number="form.nights_makkah" type="number" min="0" dir="ltr" /></div>
        <div class="field"><label>فندق المدينة</label><input v-model="form.hotel_madinah" /></div>
        <div class="field"><label>ليالي المدينة</label><input v-model.number="form.nights_madinah" type="number" min="0" dir="ltr" /></div>
      </div>
    </template>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-user-star" /> الإشراف والتسعير</div>
    <div class="grid grid-3">
      <div class="field"><label>مشرف الرحلة</label><input v-model="form.supervisor" /></div>
      <div class="field"><label>هاتف المشرف</label><input v-model="form.supervisor_phone" dir="ltr" /></div>
      <div class="field"><label>الطاقة الاستيعابية</label><input v-model.number="form.capacity" type="number" min="0" dir="ltr" /></div>
      <div class="field"><label>السعر الافتراضي للمسافر</label><input v-model.number="form.price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>الباقة الافتراضية</label><select v-model="form.package_id"><option value="">—</option><option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }} ({{ f.money(p.price) }})</option></select></div>
      <div class="field"><label>الحالة</label><select v-model="form.status"><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></select></div>
    </div>

    <div class="field"><label>ملاحظات</label><textarea v-model="form.note" rows="3" /></div>
  </div>
</template>
