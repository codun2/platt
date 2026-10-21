<script setup lang="ts">
/**
 * Modern dropdown. Drop-in for a native <select>: keep the same
 * `v-model` and inner <option> markup, just swap the tag. A hidden native
 * <select> still holds the options (so v-for / dynamic options keep working);
 * the visible UI is fully custom and styled (rounded popup, hover, check icon).
 */
const props = defineProps<{ modelValue?: string | number | null; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [any]; change: [] }>()
defineOptions({ inheritAttrs: false })

const root = ref<HTMLElement | null>(null)
const sel = ref<HTMLSelectElement | null>(null)
const open = ref(false)
const opts = ref<{ value: any; label: string; disabled: boolean }[]>([])
let mo: MutationObserver | null = null

function refresh() {
  if (!sel.value) return
  opts.value = Array.from(sel.value.options).map((o: any) => ({
    value: '_value' in o ? o._value : o.value,
    label: (o.textContent || '').trim(),
    disabled: o.disabled,
  }))
}
const current = computed(() => opts.value.find(o => String(o.value) === String(props.modelValue ?? '')))

function pick(o: { value: any; disabled: boolean }) {
  if (o.disabled) return
  if (sel.value) sel.value.value = o.value
  emit('update:modelValue', o.value)
  emit('change')
  open.value = false
}
function toggle() { open.value = !open.value; if (open.value) refresh() }
function onDocClick(e: MouseEvent) { if (root.value && !root.value.contains(e.target as Node)) open.value = false }
function onKey(e: KeyboardEvent) { if (e.key === 'Escape') open.value = false }

onMounted(() => {
  refresh(); nextTick(refresh)
  if (sel.value) { mo = new MutationObserver(refresh); mo.observe(sel.value, { childList: true, subtree: true, attributes: true, characterData: true }) }
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => { mo?.disconnect(); document.removeEventListener('click', onDocClick); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div ref="root" class="ui-select" :class="{ open }">
    <select ref="sel" class="ui-select-native" :value="modelValue" tabindex="-1" aria-hidden="true"><slot /></select>
    <button type="button" class="ui-select-btn" v-bind="$attrs" @click.stop="toggle">
      <span :class="{ 'ui-select-ph': !current }">{{ current ? current.label : (placeholder || '—') }}</span>
      <i class="ti ti-chevron-down ui-select-caret" />
    </button>
    <div v-if="open" class="ui-select-pop">
      <button
        v-for="o in opts" :key="String(o.value)" type="button"
        class="ui-select-opt" :class="{ sel: current && String(o.value) === String(current.value), dis: o.disabled }"
        :disabled="o.disabled" @click.stop="pick(o)"
      >
        <i class="ti ti-check" :style="{ visibility: current && String(o.value) === String(current.value) ? 'visible' : 'hidden' }" />
        <span>{{ o.label }}</span>
      </button>
    </div>
  </div>
</template>
