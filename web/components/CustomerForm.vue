<script setup lang="ts">
/** Full customer profile form — generic fields covering every sector. */
const props = defineProps<{ form: any }>()
const auth = useAuth()
const sector = computed(() => auth.state.value.tenant?.sector_preset?.key || '')
// Sectors where official ID / nationality / birth date really matter.
const needsId = computed(() => ['hotel', 'chalets', 'halls', 'travel', 'car_rental', 'real_estate', 'car_showroom'].includes(sector.value))
const isCompany = computed(() => props.form.customer_type === 'company')
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-id-badge-2" /> بيانات أساسية</div>
    <div class="grid grid-3">
      <div class="field">
        <label>نوع العميل</label>
        <UiSelect v-model="form.customer_type">
          <option value="individual">فرد</option>
          <option value="company">شركة / جهة</option>
        </UiSelect>
      </div>
      <div class="field"><label>الاسم <span style="color:var(--primary)">*</span></label><input v-model="form.name" placeholder="اسم العميل" /></div>
      <div class="field"><label>كود العميل</label><input v-model="form.code" dir="ltr" placeholder="اختياري" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-phone" /> بيانات التواصل</div>
    <div class="grid grid-3">
      <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>هاتف إضافي</label><input v-model="form.phone2" dir="ltr" /></div>
      <div class="field"><label>واتساب</label><input v-model="form.whatsapp" dir="ltr" /></div>
      <div class="field"><label>البريد الإلكتروني</label><input v-model="form.email" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-license" /> الهوية والمستندات</div>
    <div class="grid grid-3">
      <div class="field">
        <label>نوع الهوية</label>
        <UiSelect v-model="form.id_type">
          <option value="">—</option>
          <option value="national_id">بطاقة مدنية / هوية</option>
          <option value="passport">جواز سفر</option>
          <option value="resident_id">إقامة</option>
          <option value="commercial_reg">سجل تجاري</option>
        </UiSelect>
      </div>
      <div class="field">
        <label>رقم الهوية / المستند <span v-if="needsId" class="muted" style="font-weight:400">(مطلوب للعقود)</span></label>
        <input v-model="form.id_number" dir="ltr" />
      </div>
      <div class="field"><label>الرقم الضريبي</label><input v-model="form.tax_number" dir="ltr" /></div>
      <div class="field"><label>الجنسية</label><input v-model="form.nationality" /></div>
      <div class="field"><label>تاريخ الميلاد</label><input v-model="form.birth_date" type="date" dir="ltr" /></div>
      <div class="field">
        <label>الجنس</label>
        <UiSelect v-model="form.gender"><option value="">—</option><option value="male">ذكر</option><option value="female">أنثى</option></UiSelect>
      </div>
    </div>

    <div v-show="isCompany" class="card-title" style="margin-top:8px"><i class="ti ti-building" /> بيانات الجهة</div>
    <div v-show="isCompany" class="grid grid-2">
      <div class="field"><label>اسم الشركة / الجهة</label><input v-model="form.company_name" /></div>
      <div class="field"><label>الشخص المسؤول</label><input v-model="form.contact_person" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> العنوان</div>
    <div class="grid grid-3">
      <div class="field"><label>الدولة</label><input v-model="form.country" /></div>
      <div class="field"><label>المدينة / الولاية</label><input v-model="form.city" /></div>
      <div class="field"><label>المنطقة / الحي</label><input v-model="form.area" /></div>
    </div>
    <div class="field"><label>العنوان التفصيلي</label><input v-model="form.address" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> التصنيف والبيانات المالية</div>
    <div class="grid grid-4">
      <div class="field">
        <label>التصنيف</label>
        <UiSelect v-model="form.category">
          <option value="">عادي</option>
          <option value="vip">مميّز (VIP)</option>
          <option value="wholesale">جملة</option>
          <option value="retail">تجزئة</option>
          <option value="government">جهة حكومية</option>
        </UiSelect>
      </div>
      <div class="field"><label>حد الائتمان</label><input v-model="form.credit_limit" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>رصيد افتتاحي</label><input v-model="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
      <div class="field"><label>أيام السداد (آجل)</label><input v-model="form.payment_terms_days" type="number" min="0" max="365" dir="ltr" /></div>
    </div>
    <div class="field"><label>الوسوم (افصل بينها بفاصلة)</label><input v-model="form.tags" placeholder="مثال: عميل دائم, مشروع المول" /></div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> ملاحظات</div>
    <div class="field"><textarea v-model="form.notes" rows="3" /></div>

    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label>
  </div>
</template>
