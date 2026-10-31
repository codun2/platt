<script setup lang="ts">
// Two-tier navigation (a fresh concept): a slim icon rail of top-level SECTIONS,
// plus a contextual panel that lists only the current section's items. Sections
// with no children get no panel (full-width page). Mobile → slide-in drawer.
import type { SidebarItem } from '~/composables/useAuth'

const props = defineProps<{ items: SidebarItem[]; brand: string; sub?: string }>()
const route = useRoute(); const router = useRouter()
const { isEn, t } = useI18n()
const navOpen = useState('nav-open', () => false)
const isMobile = ref(false)
onMounted(() => { const mq = window.matchMedia('(max-width: 900px)'); const f = () => (isMobile.value = mq.matches); f(); mq.addEventListener('change', f) })

const label = (i: { name_ar: string; name_en: string | null }) => (isEn.value && i.name_en) ? i.name_en : i.name_ar
const bestRoute = computed(() => {
  const p = route.path; let best: string | null = null
  const walk = (items: SidebarItem[]) => { for (const i of items) { const r = i.route; if (r && (p === r || (r !== '/' && p.startsWith(r.endsWith('/') ? r : r + '/'))) && (!best || r.length > best.length)) best = r; walk(i.children || []) } }
  walk(props.items); return best
})
const isActive = (r: string | null) => !!r && r === bestRoute.value
// The section (top-level item) the current page belongs to.
const activeTop = computed(() => props.items.find(i => isActive(i.route) || i.children.some(c => isActive(c.route))) || null)
const topActive = (i: SidebarItem) => activeTop.value?.key === i.key
const panelItems = computed(() => activeTop.value?.children || [])
const showPanel = computed(() => !isMobile.value && panelItems.value.length > 0)

function goSection(i: SidebarItem) {
  const target = i.route || i.children.find(c => c.route)?.route
  if (target) router.push(target)
}
watch(() => route.path, () => { navOpen.value = false })

// Mobile drawer: full accordion of every section.
const open = ref<Record<string, boolean>>({})
watch(activeTop, v => { if (v) open.value[v.key] = true }, { immediate: true })
</script>

<template>
  <!-- Desktop: icon rail + contextual panel -->
  <div v-if="!isMobile" class="nav2">
    <div class="rail2">
      <NuxtLink to="/" class="r2-brand" :title="brand"><span class="mark">ر</span></NuxtLink>
      <nav class="r2-list">
        <button v-for="i in items" :key="i.key" class="r2-ic" :class="{ on: topActive(i) }" :title="label(i)" @click="goSection(i)">
          <i :class="`ti ${i.icon || 'ti-point'}`" />
          <span class="r2-dot" />
        </button>
      </nav>
      <slot name="rail-bottom" />
    </div>

    <aside v-if="showPanel" class="panel2">
      <div class="p2-head">
        <i :class="`ti ${activeTop?.icon || 'ti-folder'}`" />
        <div><b>{{ label(activeTop!) }}</b><small>{{ brand }}</small></div>
      </div>
      <div class="p2-list">
        <NuxtLink v-for="c in panelItems" :key="c.key" :to="c.route || '/'" class="p2-item" :class="{ active: isActive(c.route) }">
          <i :class="`ti ${c.icon || 'ti-point'}`" /> <span>{{ label(c) }}</span>
        </NuxtLink>
      </div>
      <slot name="bottom" />
    </aside>
  </div>

  <!-- Mobile: slide-in drawer with full accordion -->
  <template v-else>
    <div v-if="navOpen" class="rail-backdrop" @click="navOpen = false" />
    <aside class="drawer2" :class="{ open: navOpen }">
      <NuxtLink to="/" class="d2-brand"><span class="mark">ر</span><span><b>{{ brand }}</b><small>{{ sub || t('app.brandSub') }}</small></span></NuxtLink>
      <nav class="d2-menu">
        <template v-for="i in items" :key="i.key">
          <div v-if="i.children.length" class="grp" :class="{ open: open[i.key] }">
            <button class="d2-item" :class="{ active: topActive(i) }" @click="open[i.key] = !open[i.key]"><i :class="`ti ${i.icon}`" class="r-ic" /><span>{{ label(i) }}</span><i class="ti ti-chevron-left chev" /></button>
            <div v-show="open[i.key]" class="d2-sub">
              <NuxtLink v-for="c in i.children" :key="c.key" :to="c.route || '/'" class="d2-item sub" :class="{ active: isActive(c.route) }"><i :class="`ti ${c.icon || 'ti-point'}`" class="r-ic" /><span>{{ label(c) }}</span></NuxtLink>
            </div>
          </div>
          <NuxtLink v-else :to="i.route || '/'" class="d2-item" :class="{ active: isActive(i.route) }"><i :class="`ti ${i.icon}`" class="r-ic" /><span>{{ label(i) }}</span></NuxtLink>
        </template>
      </nav>
      <slot name="bottom" />
    </aside>
  </template>
</template>
