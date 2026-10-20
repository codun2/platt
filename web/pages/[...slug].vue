<script setup lang="ts">
// Placeholder for engine modules not yet built (phase 2+). The sidebar entry
// exists (driven by the DB); the screen itself arrives with its engine.
definePageMeta({ title: 'قريباً' })
const route = useRoute()
const auth = useAuth()
const mod = computed(() => {
  const all = auth.state.value.sidebar.flatMap(i => [i, ...i.children])
  return all.find(m => m.route === route.path) || all.find(m => m.route && route.path.startsWith(m.route))
})
</script>

<template>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i :class="`ti ${mod?.icon || 'ti-tools'}`" style="font-size:52px;color:var(--primary)" />
    <h2 class="mt">{{ mod?.name_ar || 'هذه الصفحة' }}</h2>
    <p class="muted">هذه الوحدة مفعّلة لحسابك وستتوفر شاشاتها في المرحلة القادمة (محرّك <b>{{ mod?.engine || '—' }}</b>).</p>
    <NuxtLink to="/" class="btn ghost mt">العودة للرئيسية</NuxtLink>
  </div>
</template>
