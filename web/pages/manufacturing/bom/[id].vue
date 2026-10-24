<script setup lang="ts">
definePageMeta({ title: 'الوصفة', titleEn: 'Recipe' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const id = Number(route.params.id)
const b = ref<any>(null); const items = ref<any[]>([]); const editing = ref(route.query.edit === '1'); const form = reactive<any>(blankBom()); const err = ref(''); const msg = ref('')
async function load() { b.value = await api.get(`/app/manufacturing/bom/${id}`); if (!editing.value) Object.assign(form, bomToForm(b.value)) }
onMounted(async () => { await load(); items.value = await api.get('/app/inventory/items', { all: 1 }); if (editing.value) Object.assign(form, bomToForm(b.value)) })
usePageTitle(() => b.value?.name)
async function save() { err.value = ''; try { await api.patch(`/app/manufacturing/bom/${id}`, { ...form, lines: form.lines.filter((l: any) => l.item_id) }); editing.value = false; msg.value = tt('تم الحفظ', 'Saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!(await uiConfirm(tt('حذف هذه الوصفة؟', 'Delete this recipe?')))) return; try { const r = await api.del(`/app/manufacturing/bom/${id}`); if (r.deactivated) { uiAlert(tt('للوصفة أوامر إنتاج سابقة، تم إيقافها بدل حذفها.', 'Has production history; deactivated instead.')); await load() } else navigateTo('/manufacturing/bom') } catch (e: any) { uiAlert(e.message) } }
const perUnit = computed(() => b.value && +b.value.output_qty ? b.value.batch_cost / +b.value.output_qty : 0)
const unit = (i: any) => i?.unit?.symbol || i?.unit?.name || ''
</script>

<template>
  <div v-if="b">
    <div class="breadcrumb"><NuxtLink to="/manufacturing/bom">{{ tt('الوصفات', 'Recipes') }}</NuxtLink> › <b>{{ b.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div v-if="editing" class="card">
      <BomForm :form="form" :items="items" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px"><button class="btn" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button><button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button></div>
    </div>
    <template v-else>
      <div class="card">
        <div class="row between">
          <div><div class="row" style="gap:10px;align-items:center"><h2 style="margin:0">{{ b.name }}</h2><span v-if="!b.is_active" class="badge muted">{{ tt('موقوفة', 'Inactive') }}</span></div>
            <p class="muted" style="margin:6px 0 0">{{ tt('تنتج', 'Yields') }} <b>{{ f.qty(b.output_qty) }} {{ unit(b.item) }}</b> {{ tt('من', 'of') }} <NuxtLink :to="`/inventory/items/${b.item_id}`" style="color:var(--info)">{{ b.item?.name }}</NuxtLink> {{ tt('لكل دفعة', 'per batch') }}</p></div>
          <div class="row" style="gap:8px"><NuxtLink class="btn sm" :to="`/manufacturing/orders/new?bom_id=${b.id}`"><i class="ti ti-player-play" /> {{ tt('أمر إنتاج', 'Produce') }}</NuxtLink><template v-if="auth.can('manufacturing.manage')"><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></template></div>
        </div>
      </div>
      <div class="grid grid-3 mt">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-coins" /></div><div><b>{{ f.money(b.batch_cost) }}</b><small>{{ tt('تكلفة الدفعة', 'Batch cost') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-calculator" /></div><div><b>{{ f.money(perUnit) }}</b><small>{{ tt('تكلفة الوحدة', 'Unit cost') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trending-up" /></div><div><b>{{ +b.item?.sale_price ? Math.round((+b.item.sale_price - perUnit) / +b.item.sale_price * 100) + '%' : '—' }}</b><small>{{ tt('هامش الربح على سعر', 'Margin at price') }} {{ f.money(b.item?.sale_price) }}</small></div></div>
      </div>
      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> {{ tt('المكوّنات لكل دفعة', 'Ingredients per batch') }}</div>
          <table class="table"><thead><tr><th>{{ tt('المادة', 'Material') }}</th><th>{{ tt('الكمية', 'Qty') }}</th><th>{{ tt('هدر', 'Waste') }}</th><th>{{ tt('التكلفة', 'Cost') }}</th></tr></thead>
            <tbody><tr v-for="l in b.lines" :key="l.id"><td><NuxtLink :to="`/inventory/items/${l.item_id}`" style="color:var(--info)">{{ l.item?.name }}</NuxtLink></td><td>{{ f.qty(l.qty) }} {{ unit(l.item) }}</td><td>{{ +l.waste_pct ? +l.waste_pct + '%' : '—' }}</td><td class="num">{{ f.money(+l.qty * (1 + +l.waste_pct / 100) * +(l.item?.cost_price || 0)) }}</td></tr>
              <tr v-if="+b.extra_cost"><td colspan="3" class="muted">{{ tt('تكاليف إضافية', 'Extra costs') }}</td><td class="num">{{ f.money(b.extra_cost) }}</td></tr></tbody></table>
          <div v-if="b.instructions" class="mt"><small class="muted">{{ tt('طريقة التحضير', 'Method') }}</small><p style="white-space:pre-line;margin:4px 0 0">{{ b.instructions }}</p></div>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-history" /> {{ tt('آخر أوامر الإنتاج', 'Recent production') }}</div>
          <table v-if="b.recent_orders.length" class="table"><tbody><tr v-for="o in b.recent_orders" :key="o.id"><td><NuxtLink :to="`/manufacturing/orders/${o.id}`" style="color:var(--info)" dir="ltr">{{ o.number }}</NuxtLink></td><td>{{ f.qty(o.actual_qty ?? o.planned_qty) }}</td><td class="num">{{ +o.unit_cost ? f.money(o.unit_cost) : '—' }}</td><td><span class="badge" :class="prodStatus[o.status]?.cls">{{ prodStatus[o.status]?.label }}</span></td></tr></tbody></table>
          <p v-else class="empty">{{ tt('لم تُنتج بعد.', 'Not produced yet.') }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
