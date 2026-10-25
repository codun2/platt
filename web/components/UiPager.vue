<script setup lang="ts">
/**
 * Page navigation for any list. `p` is a Laravel paginator (current_page,
 * last_page, total, from, to) — or the same shape from usePaged() for lists
 * paginated in the browser. Emits `go(page)`.
 */
const props = defineProps<{ p: any }>()
const emit = defineEmits<{ go: [number] }>()
const cur = computed(() => +props.p?.current_page || 1)
const last = computed(() => +props.p?.last_page || 1)
// 1 … 4 5 [6] 7 8 … 20
const pages = computed(() => {
  const out: (number | '…')[] = []
  const add = (n: number) => { if (!out.includes(n)) out.push(n) }
  add(1)
  for (let n = Math.max(2, cur.value - 2); n <= Math.min(last.value - 1, cur.value + 2); n++) {
    if (n > (out[out.length - 1] as number) + 1) out.push('…')
    add(n)
  }
  if (last.value > 1) { if (last.value > (out[out.length - 1] as number) + 1) out.push('…'); add(last.value) }
  return out
})
function go(n: number) { if (n >= 1 && n <= last.value && n !== cur.value) { emit('go', n); window.scrollTo({ top: 0, behavior: 'smooth' }) } }
</script>

<template>
  <div v-if="p && +p.total > 0" class="pager">
    <span class="muted">{{ tt('عرض', 'Showing') }} <b>{{ p.from ?? 1 }}–{{ p.to ?? p.total }}</b> {{ tt('من', 'of') }} <b>{{ p.total }}</b></span>
    <div v-if="last > 1" class="pager-nav">
      <button type="button" class="pg" :disabled="cur <= 1" @click="go(cur - 1)"><i class="ti ti-chevron-right" /></button>
      <template v-for="(n, i) in pages" :key="i">
        <span v-if="n === '…'" class="pg-gap">…</span>
        <button v-else type="button" class="pg" :class="{ on: n === cur }" @click="go(n)">{{ n }}</button>
      </template>
      <button type="button" class="pg" :disabled="cur >= last" @click="go(cur + 1)"><i class="ti ti-chevron-left" /></button>
    </div>
  </div>
</template>

<style scoped>
.pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 14px; font-size: 13px; }
.pager-nav { display: flex; gap: 4px; align-items: center; }
.pg { min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid var(--line); background: var(--card); border-radius: 9px; cursor: pointer; font: inherit; color: var(--ink); }
.pg:hover:not(:disabled):not(.on) { border-color: var(--primary); color: var(--primary); }
.pg.on { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 700; }
.pg:disabled { opacity: .4; cursor: default; }
.pg-gap { padding: 0 4px; color: var(--muted); }
/* English layout: flip the page arrows. The whole selector sits inside :global() and starts
   at <html>: a partial :global() compiles to a bare [dir=ltr] that mirrored every LTR field. */
:global(html[dir="ltr"] .pg .ti-chevron-right), :global(html[dir="ltr"] .pg .ti-chevron-left) { transform: scaleX(-1); }
</style>
