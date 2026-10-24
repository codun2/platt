<script setup lang="ts">
definePageMeta({ title: 'أمر إنتاج جديد', titleEn: 'New Production Order' })
const api = useApi(); const f = useFmt(); const route = useRoute()
const err = ref(''); const saving = ref(false); const boms = ref<any[]>([]); const warehouses = ref<any[]>([])
const form = reactive<any>({ bom_id: route.query.bom_id ? Number(route.query.bom_id) : '', planned_qty: 0, planned_date: f.today(), warehouse_id: '', notes: '' })
onMounted(async () => {
  boms.value = (await api.get('/app/manufacturing/bom')).filter((b: any) => b.is_active)
  warehouses.value = (await api.get('/app/inventory/items/meta')).warehouses || []
  if (bom.value && !form.planned_qty) form.planned_qty = +bom.value.output_qty
})
const bom = computed(() => boms.value.find((b: any) => String(b.id) === String(form.bom_id)))
watch(bom, b => { if (b && !form.planned_qty) form.planned_qty = +b.output_qty })
const factor = computed(() => bom.value && +bom.value.output_qty ? +form.planned_qty / +bom.value.output_qty : 0)
async function save() { err.value = ''; saving.value = true; try { const o = await api.post('/app/manufacturing/orders', { ...form, warehouse_id: form.warehouse_id || null }); navigateTo(`/manufacturing/orders/${o.id}`) } catch (e: any) { err.value = e.message } finally { saving.value = false } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/manufacturing/orders">{{ tt('أوامر الإنتاج', 'Production orders') }}</NuxtLink> › <b>{{ tt('جديد', 'New') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-4">
        <div class="field" style="grid-column:span 2"><label>{{ tt('الوصفة', 'Recipe') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.bom_id" :placeholder="tt('اختر', 'Select')" :add-label="tt('وصفة جديدة', 'New recipe')" add-to="/manufacturing/bom/new" add-key="bom"><option value="">—</option><option v-for="b in boms" :key="b.id" :value="b.id">{{ b.name }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('الكمية المطلوب إنتاجها', 'Quantity to produce') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.planned_qty" type="number" min="0.001" step="any" dir="ltr" /><small v-if="bom" class="muted">= {{ +factor.toFixed(2) }} {{ tt('دفعة', 'batches') }}</small></div>
        <div class="field"><label>{{ tt('تاريخ الإنتاج', 'Production date') }}</label><input v-model="form.planned_date" type="date" dir="ltr" /></div>
        <div v-if="warehouses.length > 1" class="field"><label>{{ tt('المستودع', 'Warehouse') }}</label><UiSelect v-model="form.warehouse_id"><option value="">{{ tt('الافتراضي', 'Default') }}</option><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
      </div>
      <div v-if="bom" class="totals-box" style="max-width:none">
        <div class="card-title" style="margin:0 0 6px"><i class="ti ti-list-details" /> {{ tt('المواد المطلوبة', 'Materials needed') }}</div>
        <div v-for="l in bom.lines" :key="l.id" class="row between"><span>{{ l.item?.name }}</span><b>{{ f.qty(+l.qty * (1 + +l.waste_pct / 100) * factor) }}</b></div>
        <div class="row between total"><span>{{ tt('التكلفة التقديرية', 'Estimated cost') }}</span><b>{{ f.money(bom.batch_cost * factor, true) }}</b></div>
      </div>
      <div class="field mt"><label>{{ tt('ملاحظات', 'Notes') }}</label><input v-model="form.notes" /></div>
      <div class="row" style="gap:10px;margin-top:12px"><button class="btn" :disabled="saving || !form.bom_id || !(form.planned_qty > 0)" @click="save"><i class="ti ti-check" /> {{ tt('إنشاء الأمر', 'Create order') }}</button><NuxtLink class="btn ghost" to="/manufacturing/orders">{{ tt('إلغاء', 'Cancel') }}</NuxtLink></div>
    </div>
  </div>
</template>
