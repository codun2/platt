<script setup lang="ts">
/** Line-items table for invoices/quotes. Mutates `lines` in place. Optional inventory item picker. */
const props = defineProps<{ lines: any[]; canPickItems?: boolean; mode?: LineMode; descPlaceholder?: string; unit?: string; presets?: [string, string][] }>()
const mode = computed<LineMode>(() => props.mode || 'items')
const isItems = computed(() => mode.value === 'items')
const isAmount = computed(() => mode.value === 'amount')
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
const results = ref<any[]>([]); const searching = ref<number | null>(null); const q = ref('')
let timer: any = null

const lineTotal = (l: any) => Math.round(Math.max(0, (+l.qty || 0) * (+l.unit_price || 0) - (+l.discount || 0)) * 1000) / 1000
function add() { props.lines.push({ ...blankLine(), unit: props.unit || '' }) }
/** Quick line from a preset: fills the first empty line, else appends. */
function addPreset(p: [string, string]) {
  const label = tt(p[0], p[1])
  const empty = props.lines.find((l: any) => !l.description && !l.item_id && !(+l.unit_price))
  if (empty) empty.description = label
  else props.lines.push({ ...blankLine(), description: label, unit: props.unit || '' })
}
function remove(i: number) { props.lines.splice(i, 1); if (!props.lines.length) add() }
function move(i: number, d: number) { const j = i + d; if (j < 0 || j >= props.lines.length) return; const [x] = props.lines.splice(i, 1); props.lines.splice(j, 0, x) }

function openSearch(i: number) { searching.value = i; q.value = ''; results.value = []; nextTick(() => (document.getElementById('line-search') as HTMLInputElement)?.focus()) }
function onSearch() {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    try { const r: any = await api.get('/app/inventory/items/lookup', { q: q.value }); results.value = Array.isArray(r) ? r : (r.items || []) } catch { results.value = [] }
  }, 250)
}
function pick(it: any) {
  const l = props.lines[searching.value as number]
  l.item_id = it.id; l.description = it.name; l.unit = it.unit?.symbol || it.unit?.name || l.unit; l.unit_price = +it.sale_price || 0
  searching.value = null
}
function clearItem(l: any) { l.item_id = '' }
</script>

<template>
  <div class="lines-editor">
    <table class="table">
      <thead><tr><th style="width:36px">#</th><th>{{ descPlaceholder || tt('الوصف','Description') }}</th>
        <template v-if="!isAmount"><th style="width:90px">{{ tt('الكمية','Qty') }}</th><th v-if="isItems" style="width:90px">{{ tt('الوحدة','Unit') }}</th><th style="width:120px">{{ isItems ? tt('سعر الوحدة','Unit price') : tt('السعر','Price') }}</th><th v-if="isItems" style="width:100px">{{ tt('خصم','Disc.') }}</th></template>
        <th style="width:140px">{{ isAmount ? tt('المبلغ','Amount') : tt('الإجمالي','Total') }}</th><th style="width:96px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in lines" :key="i">
          <td class="muted">{{ i + 1 }}</td>
          <td>
            <div class="row" style="gap:6px;flex-wrap:nowrap">
              <input v-model="l.description" :placeholder="descPlaceholder || tt('وصف البند أو الخدمة','Item or service description')" style="flex:1" />
              <button v-if="isItems && canPickItems && !l.item_id" type="button" class="btn ghost sm icon" :title="tt('اختيار صنف من المخزون','Pick inventory item')" @click="openSearch(i)"><i class="ti ti-package" /></button>
              <span v-else-if="l.item_id" class="badge info" style="white-space:nowrap;cursor:pointer" :title="tt('إلغاء ربط الصنف','Unlink item')" @click="clearItem(l)"><i class="ti ti-package" /> {{ tt('صنف','Item') }} ×</span>
            </div>
            <div v-if="searching === i" class="line-search">
              <input id="line-search" v-model="q" :placeholder="tt('ابحث بالاسم أو الباركود...','Search by name or barcode...')" @input="onSearch" @keydown.esc="searching = null" />
              <div class="line-results">
                <button v-for="it in results" :key="it.id" type="button" @click="pick(it)"><b>{{ it.name }}</b><small class="muted"> {{ it.sku || '' }} · {{ f.money(it.sale_price) }}<span v-if="it.stock != null"> · {{ tt('متوفر','in stock') }} {{ f.qty(it.stock) }}</span></small></button>
                <p v-if="q && !results.length" class="muted" style="padding:8px;margin:0;font-size:12px">{{ tt('لا نتائج.','No results.') }}</p>
              </div>
              <button type="button" class="btn ghost sm" style="margin-top:6px" @click="searching = null">{{ tt('إغلاق','Close') }}</button>
            </div>
          </td>
          <template v-if="!isAmount">
            <td><input v-model.number="l.qty" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td v-if="isItems"><input v-model="l.unit" :placeholder="unit || tt('قطعة','pcs')" /></td>
            <td><input v-model.number="l.unit_price" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td v-if="isItems"><input v-model.number="l.discount" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td class="num"><b>{{ f.money(lineTotal(l)) }}</b></td>
          </template>
          <!-- Fee-style line: one amount (qty fixed at 1). -->
          <td v-else><input v-model.number="l.unit_price" type="number" step="0.001" min="0" dir="ltr" @input="l.qty = 1; l.discount = 0" /></td>
          <td>
            <div class="row" style="gap:2px;flex-wrap:nowrap">
              <button type="button" class="btn ghost sm icon" :disabled="i === 0" @click="move(i, -1)"><i class="ti ti-chevron-up" /></button>
              <button type="button" class="btn ghost sm icon" :disabled="i === lines.length - 1" @click="move(i, 1)"><i class="ti ti-chevron-down" /></button>
              <button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-x" /></button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="row mt" style="gap:6px;flex-wrap:wrap">
      <button type="button" class="btn ghost sm" @click="add"><i class="ti ti-plus" /> {{ tt('إضافة بند','Add line') }}</button>
      <span v-for="p in presets || []" :key="p[0]" class="chip" @click="addPreset(p)">+ {{ tt(p[0], p[1]) }}</span>
    </div>
  </div>
</template>
