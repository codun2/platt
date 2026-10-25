<script setup lang="ts">
definePageMeta({ title: 'الوصفات', titleEn: 'Recipes' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([])
onMounted(async () => { rows.value = await api.get('/app/manufacturing/bom') })
const perUnit = (b: any) => +b.output_qty ? b.batch_cost / +b.output_qty : 0
const margin = (b: any) => +b.item?.sale_price ? Math.round((+b.item.sale_price - perUnit(b)) / +b.item.sale_price * 100) : null
// Long lists are shown 25 per page.
const { rows: pRows, pager: pInfo, go: pGo } = usePaged(() => rows.value || [], 25)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/manufacturing">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('الوصفات', 'Recipes') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px"><span class="muted">{{ rows.length }} {{ tt('وصفة', 'recipes') }}</span><NuxtLink v-if="auth.can('manufacturing.manage')" class="btn" to="/manufacturing/bom/new"><i class="ti ti-plus" /> {{ tt('وصفة جديدة', 'New recipe') }}</NuxtLink></div>
      <table class="table">
        <thead><tr><th>{{ tt('الوصفة', 'Recipe') }}</th><th>{{ tt('المنتج', 'Product') }}</th><th>{{ tt('الدفعة', 'Batch') }}</th><th>{{ tt('المكوّنات', 'Ingredients') }}</th><th>{{ tt('تكلفة الوحدة', 'Unit cost') }}</th><th>{{ tt('سعر البيع', 'Price') }}</th><th>{{ tt('الهامش', 'Margin') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="b in pRows" :key="b.id" :style="b.is_active ? '' : 'opacity:.55'">
            <td><NuxtLink :to="`/manufacturing/bom/${b.id}`" style="color:var(--info);font-weight:700">{{ b.name }}</NuxtLink></td>
            <td>{{ b.item?.name }}</td><td>{{ f.qty(b.output_qty) }} {{ b.item?.unit?.symbol || b.item?.unit?.name || '' }}</td><td>{{ b.lines.length }}</td>
            <td class="num"><b>{{ f.money(perUnit(b)) }}</b></td><td class="num">{{ f.money(b.item?.sale_price) }}</td>
            <td><span v-if="margin(b) !== null" class="badge" :class="margin(b)! < 20 ? 'bad' : margin(b)! < 40 ? 'warn' : 'ok'">{{ margin(b) }}%</span></td>
            <td><NuxtLink class="btn ghost sm" :to="`/manufacturing/orders/new?bom_id=${b.id}`"><i class="ti ti-player-play" /> {{ tt('إنتاج', 'Produce') }}</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="pInfo" @go="pGo" />
      <p v-if="!rows.length" class="empty">{{ tt('أنشئ وصفة لكل منتج: المكوّنات والكميات لكل دفعة، لتُحسب التكلفة ويُصرف المخزون تلقائياً عند الإنتاج.', 'Create a recipe per product so costs and stock are handled automatically.') }}</p>
    </div>
  </div>
</template>
