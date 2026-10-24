<script setup lang="ts">
// Dynamic sidebar — rendered purely from the `sidebar` tree the API returns
// (modules enabled for this tenant ∩ modules the user may view).
import type { SidebarItem } from '~/composables/useAuth'

const props = defineProps<{ items: SidebarItem[]; brand: string; sub?: string }>()
const route = useRoute()
const { isEn, t } = useI18n()
const q = ref('')
const open = ref<Record<string, boolean>>({})
// Prefer name_en in English (falls back to Arabic when a module has no English name).
const label = (i: { name_ar: string; name_en: string | null }) => (isEn.value && i.name_en) ? i.name_en : i.name_ar

// Exactly one menu route is active: the most specific one matching the current
// path (so /pos/sales highlights "فواتير المبيعات", not also "شاشة البيع" at /pos).
const bestRoute = computed(() => {
  const p = route.path
  let best: string | null = null
  const walk = (items: SidebarItem[]) => {
    for (const i of items) {
      const r = i.route
      if (r && (p === r || (r !== '/' && p.startsWith(r.endsWith('/') ? r : r + '/'))) && (!best || r.length > best.length)) best = r
      walk(i.children || [])
    }
  }
  walk(props.items)
  return best
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
}, { immediate: true })

const toggle = (i: SidebarItem) => { open.value[i.key] = !open.value[i.key] }
</script>

<template>
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">ر</div>
      <div><b>{{ brand }}</b><small>{{ sub || t('app.brandSub') }}</small></div>
    </div>
    <div class="search">
      <input v-model="q" :placeholder="t('nav.search')" />
      <i class="ti ti-search" />
    </div>
    <ul class="menu">
      <li v-for="i in filtered" :key="i.key" :class="{ open: open[i.key] || q }">
        <template v-if="i.children.length">
          <div class="item" :class="{ active: isActive(i.route) && !i.children.some(c => isActive(c.route)) }" @click="toggle(i)">
            <i :class="`ti ${i.icon || 'ti-folder'}`" /> {{ label(i) }} <i class="ti ti-chevron-left chev" />
          </div>
          <ul v-show="open[i.key] || q" class="sub">
            <li v-for="c in i.children" :key="c.key">
              <NuxtLink :to="c.route || '/'" class="item" :class="{ active: isActive(c.route) }">
                <i :class="`ti ${c.icon || 'ti-point'}`" /> {{ label(c) }}
              </NuxtLink>
            </li>
          </ul>
        </template>
        <NuxtLink v-else :to="i.route || '/'" class="item" :class="{ active: isActive(i.route) }">
          <i :class="`ti ${i.icon || 'ti-point'}`" /> {{ label(i) }}
        </NuxtLink>
      </li>
    </ul>
    <slot name="bottom" />
  </aside>
</template>
