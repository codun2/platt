<script setup lang="ts">
/** Full inventory item form (new + edit). */
const props = defineProps<{ form: any; meta: any; suppliers: any[]; isNew?: boolean }>()
const f = useFmt(); const { tt } = useI18n()
const api = useApi()
// Inline quick-add for simple lookups: create, add to the list, return the id to select.
async function addCategory(name: string) { const r = await api.post('/app/inventory/categories', { name }); props.meta.categories.push(r); return r.id }
async function addUnit(name: string) { const r = await api.post('/app/inventory/units', { name }); props.meta.units.push(r); return r.id }
const margin = computed(() => { const c = +props.form.cost_price || 0, s = +props.form.sale_price || 0; return c > 0 ? Math.round((s - c) / c * 1000) / 10 : null })
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-box" /> {{ tt('بيانات الصنف','Item info') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('اسم الصنف','Item name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الاسم بالإنجليزية','Name (English)') }}</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>{{ tt('التصنيف','Category') }}</label><UiSelect v-model="form.category_id" :add-label="tt('إضافة تصنيف','Add category')" :quick-add="addCategory"><option value="">—</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('SKU (رمز الصنف)','SKU') }}</label><input v-model="form.sku" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الباركود','Barcode') }}</label><input v-model="form.barcode" dir="ltr" /></div>
      <div class="field"><label>{{ tt('وحدة القياس','Unit') }}</label><UiSelect v-model="form.unit_id" :add-label="tt('إضافة وحدة قياس','Add unit')" :quick-add="addUnit"><option value="">—</option><option v-for="u in meta.units" :key="u.id" :value="u.id">{{ u.name }}<template v-if="u.symbol"> ({{ u.symbol }})</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('العلامة التجارية','Brand') }}</label><input v-model="form.brand" /></div>
      <div class="field"><label>{{ tt('الموديل / الصنف الفرعي','Model') }}</label><input v-model="form.model" dir="ltr" /></div>
      <div class="field"><label>{{ tt('المورد الافتراضي','Default supplier') }}</label><UiSelect v-model="form.supplier_id" :add-label="tt('إضافة مورد','Add supplier')" add-to="/inventory/suppliers/new" add-key="supplier"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-tag" /> {{ tt('التسعير','Pricing') }}</div>
    <div class="grid grid-3">
      <div class="field"><label>{{ tt('سعر التكلفة','Cost price') }}</label><input v-model.number="form.cost_price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('سعر البيع','Sale price') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.sale_price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('هامش الربح','Margin') }}</label><input :value="margin === null ? '—' : margin + '%  (' + f.money((+form.sale_price || 0) - (+form.cost_price || 0)) + ')'" disabled dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-building-warehouse" /> {{ tt('المخزون','Stock') }}</div>
    <label class="switch" style="margin-bottom:10px"><input v-model="form.track_stock" type="checkbox" /><span /> {{ tt('يتتبّع المخزون (أطفئه للخدمات أو الأصناف غير المخزنية)','Track stock (off for services)') }}</label>
    <label v-if="useAuth().hasModule('batches') && form.track_stock" class="switch" style="margin-bottom:10px;display:flex"><input v-model="form.track_expiry" type="checkbox" /><span /> {{ tt('يتتبّع تاريخ الصلاحية والدفعات (يُطلب التاريخ عند الشراء ويُباع الأقرب انتهاءً أولاً)', 'Track expiry & batches (date asked on purchase, earliest expiry sold first)') }}</label>
    <div v-show="form.track_stock" class="grid grid-4">
      <div class="field"><label>{{ tt('الحد الأدنى (تنبيه)','Minimum (alert)') }}</label><input v-model.number="form.min_stock" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('كمية إعادة الطلب','Reorder qty') }}</label><input v-model="form.reorder_qty" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الحد الأقصى','Maximum') }}</label><input v-model="form.max_stock" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الموقع / الرف','Location / shelf') }}</label><input v-model="form.location" :placeholder="tt('مثال: رف A3','e.g. shelf A3')" /></div>
      <div class="field"><label>{{ tt('الوزن (كجم)','Weight (kg)') }}</label><input v-model="form.weight" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div v-if="isNew" class="field"><label>{{ tt('رصيد افتتاحي (المستودع الافتراضي)','Opening stock (default warehouse)') }}</label><input v-model.number="form.opening_qty" type="number" step="0.001" min="0" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> {{ tt('الوصف والملاحظات','Description & notes') }}</div>
    <div class="field"><label>{{ tt('وصف الصنف (يظهر للعميل)','Item description') }}</label><textarea v-model="form.description" rows="2" /></div>
    <div class="field"><label>{{ tt('ملاحظات داخلية','Internal notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط','Active') }}</label>
  </div>
</template>
