<script setup lang="ts">
/** Renders the ZATCA e-invoice QR from its Base64 TLV payload (offline-safe, SVG). */
import qrcode from 'qrcode-generator'
const props = defineProps<{ value?: string | null; size?: number }>()
const svg = computed(() => {
  if (!props.value) return ''
  try { const qr = qrcode(0, 'M'); qr.addData(props.value); qr.make(); return qr.createSvgTag({ cellSize: 2, margin: 0, scalable: true }) } catch { return '' }
})
</script>

<template>
  <div v-if="svg" class="zatca-qr" :style="{ width: (size || 90) + 'px', height: (size || 90) + 'px' }" v-html="svg" />
</template>

<style scoped>
.zatca-qr :deep(svg) { width: 100%; height: 100%; display: block; }
</style>
