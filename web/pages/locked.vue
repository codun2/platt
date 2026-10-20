<script setup lang="ts">
// Shown when the user opens a route of a module that is not enabled for the tenant.
definePageMeta({ title: 'وحدة غير مفعّلة' })
const route = useRoute()
const names: Record<string, string> = { inventory: 'المخزون', pos: 'نقطة البيع', bookings: 'الحجوزات', travel: 'الحج والعمرة والسفر', rental: 'التأجير والعقود', service: 'الصيانة', projects: 'المشاريع والقضايا', education: 'المدارس', manufacturing: 'التصنيع', accounting: 'المحاسبة', crm: 'العملاء والفواتير', hr: 'الموارد البشرية', whatsapp: 'أتمتة واتساب', vehicles: 'معرض السيارات' }
const name = computed(() => names[String(route.query.m)] || 'هذه الوحدة')
</script>

<template>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i class="ti ti-lock" style="font-size:52px;color:var(--warn)" />
    <h2 class="mt">{{ name }} غير مفعّلة لحسابكم</h2>
    <p class="muted">هذه الوحدة ليست ضمن باقتكم الحالية. تواصل مع إدارة المنصّة لتفعيلها.</p>
    <NuxtLink to="/" class="btn ghost mt">العودة للرئيسية</NuxtLink>
  </div>
</template>
