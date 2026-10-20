<script setup lang="ts">
definePageMeta({ title: 'المخزون' })
const api = useApi(); const f = useFmt()
const data = ref<any>(null); const low = ref<any[]>([]); const wh = ref(''); const meta = ref<any>(null); const q = ref('')
async function load() { data.value = await api.get('/app/inventory/stock', { warehouse_id: wh.value, q: q.value }) }
onMounted(async () => { meta.value = await api.get('/app/inventory/items/meta'); low.value = await api.get('/app/inventory/low-stock'); await load() })
</script>

<template>
  <div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-box" /></div><div><b>{{ data?.levels?.length ?? '…' }}</b><small>سجلات مخزون</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-coins" /></div><div><b>{{ f.money(data?.stock_value) }}</b><small>قيمة المخزون (بالتكلفة)</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-alert-triangle" /></div><div><b>{{ low.length }}</b><small>أصناف تحت الحد الأدنى</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-building-warehouse" /></div><div><b>{{ meta?.warehouses?.length ?? '…' }}</b><small>مستودعات</small></div></div>
    </div>
    <div class="card">
      <div class="card-title"><i class="ti ti-bolt" /> الوصول السريع</div>
      <div class="quick">
        <NuxtLink to="/inventory/items"><i class="ti ti-box" />الأصناف</NuxtLink>
        <NuxtLink to="/inventory/purchases"><i class="ti ti-truck-delivery" />المشتريات</NuxtLink>
        <NuxtLink to="/inventory/warehouses"><i class="ti ti-building-warehouse" />المستودعات</NuxtLink>
        <NuxtLink to="/inventory/movements"><i class="ti ti-arrows-exchange" />الحركات</NuxtLink>
        <NuxtLink to="/pos"><i class="ti ti-cash-register" />نقطة البيع</NuxtLink>
      </div>
    </div>
    <div class="grid grid-2 mt">
      <div class="card">
        <div class="row between" style="margin-bottom:10px">
          <div class="card-title" style="margin:0"><i class="ti ti-list" /> أرصدة المخزون</div>
          <div class="row">
            <select v-model="wh" style="padding:6px 10px;border:1px solid var(--line);border-radius:8px" @change="load"><option value="">كل المستودعات</option><option v-for="w in meta?.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select>
            <input v-model="q" placeholder="بحث..." style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;width:140px" @keyup.enter="load" />
          </div>
        </div>
        <table class="table"><thead><tr><th>الصنف</th><th>المستودع</th><th>الكمية</th></tr></thead>
          <tbody><tr v-for="l in data?.levels" :key="l.id"><td>{{ l.item?.name }} <small class="muted" dir="ltr">{{ l.item?.sku }}</small></td><td>{{ l.warehouse?.name }}</td><td class="num" :style="Number(l.qty) <= Number(l.item?.min_stock) ? 'color:var(--primary);font-weight:700' : ''">{{ f.qty(l.qty) }}</td></tr></tbody></table>
        <p v-if="data && !data.levels.length" class="empty">لا توجد أرصدة بعد — أضف أصنافاً أو استلم فاتورة شراء.</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-triangle" style="color:var(--warn)" /> تحت الحد الأدنى</div>
        <table class="table"><thead><tr><th>الصنف</th><th>المتاح</th><th>الحد الأدنى</th></tr></thead>
          <tbody><tr v-for="i in low" :key="i.id"><td>{{ i.name }}</td><td class="num" style="color:var(--primary);font-weight:700">{{ f.qty(i.stock) }}</td><td class="num">{{ f.qty(i.min_stock) }}</td></tr></tbody></table>
        <p v-if="!low.length" class="empty">كل الأصناف فوق الحد الأدنى ✔</p>
      </div>
    </div>
  </div>
</template>
