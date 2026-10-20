<script setup lang="ts">
defineProps<{ title: string; lg?: boolean }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <div class="modal-bg" @click.self="emit('close')">
    <div class="modal" :class="{ lg }">
      <header>{{ title }}<button @click="emit('close')">×</button></header>
      <div class="body"><slot /></div>
      <footer v-if="$slots.footer"><slot name="footer" /></footer>
    </div>
  </div>
</template>
