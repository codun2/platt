<script setup lang="ts">
// Dynamic sidebar — rendered purely from the `sidebar` tree the API returns
// (modules enabled for this tenant ∩ modules the user may view).
import type { SidebarItem } from '~/composables/useAuth'

const props = defineProps<{ items: SidebarItem[]; brand: string; sub?: string }>()
const route = useRoute()
const q = ref('')
const open = ref<Record<string, boolean>>({})

const isActive = (r: string | null) => !!r && (route.path === r || (r !== '/' && route.path.startsWith(r)))

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
      <div><b>{{ brand }}</b><small>{{ sub || 'ركائز — منصّة الأعمال' }}</small></div>
    </div>
    <div class="search">
      <input v-model="q" placeholder="بحث في القائمة..." />
      <i class="ti ti-search" />
    </div>
    <ul class="menu">
      <li v-for="i in filtered" :key="i.key" :class="{ open: open[i.key] || q }">
        <template v-if="i.children.length">
          <div class="item" :class="{ active: isActive(i.route) && !i.children.some(c => isActive(c.route)) }" @click="toggle(i)">
            <i :class="`ti ${i.icon || 'ti-folder'}`" /> {{ i.name_ar }} <i class="ti ti-chevron-left chev" />
          </div>
          <ul v-show="open[i.key] || q" class="sub">
            <li v-for="c in i.children" :key="c.key">
              <NuxtLink :to="c.route || '/'" class="item" :class="{ active: isActive(c.route) }">
                <i :class="`ti ${c.icon || 'ti-point'}`" /> {{ c.name_ar }}
              </NuxtLink>
            </li>
          </ul>
        </template>
        <NuxtLink v-else :to="i.route || '/'" class="item" :class="{ active: isActive(i.route) }">
          <i :class="`ti ${i.icon || 'ti-point'}`" /> {{ i.name_ar }}
        </NuxtLink>
      </li>
    </ul>
    <slot name="bottom" />
  </aside>
</template>
