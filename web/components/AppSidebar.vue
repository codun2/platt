<script setup lang="ts">
// Classic full sidebar: brand + search + grouped menu with text labels (always
// visible on desktop). On mobile it slides in as a drawer (toggled from TopBar).
import type { SidebarItem } from '~/composables/useAuth'

const props = defineProps<{ items: SidebarItem[]; brand: string; sub?: string }>()
const route = useRoute()
const { isEn, t } = useI18n()
const q = ref('')
const open = ref<Record<string, boolean>>({})
const navOpen = useState('nav-open', () => false)

const label = (i: { name_ar: string; name_en: string | null }) => (isEn.value && i.name_en) ? i.name_en : i.name_ar

const bestRoute = computed(() => {
  const p = route.path; let best: string | null = null
  const walk = (items: SidebarItem[]) => { for (const i of items) { const r = i.route; if (r && (p === r || (r !== '/' && p.startsWith(r.endsWith('/') ? r : r + '/'))) && (!best || r.length > best.length)) best = r; walk(i.children || []) } }
  walk(props.items); return best
})
const isActive = (r: string | null) => !!r && r === bestRoute.value

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
  <div v-if="navOpen" class="rail-backdrop" @click="navOpen = false" />
  <aside class="sidebar" :class="{ open: navOpen }">
    <NuxtLink to="/" class="brand">
      <div class="logo">ر</div>
      <div><b>{{ brand }}</b><small>{{ sub || t('app.brandSub') }}</small></div>
    </NuxtLink>
    <div class="search">
      <i class="ti ti-search" />
      <input v-model="q" :placeholder="t('nav.search')" />
    </div>
    <ul class="menu">
      <li v-for="i in filtered" :key="i.key" :class="{ open: open[i.key] || q }">
        <template v-if="i.children.length">
          <div class="item" :class="{ active: isActive(i.route) && !i.children.some(c => isActive(c.route)) }" @click="toggle(i)">
            <i :class="`ti ${i.icon || 'ti-folder'}`" /> <span>{{ label(i) }}</span> <i class="ti ti-chevron-left chev" />
          </div>
          <ul v-show="open[i.key] || q" class="sub">
            <li v-for="c in i.children" :key="c.key">
              <NuxtLink :to="c.route || '/'" class="item" :class="{ active: isActive(c.route) }">
                <i :class="`ti ${c.icon || 'ti-point'}`" /> <span>{{ label(c) }}</span>
              </NuxtLink>
            </li>
          </ul>
        </template>
        <NuxtLink v-else :to="i.route || '/'" class="item" :class="{ active: isActive(i.route) }">
          <i :class="`ti ${i.icon || 'ti-point'}`" /> <span>{{ label(i) }}</span>
        </NuxtLink>
      </li>
    </ul>
    <slot name="bottom" />
  </aside>
</template>
