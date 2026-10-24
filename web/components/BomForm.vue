<script setup lang="ts">
/** Recipe / bill of materials form with live cost per batch and per unit. */
const props = defineProps<{ form: any; items: any[] }>()
const f = useFmt()
const product = computed(() => props.items.find((i: any) => String(i.id) === String(props.form.item_id)))
const materials = computed(() => props.items.filter((i: any) => String(i.id) !== String(props.form.item_id)))
const itemOf = (id: any) => props.items.find((i: any) => String(i.id) === String(id))
const batch = computed(() => bomCost(props.form, props.items))
const perUnit = computed(() => (+props.form.output_qty > 0 ? batch.value / +props.form.output_qty : 0))
const margin = computed(() => product.value && +product.value.sale_price ? Math.round((+product.value.sale_price - perUnit.value) / +product.value.sale_price * 100) : null)
watch(() => props.form.item_id, () => { if (product.value && !props.form.name) props.form.name = product.value.name })
const unitOf = (i: any) => i?.unit?.symbol || i?.unit?.name || ''
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-cake" /> {{ tt('المنتج', 'Product') }}</div>
    <div class="grid grid-4">
      <div class="field" style="grid-column:span 2"><label>{{ tt('المنتج النهائي', 'Finished product') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.item_id" :placeholder="tt('اختر', 'Select')" :add-label="tt('إضافة منتج', 'Add product')" add-to="/inventory/items/new" add-key="product"><option value="">—</option><option v-for="i in items" :key="i.id" :value="i.id">{{ i.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('اسم الوصفة', 'Recipe name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('إنتاج الدفعة الواحدة', 'Batch yield') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.output_qty" type="number" min="0.001" step="any" dir="ltr" /><small class="muted">{{ unitOf(product) || tt('وحدة', 'units') }}</small></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> {{ tt('المكوّنات لكل دفعة', 'Ingredients per batch') }}</div>
    <table class="table lines-editor">
      <thead><tr><th>{{ tt('المادة الخام', 'Material') }}</th><th style="width:120px">{{ tt('الكمية', 'Qty') }}</th><th style="width:110px">{{ tt('هدر %', 'Waste %') }}</th><th style="width:120px">{{ tt('سعر الوحدة', 'Unit cost') }}</th><th style="width:120px">{{ tt('التكلفة', 'Cost') }}</th><th style="width:44px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, n) in form.lines" :key="n">
          <td><UiSelect v-model="l.item_id" :placeholder="tt('اختر', 'Select')"><option value="">—</option><option v-for="i in materials" :key="i.id" :value="i.id">{{ i.name }}</option></UiSelect></td>
          <td><input v-model.number="l.qty" type="number" min="0" step="any" dir="ltr" /><small class="muted">{{ unitOf(itemOf(l.item_id)) }}</small></td>
          <td><input v-model.number="l.waste_pct" type="number" min="0" max="100" step="0.5" dir="ltr" /></td>
          <td class="num muted">{{ f.money(itemOf(l.item_id)?.cost_price || 0) }}</td>
          <td class="num"><b>{{ f.money((+l.qty || 0) * (1 + (+l.waste_pct || 0) / 100) * +(itemOf(l.item_id)?.cost_price || 0)) }}</b></td>
          <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(n, 1)"><i class="ti ti-x" /></button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="btn ghost sm mt" @click="form.lines.push({ item_id: '', qty: 1, waste_pct: 0 })"><i class="ti ti-plus" /> {{ tt('إضافة مكوّن', 'Add ingredient') }}</button>

    <div class="grid grid-2" style="margin-top:14px;align-items:start">
      <div>
        <div class="field"><label>{{ tt('تكاليف إضافية للدفعة (عمالة، تغليف، غاز…)', 'Extra cost per batch (labor, packaging…)') }}</label><input v-model.number="form.extra_cost" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('طريقة التحضير', 'Method') }}</label><textarea v-model="form.instructions" rows="4" /></div>
        <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('فعّالة', 'Active') }}</label>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('تكلفة الدفعة', 'Batch cost') }}</span><b>{{ f.money(batch) }}</b></div>
        <div class="row between total"><span>{{ tt('تكلفة الوحدة', 'Cost per unit') }}</span><b>{{ f.money(perUnit, true) }}</b></div>
        <div v-if="product" class="row between"><span>{{ tt('سعر البيع', 'Sale price') }}</span><b>{{ f.money(product.sale_price) }}</b></div>
        <div v-if="margin !== null" class="row between" :style="margin < 20 ? 'color:var(--primary)' : 'color:var(--ok)'"><span>{{ tt('هامش الربح', 'Margin') }}</span><b>{{ margin }}%</b></div>
        <small class="muted">{{ tt('الأسعار من «سعر التكلفة» في ملف كل مادة؛ تُحدَّث تلقائياً مع المشتريات.', 'Costs come from each material’s cost price.') }}</small>
      </div>
    </div>
  </div>
</template>
