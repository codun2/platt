<script setup lang="ts">
/**
 * «ما هذه الصفحة؟» — a short explanation on top of a page, closable.
 * Closed state is a per-device convenience (localStorage), reopened with the ? button.
 */
const props = defineProps<{ id: string; title: string }>()
const KEY = `rakeez_help_${props.id}`
const open = ref(true)
onMounted(() => { try { open.value = localStorage.getItem(KEY) !== 'closed' } catch { /* private mode */ } })
function toggle(v: boolean) { open.value = v; try { localStorage.setItem(KEY, v ? 'open' : 'closed') } catch { /* private mode */ } }
</script>

<template>
  <div v-if="open" class="page-help">
    <div class="row between" style="align-items:flex-start;gap:10px">
      <div style="flex:1"><b><i class="ti ti-bulb" /> {{ title }}</b><div class="ph-body"><slot /></div></div>
      <button class="btn ghost sm icon" :title="tt('إخفاء', 'Hide')" @click="toggle(false)"><i class="ti ti-x" /></button>
    </div>
  </div>
  <button v-else class="btn ghost sm" style="margin-bottom:10px" @click="toggle(true)"><i class="ti ti-help-circle" /> {{ tt('ما هذه الصفحة؟', 'What is this page?') }}</button>
</template>

<style scoped>
.page-help { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-size: 13.5px; line-height: 1.9; }
.ph-body { margin-top: 4px; }
.ph-body :deep(ul) { margin: 4px 0 0; padding-inline-start: 18px; }
</style>
