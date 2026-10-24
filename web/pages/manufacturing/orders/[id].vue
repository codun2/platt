<script setup lang="ts">
definePageMeta({ title: 'أمر الإنتاج', titleEn: 'Production Order' })
const api = useApi(); const f = useFmt(); const route = useRoute()
const id = Number(route.params.id)
const o = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const actual = ref(0); const consumed = reactive<Record<number, number>>({})
async function load() { o.value = await api.get(`/app/manufacturing/orders/${id}`); actual.value = +(o.value.actual_qty ?? o.value.planned_qty); for (const l of o.value.lines) consumed[l.id] = +(l.qty_consumed ?? l.qty_required) }
onMounted(load)
usePageTitle(() => o.value ? `${tt('إنتاج', 'Production')} ${o.value.item?.name}` : null)
const isOpen = computed(() => o.value && ['planned', 'in_progress'].includes(o.value.status))
const short = (l: any) => l.available !== null && l.available + 0.0005 < +(consumed[l.id] ?? l.qty_required)
const anyShort = computed(() => (o.value?.lines || []).some(short))
const estCost = computed(() => (o.value?.lines || []).reduce((n: number, l: any) => n + (+consumed[l.id] || 0) * +l.unit_cost, 0) + +(o.value?.extra_cost || 0))
async function act(path: string, body: any = {}, ok = '') { busy.value = true; err.value = ''; try { o.value = await api.post(`/app/manufacturing/orders/${id}/${path}`, body); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { busy.value = false } }
async function complete() { if (!(await uiConfirm(tt(`إنهاء الإنتاج بكمية ${f.qty(actual.value)}؟ ستُصرف المواد من المخزون ويُضاف المنتج.`, `Finish with ${f.qty(actual.value)}? Materials are consumed and the product is added.`), { tone: 'info', okText: tt('إنهاء', 'Finish') }))) return; act('complete', { actual_qty: actual.value, consumed }, tt('تم الإنتاج وتحديث المخزون', 'Produced; stock updated')) }
async function cancel() { if (await uiConfirm(tt('إلغاء أمر الإنتاج؟', 'Cancel this order?'))) act('cancel', {}, tt('تم الإلغاء', 'Cancelled')) }
const unit = (i: any) => i?.unit?.symbol || i?.unit?.name || ''
</script>

<template>
  <div v-if="o">
    <div class="breadcrumb"><NuxtLink to="/manufacturing/orders">{{ tt('أوامر الإنتاج', 'Production orders') }}</NuxtLink> › <b dir="ltr">{{ o.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="card">
      <div class="row between">
        <div><div class="row" style="gap:10px;align-items:center"><h2 style="margin:0">{{ o.item?.name }}</h2><span class="badge" :class="prodStatus[o.status]?.cls">{{ prodStatus[o.status]?.label }}</span></div>
          <p class="muted" style="margin:6px 0 0"><span dir="ltr">{{ o.number }}</span> · {{ tt('الوصفة', 'Recipe') }}: <NuxtLink :to="`/manufacturing/bom/${o.bom_id}`" style="color:var(--info)">{{ o.bom?.name }}</NuxtLink> · {{ tt('مخطط', 'Planned') }} <b>{{ f.qty(o.planned_qty) }} {{ unit(o.item) }}</b><template v-if="o.planned_date"> · <span dir="ltr">{{ o.planned_date }}</span></template></p></div>
        <div v-if="isOpen" class="row" style="gap:8px"><button v-if="o.status === 'planned'" class="btn sm" :disabled="busy" @click="act('start', {}, tt('بدأ الإنتاج', 'Started'))"><i class="ti ti-player-play" /> {{ tt('بدء الإنتاج', 'Start') }}</button><button class="btn ghost sm" style="color:var(--primary)" @click="cancel"><i class="ti ti-ban" /> {{ tt('إلغاء', 'Cancel') }}</button></div>
      </div>
    </div>
    <div v-if="o.status === 'completed'" class="grid grid-3 mt">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-package" /></div><div><b>{{ f.qty(o.actual_qty) }} {{ unit(o.item) }}</b><small>{{ tt('تم إنتاجها', 'Produced') }} <span dir="ltr">{{ o.completed_at }}</span></small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-coins" /></div><div><b>{{ f.money(+o.material_cost + +o.extra_cost) }}</b><small>{{ tt('التكلفة الكلية', 'Total cost') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-calculator" /></div><div><b>{{ f.money(o.unit_cost) }}</b><small>{{ tt('تكلفة الوحدة الفعلية', 'Actual unit cost') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="card-title"><i class="ti ti-list-details" /> {{ tt('المواد', 'Materials') }}</div>
      <div v-if="isOpen && anyShort" class="alert bad"><i class="ti ti-alert-triangle" /> {{ tt('بعض المواد غير كافية في المخزون. اشترِ الناقص أو عدّل الكمية المصروفة.', 'Some materials are short. Buy them or adjust the quantity used.') }}</div>
      <table class="table">
        <thead><tr><th>{{ tt('المادة', 'Material') }}</th><th>{{ tt('المطلوب', 'Required') }}</th><th v-if="isOpen">{{ tt('المتوفر', 'On hand') }}</th><th>{{ isOpen ? tt('المصروف فعلياً', 'Actually used') : tt('المصروف', 'Used') }}</th><th>{{ tt('التكلفة', 'Cost') }}</th></tr></thead>
        <tbody>
          <tr v-for="l in o.lines" :key="l.id" :style="isOpen && short(l) ? 'background:#fef2f2' : ''">
            <td><NuxtLink :to="`/inventory/items/${l.item_id}`" style="color:var(--info)">{{ l.item?.name }}</NuxtLink></td>
            <td>{{ f.qty(l.qty_required) }} {{ unit(l.item) }}</td>
            <td v-if="isOpen"><b :style="short(l) ? 'color:var(--primary)' : 'color:var(--ok)'">{{ l.available === null ? '—' : f.qty(l.available) }}</b></td>
            <td><input v-if="isOpen" v-model.number="consumed[l.id]" type="number" min="0" step="any" dir="ltr" style="width:110px;padding:5px 8px;border:1px solid var(--line);border-radius:8px" /><span v-else>{{ f.qty(l.qty_consumed) }}</span></td>
            <td class="num">{{ f.money((isOpen ? +consumed[l.id] : +l.qty_consumed) * +l.unit_cost) }}</td>
          </tr>
          <tr v-if="+o.extra_cost"><td :colspan="isOpen ? 4 : 3" class="muted">{{ tt('تكاليف إضافية', 'Extra costs') }}</td><td class="num">{{ f.money(o.extra_cost) }}</td></tr>
        </tbody>
      </table>
      <div v-if="isOpen" class="totals-box mt" style="max-width:none">
        <div class="grid grid-3" style="align-items:end">
          <div class="field" style="margin:0"><label>{{ tt('الكمية المنتجة فعلياً', 'Actually produced') }}</label><input v-model.number="actual" type="number" min="0.001" step="any" dir="ltr" /></div>
          <div><small class="muted">{{ tt('تكلفة الوحدة المتوقعة', 'Expected unit cost') }}</small><br /><b>{{ f.money(actual > 0 ? estCost / actual : 0) }}</b></div>
          <button class="btn ok" :disabled="busy || !(actual > 0) || anyShort" @click="complete"><i class="ti ti-check" /> {{ tt('إنهاء الإنتاج', 'Finish production') }}</button>
        </div>
      </div>
      <p v-if="o.notes" class="muted mt">{{ o.notes }}</p>
    </div>
  </div>
</template>
