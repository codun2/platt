<script setup lang="ts">
/**
 * Phone input with a searchable country-code selector (flag + dial code).
 * Drop-in for a phone <input>: `v-model` holds the full international number
 * normalized as «+<dial><digits>» (e.g. +966501234567), with no leading zero.
 * This single normalized form also makes de-duplication reliable server-side.
 *
 *   <UiPhone v-model="form.phone" :country="defaultCountry" />
 *
 * Only one dropdown is open at a time across the app (shares nothing with UiSelect).
 */
const props = withDefaults(defineProps<{
  modelValue?: string | null
  country?: string | null   // ISO2 default (e.g. 'SA'); falls back to the tenant's / SA
  placeholder?: string
  disabled?: boolean
}>(), { modelValue: '', country: '', placeholder: '', disabled: false })
const emit = defineEmits<{ 'update:modelValue': [string] }>()
defineOptions({ inheritAttrs: false })

// GCC + Arab world first, then common. flag = emoji, dial without the «+».
const COUNTRIES = [
  { iso: 'SA', dial: '966', ar: 'السعودية', en: 'Saudi Arabia', flag: '🇸🇦' },
  { iso: 'AE', dial: '971', ar: 'الإمارات', en: 'UAE', flag: '🇦🇪' },
  { iso: 'KW', dial: '965', ar: 'الكويت', en: 'Kuwait', flag: '🇰🇼' },
  { iso: 'QA', dial: '974', ar: 'قطر', en: 'Qatar', flag: '🇶🇦' },
  { iso: 'BH', dial: '973', ar: 'البحرين', en: 'Bahrain', flag: '🇧🇭' },
  { iso: 'OM', dial: '968', ar: 'عُمان', en: 'Oman', flag: '🇴🇲' },
  { iso: 'YE', dial: '967', ar: 'اليمن', en: 'Yemen', flag: '🇾🇪' },
  { iso: 'JO', dial: '962', ar: 'الأردن', en: 'Jordan', flag: '🇯🇴' },
  { iso: 'EG', dial: '20', ar: 'مصر', en: 'Egypt', flag: '🇪🇬' },
  { iso: 'IQ', dial: '964', ar: 'العراق', en: 'Iraq', flag: '🇮🇶' },
  { iso: 'SY', dial: '963', ar: 'سوريا', en: 'Syria', flag: '🇸🇾' },
  { iso: 'LB', dial: '961', ar: 'لبنان', en: 'Lebanon', flag: '🇱🇧' },
  { iso: 'PS', dial: '970', ar: 'فلسطين', en: 'Palestine', flag: '🇵🇸' },
  { iso: 'SD', dial: '249', ar: 'السودان', en: 'Sudan', flag: '🇸🇩' },
  { iso: 'LY', dial: '218', ar: 'ليبيا', en: 'Libya', flag: '🇱🇾' },
  { iso: 'TN', dial: '216', ar: 'تونس', en: 'Tunisia', flag: '🇹🇳' },
  { iso: 'DZ', dial: '213', ar: 'الجزائر', en: 'Algeria', flag: '🇩🇿' },
  { iso: 'MA', dial: '212', ar: 'المغرب', en: 'Morocco', flag: '🇲🇦' },
  { iso: 'MR', dial: '222', ar: 'موريتانيا', en: 'Mauritania', flag: '🇲🇷' },
  { iso: 'SO', dial: '252', ar: 'الصومال', en: 'Somalia', flag: '🇸🇴' },
  { iso: 'DJ', dial: '253', ar: 'جيبوتي', en: 'Djibouti', flag: '🇩🇯' },
  { iso: 'KM', dial: '269', ar: 'جزر القمر', en: 'Comoros', flag: '🇰🇲' },
  { iso: 'TR', dial: '90', ar: 'تركيا', en: 'Turkey', flag: '🇹🇷' },
  { iso: 'IR', dial: '98', ar: 'إيران', en: 'Iran', flag: '🇮🇷' },
  { iso: 'PK', dial: '92', ar: 'باكستان', en: 'Pakistan', flag: '🇵🇰' },
  { iso: 'IN', dial: '91', ar: 'الهند', en: 'India', flag: '🇮🇳' },
  { iso: 'ID', dial: '62', ar: 'إندونيسيا', en: 'Indonesia', flag: '🇮🇩' },
  { iso: 'US', dial: '1', ar: 'الولايات المتحدة', en: 'United States', flag: '🇺🇸' },
  { iso: 'GB', dial: '44', ar: 'بريطانيا', en: 'United Kingdom', flag: '🇬🇧' },
  { iso: 'FR', dial: '33', ar: 'فرنسا', en: 'France', flag: '🇫🇷' },
  { iso: 'DE', dial: '49', ar: 'ألمانيا', en: 'Germany', flag: '🇩🇪' },
]
const byIso = (iso: string) => COUNTRIES.find(c => c.iso === (iso || '').toUpperCase())
const defCountry = computed(() => byIso(props.country || 'SA') || byIso('SA')!)

const uid = Symbol('ui-phone')
const openId = useState<symbol | null>('ui-phone-open', () => null)
const open = computed({ get: () => openId.value === uid, set: v => { openId.value = v ? uid : (openId.value === uid ? null : openId.value) } })
const root = ref<HTMLElement | null>(null)
const q = ref('')
const searchEl = ref<HTMLInputElement | null>(null)

const selIso = ref(defCountry.value.iso)
const local = ref('')

// Parse an incoming value into (country, local digits).
function parse(val: string) {
  let s = String(val || '').trim().replace(/[\s()\-]/g, '')
  if (s.startsWith('00')) s = '+' + s.slice(2)
  if (s.startsWith('+')) {
    const d = s.slice(1).replace(/\D/g, '')
    const c = [...COUNTRIES].sort((a, b) => b.dial.length - a.dial.length).find(c => d.startsWith(c.dial))
    if (c) return { iso: c.iso, local: d.slice(c.dial.length) }
    return { iso: defCountry.value.iso, local: d }
  }
  // bare / local number: drop a leading zero.
  return { iso: defCountry.value.iso, local: s.replace(/\D/g, '').replace(/^0+/, '') }
}
// Keep internal state in sync with the model (e.g. when editing a record).
watch(() => props.modelValue, (v) => {
  const p = parse(v || '')
  selIso.value = p.iso; local.value = p.local
}, { immediate: true })

const selCountry = computed(() => byIso(selIso.value) || defCountry.value)
const filtered = computed(() => {
  const s = q.value.trim().toLowerCase().replace(/^\+/, '')
  if (!s) return COUNTRIES
  return COUNTRIES.filter(c => c.ar.includes(q.value.trim()) || c.en.toLowerCase().includes(s) || c.dial.startsWith(s))
})

function emitValue() {
  const digits = local.value.replace(/\D/g, '').replace(/^0+/, '')
  emit('update:modelValue', digits ? `+${selCountry.value.dial}${digits}` : '')
}
function onLocal(e: Event) {
  local.value = (e.target as HTMLInputElement).value.replace(/[^\d]/g, '')
  emitValue()
}
function pickCountry(c: typeof COUNTRIES[number]) {
  selIso.value = c.iso
  open.value = false; q.value = ''
  emitValue()
}
function toggle() { if (props.disabled) return; open.value = !open.value; if (open.value) nextTick(() => searchEl.value?.focus()) }
function onDocClick(e: MouseEvent) { if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false }
function onKey(e: KeyboardEvent) { if (e.key === 'Escape') open.value = false }
onMounted(() => { document.addEventListener('click', onDocClick); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { document.removeEventListener('click', onDocClick); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div ref="root" class="ui-phone" :class="{ open, dis: disabled }">
    <button type="button" class="uiph-cc" :disabled="disabled" @click.stop="toggle">
      <span class="fl">{{ selCountry.flag }}</span>
      <span dir="ltr">+{{ selCountry.dial }}</span>
      <i class="ti ti-chevron-down uiph-caret" />
    </button>
    <input
      class="uiph-num" dir="ltr" type="tel" inputmode="tel" v-bind="$attrs"
      :value="local" :placeholder="placeholder || '5xxxxxxxx'" :disabled="disabled"
      @input="onLocal"
    />
    <div v-if="open" class="uiph-pop" @click.stop>
      <div class="uiph-search"><i class="ti ti-search" /><input ref="searchEl" v-model="q" :placeholder="tt('ابحث عن دولة أو مقدمة', 'Search country or code')" /></div>
      <div class="uiph-list">
        <button
          v-for="c in filtered" :key="c.iso" type="button"
          class="uiph-opt" :class="{ sel: c.iso === selIso }" @click.stop="pickCountry(c)"
        >
          <span class="fl">{{ c.flag }}</span>
          <span class="nm">{{ tt(c.ar, c.en) }}</span>
          <span class="dl" dir="ltr">+{{ c.dial }}</span>
        </button>
        <div v-if="!filtered.length" class="uiph-empty">{{ tt('لا نتائج', 'No results') }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ui-phone { position: relative; display: flex; align-items: stretch; gap: 0; border: 1px solid var(--line); border-radius: 10px; background: var(--field-bg); }
.ui-phone.open, .ui-phone:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-ring); }
.ui-phone.dis { opacity: .6; }
.uiph-cc { display: flex; align-items: center; gap: 6px; padding: 9px 11px; border: 0; border-inline-end: 1px solid var(--line); border-start-start-radius: 9px; border-end-start-radius: 9px; background: var(--bg); color: var(--ink); font: inherit; font-weight: 700; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
.uiph-cc .fl { font-size: 17px; line-height: 1; }
.uiph-caret { font-size: 15px; color: var(--muted); transition: transform .15s; }
.ui-phone.open .uiph-caret { transform: rotate(180deg); }
/* `.ui-phone` prefix keeps these ahead of the global `.field input` rule. */
.ui-phone .uiph-num { flex: 1; min-width: 0; border: 0; border-radius: 0; background: transparent; padding: 9px 12px; font: inherit; color: var(--ink); text-align: left; }
.ui-phone .uiph-num:focus { outline: none; box-shadow: none; }
.uiph-pop { position: absolute; z-index: 60; top: calc(100% + 6px); inset-inline-start: 0; width: 300px; max-width: min(300px, 86vw); background: var(--card); border: 1px solid var(--line); border-radius: 12px; box-shadow: var(--shadow-lg); padding: 8px; }
.uiph-search { display: flex; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: 10px; padding: 0 10px; margin-bottom: 6px; }
.uiph-search i { color: var(--muted); font-size: 17px; }
.ui-phone .uiph-search input { flex: 1; width: auto; border: 0; border-radius: 0; background: transparent; padding: 9px 0; font: inherit; color: var(--ink); }
.ui-phone .uiph-search input:focus { outline: none; box-shadow: none; }
.uiph-list { max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px; }
.uiph-opt { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; border: 0; background: none; border-radius: 8px; cursor: pointer; font: inherit; color: var(--ink); text-align: start; }
.uiph-opt:hover { background: var(--accent-soft); }
.uiph-opt.sel { background: var(--accent-soft); font-weight: 700; }
.uiph-opt .fl { font-size: 19px; line-height: 1; flex-shrink: 0; }
.uiph-opt .nm { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.uiph-opt .dl { color: var(--muted); font-weight: 700; }
.uiph-empty { padding: 12px; text-align: center; color: var(--muted); font-size: 13px; }
</style>
