<script setup lang="ts">
/** Line-items table for invoices/quotes. Mutates `lines` in place. Optional inventory item picker. */
const props = defineProps<{ lines: any[]; canPickItems?: boolean }>()
const api = useApi(); const f = useFmt()
const results = ref<any[]>([]); const searching = ref<number | null>(null); const q = ref('')
let timer: any = null

const lineTotal = (l: any) => Math.round(Math.max(0, (+l.qty || 0) * (+l.unit_price || 0) - (+l.discount || 0)) * 1000) / 1000
function add() { props.lines.push(blankLine()) }
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
      <thead><tr><th style="width:36px">#</th><th>الوصف</th><th style="width:90px">الكمية</th><th style="width:90px">الوحدة</th><th style="width:120px">سعر الوحدة</th><th style="width:100px">خصم</th><th style="width:120px">الإجمالي</th><th style="width:96px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in lines" :key="i">
          <td class="muted">{{ i + 1 }}</td>
          <td>
            <div class="row" style="gap:6px;flex-wrap:nowrap">
              <input v-model="l.description" placeholder="وصف البند أو الخدمة" style="flex:1" />
              <button v-if="canPickItems && !l.item_id" type="button" class="btn ghost sm icon" title="اختيار صنف من المخزون" @click="openSearch(i)"><i class="ti ti-package" /></button>
              <span v-else-if="l.item_id" class="badge info" style="white-space:nowrap;cursor:pointer" title="إلغاء ربط الصنف" @click="clearItem(l)"><i class="ti ti-package" /> صنف ×</span>
            </div>
            <div v-if="searching === i" class="line-search">
              <input id="line-search" v-model="q" placeholder="ابحث بالاسم أو الباركود..." @input="onSearch" @keydown.esc="searching = null" />
              <div class="line-results">
                <button v-for="it in results" :key="it.id" type="button" @click="pick(it)"><b>{{ it.name }}</b><small class="muted"> {{ it.sku || '' }} · {{ f.money(it.sale_price) }}<span v-if="it.stock != null"> · متوفر {{ f.qty(it.stock) }}</span></small></button>
                <p v-if="q && !results.length" class="muted" style="padding:8px;margin:0;font-size:12px">لا نتائج.</p>
              </div>
              <button type="button" class="btn ghost sm" style="margin-top:6px" @click="searching = null">إغلاق</button>
            </div>
          </td>
          <td><input v-model.number="l.qty" type="number" step="0.001" min="0" dir="ltr" /></td>
          <td><input v-model="l.unit" placeholder="قطعة" /></td>
          <td><input v-model.number="l.unit_price" type="number" step="0.001" min="0" dir="ltr" /></td>
          <td><input v-model.number="l.discount" type="number" step="0.001" min="0" dir="ltr" /></td>
          <td class="num"><b>{{ f.money(lineTotal(l)) }}</b></td>
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
    <button type="button" class="btn ghost sm mt" @click="add"><i class="ti ti-plus" /> إضافة بند</button>
  </div>
</template>
