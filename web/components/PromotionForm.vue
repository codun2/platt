<script setup lang="ts">
/** Offer form: what it gives, on what, when, coupon. */
const props = defineProps<{ form: any }>()
const api = useApi(); const f = useFmt()
const cats = ref<any[]>([]); const items = ref<any[]>([]); const iq = ref('')
const isBill = computed(() => ['bill_percent', 'bill_amount'].includes(props.form.type))
const isPct = computed(() => ['percent', 'bill_percent'].includes(props.form.type))
const coupon = ref(!!props.form.code)
watch(coupon, v => { if (!v) props.form.code = '' })
onMounted(async () => {
  try { const m = await api.get('/app/inventory/items/meta'); cats.value = m.categories || [] } catch { /* no inventory */ }
  try { const r = await api.get('/app/inventory/items/lookup', { q: '' }); items.value = r.items || [] } catch { /* no inventory */ }
})
let t: any = null
watch(iq, v => { clearTimeout(t); t = setTimeout(async () => { try { items.value = (await api.get('/app/inventory/items/lookup', { q: v })).items || [] } catch { /* offline */ } }, 250) })
const picked = ref<Record<number, string>>({})
watch(items, list => list.forEach((i: any) => { picked.value[i.id] = i.name }), { immediate: true })
function toggle(arr: number[], id: number) { const i = arr.indexOf(id); i >= 0 ? arr.splice(i, 1) : arr.push(id) }
function randomCode() { props.form.code = 'R' + Math.random().toString(36).slice(2, 7).toUpperCase() }
const preview = computed(() => promoSummary(props.form, f))
</script>

<template>
  <div>
    <div class="grid grid-3">
      <div class="field" style="grid-column:span 2"><label>{{ tt('اسم العرض (يظهر في الفاتورة)', 'Offer name (shown on the receipt)') }} <span style="color:var(--primary)">*</span></label><input v-model="form.name" :placeholder="tt('مثال: عرض نهاية الأسبوع على المعمول', 'e.g. Weekend maamoul deal')" /></div>
      <div class="field" style="align-self:end"><label class="row" style="gap:8px"><span class="switch"><input v-model="form.is_active" type="checkbox" /><span /></span> {{ tt('العرض مفعّل', 'Offer active') }}</label></div>
    </div>

    <div class="card-title" style="margin-top:6px"><i class="ti ti-discount-2" /> {{ tt('نوع العرض', 'Offer type') }}</div>
    <div class="type-grid">
      <div v-for="(t, k) in promoType" :key="k" class="type-card" :class="{ on: form.type === k }" @click="form.type = k"><i :class="`ti ${t.icon}`" /><span>{{ t.label }}</span></div>
    </div>
    <div class="grid grid-4" style="margin-top:12px">
      <template v-if="form.type === 'bxgy'">
        <div class="field"><label>{{ tt('يشتري العميل (قطعة)', 'Customer buys (units)') }}</label><input v-model.number="form.buy_qty" type="number" min="1" dir="ltr" /></div>
        <div class="field"><label>{{ tt('ويحصل مجاناً على', 'and gets free') }}</label><input v-model.number="form.get_qty" type="number" min="1" dir="ltr" /></div>
      </template>
      <div v-else class="field"><label>{{ isPct ? tt('نسبة الخصم %', 'Discount %') : tt('مبلغ الخصم', 'Discount amount') }} <span style="color:var(--primary)">*</span></label><input v-model.number="form.value" type="number" min="0" :max="isPct ? 100 : undefined" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('حد أدنى لقيمة المشتريات', 'Minimum basket') }}</label><input v-model.number="form.min_total" type="number" min="0" step="0.001" dir="ltr" /></div>
      <div v-if="form.type !== 'amount'" class="field"><label>{{ tt('أقصى خصم (اختياري)', 'Max discount (optional)') }}</label><input v-model.number="form.max_discount" type="number" min="0" step="0.001" dir="ltr" /></div>
    </div>

    <template v-if="!isBill">
      <div class="card-title" style="margin-top:6px"><i class="ti ti-box" /> {{ tt('ينطبق على', 'Applies to') }}</div>
      <div class="chips" style="margin-bottom:8px">
        <span class="chip" :class="{ on: form.applies_to === 'all' }" @click="form.applies_to = 'all'">{{ tt('كل الأصناف', 'All items') }}</span>
        <span class="chip" :class="{ on: form.applies_to === 'categories' }" @click="form.applies_to = 'categories'">{{ tt('تصنيفات محددة', 'Some categories') }}</span>
        <span class="chip" :class="{ on: form.applies_to === 'items' }" @click="form.applies_to = 'items'">{{ tt('أصناف محددة', 'Some items') }}</span>
      </div>
      <div v-if="form.applies_to === 'categories'" class="chips">
        <span v-for="c in cats" :key="c.id" class="chip" :class="{ on: form.category_ids.includes(c.id) }" @click="toggle(form.category_ids, c.id)">{{ c.name }}</span>
        <p v-if="!cats.length" class="muted">{{ tt('لا توجد تصنيفات — أضفها من الأصناف.', 'No categories yet.') }}</p>
      </div>
      <div v-if="form.applies_to === 'items'">
        <input v-model="iq" :placeholder="tt('ابحث باسم الصنف أو الباركود', 'Search item name or barcode')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;width:100%;max-width:360px;margin-bottom:8px" />
        <div class="chips">
          <span v-for="id in form.item_ids" :key="'s' + id" class="chip on" @click="toggle(form.item_ids, id)">{{ picked[id] || '#' + id }} ×</span>
          <span v-for="i in items.filter((x: any) => !form.item_ids.includes(x.id)).slice(0, 20)" :key="i.id" class="chip" @click="toggle(form.item_ids, i.id)">+ {{ i.name }}</span>
        </div>
      </div>
    </template>

    <div class="card-title" style="margin-top:10px"><i class="ti ti-calendar-time" /> {{ tt('متى يعمل العرض', 'When it runs') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('من تاريخ', 'From') }}</label><input v-model="form.starts_on" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('إلى تاريخ', 'To') }}</label><input v-model="form.ends_on" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('من الساعة', 'From time') }}</label><input v-model="form.time_from" type="time" dir="ltr" /></div>
      <div class="field"><label>{{ tt('إلى الساعة', 'To time') }}</label><input v-model="form.time_to" type="time" dir="ltr" /></div>
    </div>
    <div class="chips"><span class="muted" style="margin-inline-end:6px">{{ tt('الأيام:', 'Days:') }}</span><span v-for="(d, i) in weekDays()" :key="i" class="chip" :class="{ on: form.days.includes(i) }" @click="toggle(form.days, i)">{{ d }}</span><small class="muted">{{ form.days.length ? '' : tt('(كل الأيام)', '(every day)') }}</small></div>

    <div class="card-title" style="margin-top:12px"><i class="ti ti-ticket" /> {{ tt('كوبون', 'Coupon') }}</div>
    <label class="row" style="gap:8px;margin-bottom:8px"><span class="switch"><input v-model="coupon" type="checkbox" /><span /></span> {{ tt('يعمل فقط عند إدخال رمز كوبون عند الكاشير', 'Only when a coupon code is entered at the till') }}</label>
    <div v-if="coupon" class="grid grid-4">
      <div class="field"><label>{{ tt('رمز الكوبون', 'Coupon code') }}</label><div class="row" style="gap:6px"><input v-model="form.code" dir="ltr" style="text-transform:uppercase" placeholder="EID25" /><button type="button" class="btn ghost sm" @click="randomCode"><i class="ti ti-dice" /></button></div></div>
      <div class="field"><label>{{ tt('عدد مرات الاستخدام (اختياري)', 'Usage limit (optional)') }}</label><input v-model.number="form.usage_limit" type="number" min="1" dir="ltr" /></div>
    </div>
    <div v-else class="grid grid-4"><div class="field"><label>{{ tt('حد الاستخدام (اختياري)', 'Usage limit (optional)') }}</label><input v-model.number="form.usage_limit" type="number" min="1" dir="ltr" /></div></div>
    <div class="field"><label>{{ tt('ملاحظات داخلية', 'Internal notes') }}</label><textarea v-model="form.notes" rows="2" /></div>

    <div class="alert info" style="margin-top:6px"><i class="ti ti-eye" /> {{ tt('ملخص العرض:', 'Summary:') }} <b>{{ preview }}</b><span v-if="form.code"> · {{ tt('بالكوبون', 'with coupon') }} <b dir="ltr">{{ form.code.toUpperCase() }}</b></span></div>
  </div>
</template>

<style scoped>
.type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
.type-card { border: 1px solid var(--line); border-radius: 12px; padding: 12px; cursor: pointer; display: flex; gap: 8px; align-items: center; font-size: 13px; }
.type-card i { font-size: 20px; color: var(--muted); }
.type-card.on { border-color: var(--primary); background: var(--primary-50, #fff1f2); font-weight: 700; }
.type-card.on i { color: var(--primary); }
</style>
