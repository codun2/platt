<script setup lang="ts">
/** Property / unit / vehicle form (new + edit). Fields adapt to the asset type. */
const props = defineProps<{ form: any; owners: any[]; buildings: any[]; editingId?: number | null }>()
const terms = useRentalTerms()
const isVehicle = computed(() => props.form.type === 'vehicle')
const isBuilding = computed(() => props.form.type === 'building')
const isLand = computed(() => props.form.type === 'land')
const parents = computed(() => props.buildings.filter((b: any) => b.id !== props.editingId))
</script>

<template>
  <div>
    <div class="card-title"><i :class="`ti ${assetTypeIcon[form.type] || 'ti-box'}`" /> {{ tt('البيانات الأساسية', 'Basic info') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('النوع', 'Type') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.type"><option v-for="k in terms.types" :key="k" :value="k">{{ assetTypes[k] }}</option></UiSelect></div>
      <div class="field"><label>{{ isVehicle ? tt('اسم السيارة', 'Vehicle name') : tt('الاسم', 'Name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" :placeholder="isVehicle ? tt('مثال: كامري 2024 أبيض', 'e.g. Camry 2024 white') : isBuilding ? tt('مثال: عمارة النخيل', 'e.g. Palm Building') : tt('مثال: شقة 101', 'e.g. Apt 101')" /></div>
      <div class="field"><label>{{ tt('الرمز / الرقم الداخلي', 'Code') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('اختياري', 'Optional')" /></div>
      <div v-if="!isVehicle && !isBuilding" class="field"><label>{{ tt('تابع لبناية', 'Inside building') }}</label><UiSelect v-model="form.parent_id" :placeholder="tt('— مستقل —', '— standalone —')"><option value="">{{ tt('— مستقل —', '— standalone —') }}</option><option v-for="b in parents" :key="b.id" :value="b.id">{{ b.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('المالك', 'Owner') }}</label><UiSelect v-model="form.owner_id" :placeholder="tt('— ملك الشركة —', '— company-owned —')"><option value="">{{ tt('— ملك الشركة —', '— company-owned —') }}</option><option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('الحالة', 'Status') }}</label><UiSelect v-model="form.status"><option v-for="(s, k) in assetStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect><small class="muted">{{ tt('«مؤجّر» يُضبط تلقائياً من العقود', '“Rented” is set automatically by contracts') }}</small></div>
    </div>

    <template v-if="isVehicle">
      <div class="card-title" style="margin-top:8px"><i class="ti ti-car" /> {{ tt('بيانات السيارة', 'Vehicle details') }}</div>
      <div class="grid grid-4">
        <div class="field"><label>{{ tt('رقم اللوحة', 'Plate no.') }}</label><input v-model="form.plate_no" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الشركة المصنّعة', 'Make') }}</label><input v-model="form.make" :placeholder="tt('تويوتا', 'Toyota')" /></div>
        <div class="field"><label>{{ tt('الطراز', 'Model') }}</label><input v-model="form.model" :placeholder="tt('كامري', 'Camry')" /></div>
        <div class="field"><label>{{ tt('سنة الصنع', 'Year') }}</label><input v-model.number="form.year" type="number" min="1950" max="2100" dir="ltr" /></div>
        <div class="field"><label>{{ tt('اللون', 'Color') }}</label><input v-model="form.color" /></div>
        <div class="field"><label>{{ tt('رقم الهيكل (VIN)', 'VIN') }}</label><input v-model="form.vin" dir="ltr" /></div>
        <div class="field"><label>{{ tt('العدّاد (كم)', 'Odometer (km)') }}</label><input v-model.number="form.odometer" type="number" min="0" dir="ltr" /></div>
      </div>
    </template>

    <template v-else>
      <div class="card-title" style="margin-top:8px"><i class="ti ti-map-pin" /> {{ tt('الموقع والمواصفات', 'Location & specs') }}</div>
      <div class="grid grid-4">
        <div class="field"><label>{{ tt('المدينة', 'City') }}</label><input v-model="form.city" /></div>
        <div class="field" style="grid-column:span 3"><label>{{ tt('العنوان / الحي', 'Address / district') }}</label><input v-model="form.address" /></div>
        <div class="field"><label>{{ tt('المساحة (م²)', 'Area (m²)') }}</label><input v-model.number="form.area" type="number" min="0" step="0.01" dir="ltr" /></div>
        <template v-if="!isBuilding && !isLand">
          <div class="field"><label>{{ tt('الغرف', 'Rooms') }}</label><input v-model.number="form.rooms" type="number" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('دورات المياه', 'Bathrooms') }}</label><input v-model.number="form.bathrooms" type="number" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('الطابق', 'Floor') }}</label><input v-model="form.floor" /></div>
          <div class="field"><label>{{ tt('عدّاد الكهرباء', 'Electricity meter') }}</label><input v-model="form.electricity_meter" dir="ltr" /></div>
          <div class="field"><label>{{ tt('عدّاد المياه', 'Water meter') }}</label><input v-model="form.water_meter" dir="ltr" /></div>
          <div class="field" style="align-self:end"><label class="switch"><input v-model="form.furnished" type="checkbox" /><span /> {{ tt('مفروش', 'Furnished') }}</label></div>
        </template>
      </div>
    </template>

    <template v-if="!isBuilding">
      <div class="card-title" style="margin-top:8px"><i class="ti ti-cash" /> {{ tt('التسعير المقترح للعقود', 'Default pricing for contracts') }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ terms.rent }} {{ tt('لكل فترة', 'per period') }}</label><input v-model.number="form.rent_amount" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('التكرار', 'Frequency') }}</label><UiSelect v-model="form.rent_frequency"><option v-for="(l, k) in frequencies" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('التأمين', 'Security deposit') }}</label><input v-model.number="form.deposit" type="number" min="0" step="0.001" dir="ltr" /></div>
      </div>
    </template>
    <div class="field"><label>{{ tt('وصف / ملاحظات', 'Description / notes') }}</label><textarea v-model="form.description" rows="2" /></div>
  </div>
</template>
