<script setup lang="ts">
/** Work order form: customer, sector details, assignment, labor/part lines. */
const props = defineProps<{ form: any; customers: any[]; technicians: any[]; catalog: any[] }>()
const api = useApi(); const f = useFmt(); const S = useServiceSector()
const walkIn = ref(!props.form.customer_id)
watch(walkIn, v => { if (v) props.form.customer_id = ''; else { props.form.customer_name = ''; props.form.customer_phone = '' } })
const total = computed(() => orderTotal(props.form))
const tax = computed(() => +(useAuth().state.value.tenant?.tax_rate || 0))

// Lines
const iq = ref(''); const found = ref<any[]>([]); let timer: any = null
watch(iq, v => { clearTimeout(timer); if (!v.trim()) { found.value = []; return } timer = setTimeout(async () => { try { found.value = (await api.get('/app/inventory/items/lookup', { q: v })).items.slice(0, 8) } catch { found.value = [] } }, 250) })
function addItem(i: any) { const ex = props.form.lines.find((l: any) => l.item_id === i.id); ex ? ex.qty++ : props.form.lines.push({ kind: 'part', item_id: i.id, description: i.name, qty: 1, unit_price: +i.sale_price, unit_cost: +i.cost_price || 0, stock: i.track_stock ? i.stock : null }); iq.value = ''; found.value = [] }
function addCatalog(c: any) { const ex = props.form.lines.find((l: any) => l.catalog_id === c.id); ex ? ex.qty++ : props.form.lines.push({ kind: c.kind, catalog_id: c.id, description: c.name, qty: 1, unit_price: +c.price, unit_cost: +c.cost || 0 }) }
function addFree(kind: 'labor' | 'part') { props.form.lines.push({ kind, description: '', qty: 1, unit_price: 0, unit_cost: 0 }) }
async function addTechnician(name: string) { const r = await api.post('/app/service/technicians', { name }); props.technicians.push(r); return r.id }
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-user" /> {{ tt('العميل', 'Customer') }}</div>
    <div class="row" style="gap:6px;margin-bottom:8px"><span class="chip" :class="{ on: walkIn }" @click="walkIn = true">{{ tt('عميل سريع', 'Walk-in') }}</span><span class="chip" :class="{ on: !walkIn }" @click="walkIn = false">{{ tt('عميل مسجّل', 'Registered') }}</span></div>
    <div class="grid grid-3">
      <template v-if="walkIn">
        <div class="field"><label>{{ tt('الاسم', 'Name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.customer_name" /></div>
        <div class="field"><label>{{ tt('الجوال', 'Mobile') }}</label><input v-model="form.customer_phone" dir="ltr" /></div>
      </template>
      <div v-else class="field" style="grid-column:span 2"><label>{{ tt('العميل', 'Customer') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.customer_id" :placeholder="tt('اختر', 'Select')" :add-label="tt('إضافة عميل', 'Add customer')" add-to="/crm/customers/new" add-key="customer"><option value="">—</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('الأولوية', 'Priority') }}</label><UiSelect v-model="form.priority"><option v-for="(s, k) in priorities" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i :class="`ti ${S.spec.icon}`" /> {{ tt(S.spec.title[0], S.spec.title[1]) }}</div>
    <div class="grid grid-4">
      <template v-for="fd in S.spec.fields" :key="fd.key">
        <div v-if="fd.type === 'textarea'" class="field" style="grid-column:1/-1"><label>{{ fieldLabel(fd) }}</label><textarea v-model="form.details[fd.key]" rows="2" /></div>
        <div v-else-if="fd.type === 'switch'" class="field" style="align-self:end"><label class="switch"><input v-model="form.details[fd.key]" type="checkbox" /><span /> {{ fieldLabel(fd) }}</label></div>
        <div v-else-if="fd.type === 'select'" class="field"><label>{{ fieldLabel(fd) }}</label><UiSelect v-model="form.details[fd.key]"><option value="">—</option><option v-for="(_, k) in fd.options" :key="k" :value="k">{{ optionLabel(fd, String(k)) }}</option></UiSelect></div>
        <div v-else class="field"><label>{{ fieldLabel(fd) }}</label><input v-if="fd.type === 'number'" v-model.number="form.details[fd.key]" type="number" min="0" step="any" dir="ltr" /><input v-else v-model="form.details[fd.key]" :dir="fd.ltr ? 'ltr' : undefined" /></div>
      </template>
    </div>
    <div class="grid grid-2">
      <div class="field"><label>{{ S.titleLabel }} <span style="color:var(--primary)">*</span></label><textarea v-model="form.title" rows="2" /></div>
      <div class="field"><label>{{ tt('التشخيص / ما تم فحصه', 'Diagnosis') }}</label><textarea v-model="form.diagnosis" rows="2" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-calendar-time" /> {{ tt('الاستلام والتسليم', 'Intake & delivery') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ S.tech }}</label><UiSelect v-model="form.technician_id" :add-label="tt('إضافة', 'Add')" :quick-add="addTechnician"><option value="">—</option><option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('وقت الاستلام', 'Received at') }}</label><input v-model="form.received_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>{{ tt('موعد التسليم المتوقع', 'Promised for') }}</label><input v-model="form.promised_at" type="datetime-local" dir="ltr" /></div>
      <div class="field"><label>{{ tt('التكلفة التقديرية للعميل', 'Estimate given') }}</label><input v-model.number="form.estimate" type="number" min="0" step="0.001" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> {{ tt('الخدمات والقطع', 'Services & parts') }}</div>
    <div v-if="catalog.length" class="chips" style="margin-bottom:8px"><span v-for="c in catalog" :key="c.id" class="chip" @click="addCatalog(c)">+ {{ c.name }} <small class="muted">{{ f.money(c.price) }}</small></span></div>
    <div class="row" style="margin-bottom:8px;position:relative">
      <div v-if="S.usesStock" style="flex:1;position:relative">
        <input v-model="iq" :placeholder="tt('ابحث عن قطعة من المخزون بالاسم أو الباركود…', 'Search a stocked part by name or barcode…')" style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:10px" />
        <div v-if="found.length" class="ui-select-pop" style="display:block;position:absolute;top:100%;inset-inline:0;z-index:20">
          <button v-for="i in found" :key="i.id" type="button" class="ui-select-opt" @click="addItem(i)"><span>{{ i.name }}</span><small class="muted" style="margin-inline-start:auto">{{ f.money(i.sale_price) }}<template v-if="i.track_stock"> · {{ tt('متوفر', 'stock') }} {{ f.qty(i.stock) }}</template></small></button>
        </div>
      </div>
      <button type="button" class="btn ghost sm" @click="addFree('labor')"><i class="ti ti-plus" /> {{ tt('خدمة / أجرة', 'Labor') }}</button>
      <button type="button" class="btn ghost sm" @click="addFree('part')"><i class="ti ti-plus" /> {{ tt('قطعة / مادة', 'Part') }}</button>
    </div>
    <table v-if="form.lines.length" class="table lines-editor">
      <thead><tr><th>{{ tt('البيان', 'Description') }}</th><th style="width:90px">{{ tt('النوع', 'Type') }}</th><th style="width:90px">{{ tt('الكمية', 'Qty') }}</th><th style="width:120px">{{ tt('السعر', 'Price') }}</th><th style="width:110px">{{ tt('الإجمالي', 'Total') }}</th><th style="width:44px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in form.lines" :key="i">
          <td><input v-model="l.description" :readonly="!!l.item_id" /><small v-if="l.item_id && l.stock != null" class="muted">{{ tt('متوفر', 'In stock') }}: {{ f.qty(l.stock) }}</small></td>
          <td><span class="badge" :class="l.kind === 'part' ? 'info' : 'muted'">{{ l.kind === 'part' ? tt('قطعة', 'Part') : tt('خدمة', 'Labor') }}</span></td>
          <td><input v-model.number="l.qty" type="number" min="0.001" step="any" dir="ltr" /></td>
          <td><input v-model.number="l.unit_price" type="number" min="0" step="0.001" dir="ltr" /></td>
          <td class="num"><b>{{ f.money((+l.qty || 0) * (+l.unit_price || 0)) }}</b></td>
          <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted" style="font-size:13px">{{ tt('أضف الخدمات والقطع الآن أو لاحقاً بعد الفحص.', 'Add services and parts now or after inspection.') }}</p>

    <div class="grid grid-2" style="margin-top:12px;align-items:start">
      <div>
        <div class="field"><label>{{ tt('ملاحظات للعميل', 'Notes for customer') }}</label><textarea v-model="form.notes" rows="2" /></div>
        <div class="field"><label>{{ tt('ملاحظات داخلية', 'Internal notes') }}</label><textarea v-model="form.internal_notes" rows="2" /></div>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('المجموع', 'Subtotal') }}</span><b>{{ f.money((form.lines || []).reduce((n: number, l: any) => n + (+l.qty || 0) * (+l.unit_price || 0), 0)) }}</b></div>
        <div class="row between"><span>{{ tt('خصم', 'Discount') }}</span><input v-model.number="form.discount" type="number" min="0" step="0.001" dir="ltr" style="width:110px;padding:4px 8px;border:1px solid var(--line);border-radius:6px" /></div>
        <div class="row between"><span>{{ tt('الضريبة', 'Tax') }} ({{ tax }}%)</span><b>{{ f.money(total * tax / 100) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي', 'Total') }}</span><b>{{ f.money(total * (1 + tax / 100), true) }}</b></div>
      </div>
    </div>
  </div>
</template>
