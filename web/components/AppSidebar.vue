<script setup lang="ts">
// Modern icon-rail navigation: a slim rail of icons that expands to a full panel
// on hover (overlay — no layout shift), can be pinned open, and becomes a drawer
// on mobile. Rendered purely from the `sidebar` tree the API returns.
import type { SidebarItem } from '~/composables/useAuth'

const props = defineProps<{ items: SidebarItem[]; brand: string; sub?: string }>()
const route = useRoute()
const { isEn, t } = useI18n()
const q = ref('')
const open = ref<Record<string, boolean>>({})
const hovered = ref(false)
const pinned = ref(false)
const navOpen = useState('nav-open', () => false)   // mobile drawer, toggled from TopBar
const isMobile = ref(false)
const expanded = computed(() => pinned.value || hovered.value || navOpen.value)

onMounted(() => {
  try { pinned.value = localStorage.getItem('rk_nav_pinned') === '1' } catch { /* */ }
  const mq = window.matchMedia('(max-width: 860px)')
  const set = () => { isMobile.value = mq.matches }
  set(); mq.addEventListener('change', set)
})
function togglePin() { pinned.value = !pinned.value; try { localStorage.setItem('rk_nav_pinned', pinned.value ? '1' : '0') } catch { /* */ } }

const label = (i: { name_ar: string; name_en: string | null }) => (isEn.value && i.name_en) ? i.name_en : i.name_ar

const bestRoute = computed(() => {
  const p = route.path; let best: string | null = null
  const walk = (items: SidebarItem[]) => {
    for (const i of items) {
      const r = i.route
      if (r && (p === r || (r !== '/' && p.startsWith(r.endsWith('/') ? r : r + '/'))) && (!best || r.length > best.length)) best = r
      walk(i.children || [])
    }
  }
  walk(props.items); return best
})
const isActive = (r: string | null) => !!r && r === bestRoute.value
const groupActive = (i: SidebarItem) => isActive(i.route) || i.children.some(c => isActive(c.route))

const filtered = computed(() => {
  const s = q.value.trim()
  if (!s) return props.items
  return props.items
    .map(i => ({ ...i, children: i.children.filter(c => c.name_ar.includes(s) || (c.name_en || '').toLowerCase().includes(s.toLowerCase())) }))
    .filter(i => i.name_ar.includes(s) || (i.name_en || '').toLowerCase().includes(s.toLowerCase()) || i.children.length)
})

watch(() => route.path, () => {
  props.items.forEach(i => { if (i.children.some(c => isActive(c.route))) open.value[i.key] = true })
  navOpen.value = false
}, { immediate: true })

const toggle = (i: SidebarItem) => { open.value[i.key] = !open.value[i.key] }
</script>

<template>
  <div class="rail-space" :class="{ pinned }" />
  <div v-if="navOpen" class="rail-backdrop" @click="navOpen = false" />
  <aside class="rail" :class="{ expanded, pinned, drawer: isMobile }"
    @mouseenter="!isMobile && (hovered = true)" @mouseleave="hovered = false">

    <div class="rail-top">
      <NuxtLink to="/" class="rail-brand">
        <span class="mark">ر</span>
        <span class="rb-txt"><b>{{ brand }}</b><small>{{ sub || t('app.brandSub') }}</small></span>
      </NuxtLink>
      <button class="pin" :class="{ on: pinned }" :title="pinned ? 'إلغاء التثبيت' : 'تثبيت القائمة'" @click="togglePin"><i :class="`ti ${pinned ? 'ti-pinned-filled' : 'ti-pin'}`" /></button>
    </div>

    <div class="rail-search">
      <i class="ti ti-search" />
      <input v-model="q" :placeholder="t('nav.search')" />
    </div>

    <nav class="rail-menu">
      <template v-for="i in filtered" :key="i.key">
        <div v-if="i.children.length" class="grp" :class="{ open: open[i.key] || q }">
          <button class="r-item" :class="{ active: groupActive(i) }" :title="label(i)" @click="toggle(i)">
            <i :class="`ti ${i.icon || 'ti-folder'}`" class="r-ic" />
            <span class="r-lbl">{{ label(i) }}</span>
            <i class="ti ti-chevron-left chev" />
          </button>
          <div v-show="(open[i.key] || q) && expanded" class="r-sub">
            <NuxtLink v-for="c in i.children" :key="c.key" :to="c.route || '/'" class="r-item sub" :class="{ active: isActive(c.route) }">
              <i :class="`ti ${c.icon || 'ti-point'}`" class="r-ic" /><span class="r-lbl">{{ label(c) }}</span>
            </NuxtLink>
          </div>
        </div>
        <NuxtLink v-else :to="i.route || '/'" class="r-item" :class="{ active: isActive(i.route) }" :title="label(i)">
          <i :class="`ti ${i.icon || 'ti-point'}`" class="r-ic" /><span class="r-lbl">{{ label(i) }}</span>
        </NuxtLink>
      </template>
    </nav>
    <slot name="bottom" />
  </aside>
</template>
