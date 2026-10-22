<script setup lang="ts">
/** Full inventory item form (new + edit). */
const props = defineProps<{ form: any; meta: any; suppliers: any[]; isNew?: boolean }>()
const f = useFmt()
const margin = computed(() => { const c = +props.form.cost_price || 0, s = +props.form.sale_price || 0; return c > 0 ? Math.round((s - c) / c * 1000) / 10 : null })
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-box" /> بيانات الصنف</div>
    <div class="grid grid-3">
      <div class="field"><label>اسم الصنف <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>الاسم بالإنجليزية</label><input v-model="form.name_en" dir="ltr" /></div>
      <div class="field"><label>التصنيف</label><UiSelect v-model="form.category_id"><option value="">—</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect></div>
      <div class="field"><label>SKU (رمز الصنف)</label><input v-model="form.sku" dir="ltr" /></div>
      <div class="field"><label>الباركود</label><input v-model="form.barcode" dir="ltr" /></div>
      <div class="field"><label>وحدة القياس</label><UiSelect v-model="form.unit_id"><option value="">—</option><option v-for="u in meta.units" :key="u.id" :value="u.id">{{ u.name }}<template v-if="u.symbol"> ({{ u.symbol }})</template></option></UiSelect></div>
      <div class="field"><label>العلامة التجارية</label><input v-model="form.brand" /></div>
      <div class="field"><label>الموديل / الصنف الفرعي</label><input v-model="form.model" dir="ltr" /></div>
      <div class="field"><label>المورد الافتراضي</label><UiSelect v-model="form.supplier_id"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-tag" /> التسعير</div>
    <div class="grid grid-3">
      <div class="field"><label>سعر التكلفة</label><input v-model.number="form.cost_price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>سعر البيع <span style="color:var(--primary)">*</span></label><input v-model.number="form.sale_price" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>هامش الربح</label><input :value="margin === null ? '—' : margin + '%  (' + f.money((+form.sale_price || 0) - (+form.cost_price || 0)) + ')'" disabled dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-building-warehouse" /> المخزون</div>
    <label class="switch" style="margin-bottom:10px"><input v-model="form.track_stock" type="checkbox" /><span /> يتتبّع المخزون (أطفئه للخدمات أو الأصناف غير المخزنية)</label>
    <div v-show="form.track_stock" class="grid grid-4">
      <div class="field"><label>الحد الأدنى (تنبيه)</label><input v-model.number="form.min_stock" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>كمية إعادة الطلب</label><input v-model="form.reorder_qty" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>الحد الأقصى</label><input v-model="form.max_stock" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div class="field"><label>الموقع / الرف</label><input v-model="form.location" placeholder="مثال: رف A3" /></div>
      <div class="field"><label>الوزن (كجم)</label><input v-model="form.weight" type="number" step="0.001" min="0" dir="ltr" /></div>
      <div v-if="isNew" class="field"><label>رصيد افتتاحي (المستودع الافتراضي)</label><input v-model.number="form.opening_qty" type="number" step="0.001" min="0" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-note" /> الوصف والملاحظات</div>
    <div class="field"><label>وصف الصنف (يظهر للعميل)</label><textarea v-model="form.description" rows="2" /></div>
    <div class="field"><label>ملاحظات داخلية</label><textarea v-model="form.notes" rows="2" /></div>
    <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label>
  </div>
</template>
