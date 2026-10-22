<script setup lang="ts">
// Placeholder for engine modules not yet built (phase 2+). The sidebar entry
// exists (driven by the DB); the screen itself arrives with its engine.
definePageMeta({ title: 'قريباً', titleEn: 'Coming soon' })
const route = useRoute()
const auth = useAuth()
const { t, isEn } = useI18n()
const mod = computed(() => {
  const all = auth.state.value.sidebar.flatMap(i => [i, ...i.children])
  return all.find(m => m.route === route.path) || all.find(m => m.route && route.path.startsWith(m.route))
})
const modName = computed(() => (isEn.value && mod.value?.name_en) ? mod.value.name_en : (mod.value?.name_ar || t('locked.title')))
</script>

<template>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i :class="`ti ${mod?.icon || 'ti-tools'}`" style="font-size:52px;color:var(--primary)" />
    <h2 class="mt">{{ modName }}</h2>
    <p class="muted">{{ t('locked.msg', { engine: mod?.engine || '—' }) }}</p>
    <NuxtLink to="/" class="btn ghost mt">{{ t('locked.back') }}</NuxtLink>
  </div>
</template>
