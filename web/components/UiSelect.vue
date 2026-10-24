<script setup lang="ts">
/**
 * Modern dropdown. Drop-in for a native <select>: keep the same
 * `v-model` and inner <option> markup, just swap the tag. A hidden native
 * <select> still holds the options (so v-for / dynamic options keep working);
 * the visible UI is fully custom and styled (rounded popup, hover, check icon).
 *
 * Adding a missing choice (shown at the bottom of the list, prominently when
 * the list is empty):
 *   - quickAdd: (name) => Promise<id>  — inline input; the new id is selected.
 *     For simple lookups (units, categories).
 *   - addTo="/inventory/suppliers/new" + addKey="supplier" — opens that page.
 *     The current form is kept as a draft (useDraft) and, after saving there,
 *     the user returns here with the new record selected.
 * Only one dropdown is open at a time across the app.
 */
const props = defineProps<{
  modelValue?: string | number | null; placeholder?: string
  addLabel?: string; addTo?: string; addKey?: string; quickAdd?: (name: string) => Promise<any>
}>()
const emit = defineEmits<{ 'update:modelValue': [any]; change: [] }>()
defineOptions({ inheritAttrs: false })

const uid = Symbol('ui-select')
const openId = useState<symbol | null>('ui-select-open', () => null)
const open = computed({ get: () => openId.value === uid, set: v => { openId.value = v ? uid : (openId.value === uid ? null : openId.value) } })

const root = ref<HTMLElement | null>(null)
const sel = ref<HTMLSelectElement | null>(null)
const opts = ref<{ value: any; label: string; disabled: boolean }[]>([])
const adding = ref(false); const newName = ref(''); const addErr = ref(''); const addBusy = ref(false)
const addInput = ref<HTMLInputElement | null>(null)
let mo: MutationObserver | null = null

function refresh() {
  if (!sel.value) return
  const next = Array.from(sel.value.options).map((o: any) => ({
    value: '_value' in o ? o._value : o.value,
    label: (o.textContent || '').trim(),
    disabled: o.disabled,
  }))
  // Equality guard: only reassign when the option set actually changed.
  // Without this, a MutationObserver → reactive write → re-render → mutation
  // cycle can loop forever and freeze the tab.
  const sig = (a: any[]) => a.map(o => `${o.value}\u0001${o.label}\u0001${o.disabled ? 1 : 0}`).join('\u0002')
  if (sig(next) !== sig(opts.value)) opts.value = next
}
const current = computed(() => opts.value.find(o => String(o.value) === String(props.modelValue ?? '')))
/** Real choices (the "—" / empty-value rows don't count). */
const realCount = computed(() => opts.value.filter(o => o.value !== '' && o.value !== null && o.value !== undefined).length)
const canAdd = computed(() => !!(props.quickAdd || props.addTo))
const addText = computed(() => props.addLabel || tt('إضافة جديد', 'Add new'))

function select(value: any) {
  if (sel.value) sel.value.value = value
  emit('update:modelValue', value)
  emit('change')
}
function pick(o: { value: any; disabled: boolean }) {
  if (o.disabled) return
  select(o.value)
  open.value = false
}
function toggle() { open.value = !open.value; if (open.value) { refresh(); adding.value = false; addErr.value = '' } }
function onDocClick(e: MouseEvent) { if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false }
function onKey(e: KeyboardEvent) { if (e.key === 'Escape') open.value = false }

/** Select an id as soon as the parent's options include it. */
function selectWhenAvailable(id: any, tries = 20) {
  refresh()
  if (opts.value.some(o => String(o.value) === String(id))) { select(opts.value.find(o => String(o.value) === String(id))!.value); return }
  if (tries > 0) setTimeout(() => selectWhenAvailable(id, tries - 1), 150)
}

async function startAdd() {
  if (props.quickAdd) { adding.value = true; addErr.value = ''; newName.value = ''; nextTick(() => addInput.value?.focus()); return }
  if (props.addTo) {
    const route = useRoute()
    window.dispatchEvent(new CustomEvent('rakeez:leave-for-add'))   // useDraft saves the form
    const back = route.fullPath.replace(/([?&])(created|for)=[^&]*/g, '$1').replace(/[?&]+$/, '')
    const sep = props.addTo.includes('?') ? '&' : '?'
    open.value = false
    navigateTo(`${props.addTo}${sep}return=${encodeURIComponent(back)}&for=${encodeURIComponent(props.addKey || '')}`)
  }
}
async function saveQuick() {
  const name = newName.value.trim()
  if (!name || !props.quickAdd) return
  addBusy.value = true; addErr.value = ''
  try {
    const id = await props.quickAdd(name)
    adding.value = false; open.value = false
    if (id !== undefined && id !== null) selectWhenAvailable(id)
  } catch (e: any) { addErr.value = e.message } finally { addBusy.value = false }
}

onMounted(() => {
  refresh(); nextTick(refresh)
  if (sel.value) { mo = new MutationObserver(refresh); mo.observe(sel.value, { childList: true, subtree: true, characterData: true }) }
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onKey)
  // Back from an add page: select the record that was just created for us.
  const q = useRoute().query
  if (props.addKey && q.for === props.addKey && q.created) selectWhenAvailable(String(q.created), 40)
})
onBeforeUnmount(() => { if (open.value) open.value = false; mo?.disconnect(); document.removeEventListener('click', onDocClick); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div ref="root" class="ui-select" :class="{ open }">
    <select ref="sel" class="ui-select-native" :value="modelValue" tabindex="-1" aria-hidden="true"><slot /></select>
    <button type="button" class="ui-select-btn" v-bind="$attrs" @click.stop="toggle">
      <span :class="{ 'ui-select-ph': !current }">{{ current ? current.label : (placeholder || '—') }}</span>
      <i class="ti ti-chevron-down ui-select-caret" />
    </button>
    <div v-if="open" class="ui-select-pop" @click.stop>
      <div v-if="!realCount" class="ui-select-empty">{{ tt('لا توجد خيارات بعد', 'No options yet') }}</div>
      <template v-if="realCount">
        <button
          v-for="o in opts" :key="String(o.value)" type="button"
          class="ui-select-opt" :class="{ sel: current && String(o.value) === String(current.value), dis: o.disabled }"
          :disabled="o.disabled" @click.stop="pick(o)"
        >
          <i class="ti ti-check" :style="{ visibility: current && String(o.value) === String(current.value) ? 'visible' : 'hidden' }" />
          <span>{{ o.label }}</span>
        </button>
      </template>
      <template v-if="canAdd">
        <div v-if="adding" class="ui-select-add-form">
          <input ref="addInput" v-model="newName" :placeholder="tt('الاسم', 'Name')" @keydown.enter.prevent="saveQuick" @keydown.esc.stop="adding = false" />
          <button type="button" class="btn sm" :disabled="addBusy || !newName.trim()" @click="saveQuick"><i class="ti ti-check" /></button>
          <small v-if="addErr" class="ui-select-add-err">{{ addErr }}</small>
        </div>
        <button v-else type="button" class="ui-select-add" :class="{ prominent: !realCount }" @click.stop="startAdd">
          <i class="ti ti-plus" /><span>{{ addText }}</span><i v-if="addTo && !quickAdd" class="ti ti-external-link" style="margin-inline-start:auto;opacity:.6" />
        </button>
      </template>
    </div>
  </div>
</template>
