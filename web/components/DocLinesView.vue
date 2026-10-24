<script setup lang="ts">
/** Read-only line table for invoices / quotes, columns per sector line style. */
const props = defineProps<{ items: any[]; mode?: LineMode; plain?: boolean }>()
const f = useFmt()
const m = computed<LineMode>(() => {
  // Lines that carry qty/discount keep their columns even in an amount-style sector.
  if (props.items.some((l: any) => +l.discount > 0 || (+l.qty !== 1 && props.mode === 'amount'))) return 'items'
  return props.mode || 'items'
})
</script>

<template>
  <table :class="plain ? '' : 'table'">
    <thead><tr><th style="width:30px">#</th><th>{{ m === 'amount' ? tt('البيان', 'Description') : tt('الوصف', 'Description') }}</th>
      <template v-if="m !== 'amount'"><th style="width:70px">{{ tt('الكمية', 'Qty') }}</th><th v-if="m === 'items'" style="width:60px">{{ tt('الوحدة', 'Unit') }}</th><th style="width:100px">{{ m === 'items' ? tt('سعر الوحدة', 'Unit price') : tt('السعر', 'Price') }}</th><th v-if="m === 'items'" style="width:80px">{{ tt('خصم', 'Disc.') }}</th></template>
      <th style="width:120px">{{ m === 'amount' ? tt('المبلغ', 'Amount') : tt('الإجمالي', 'Total') }}</th></tr></thead>
    <tbody>
      <tr v-for="(l, i) in items" :key="l.id || i">
        <td class="muted">{{ i + 1 }}</td><td>{{ l.description }}<small v-if="l.item?.sku" class="muted" dir="ltr"> ({{ l.item.sku }})</small></td>
        <template v-if="m !== 'amount'"><td class="num">{{ f.qty(l.qty) }}</td><td v-if="m === 'items'">{{ l.unit || '—' }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td v-if="m === 'items'" class="num">{{ +l.discount ? f.money(l.discount) : '' }}</td></template>
        <td class="num"><b>{{ f.money(l.total) }}</b></td>
      </tr>
    </tbody>
  </table>
</template>
