<script setup lang="ts">
// Shown when the user opens a route of a module that is not enabled for the tenant.
definePageMeta({ title: 'وحدة غير مفعّلة', titleEn: 'Module not enabled' })
const route = useRoute()
const names: Record<string, () => string> = { inventory: () => tt('المخزون', 'Inventory'), pos: () => tt('نقطة البيع', 'Point of Sale'), bookings: () => tt('الحجوزات', 'Bookings'), travel: () => tt('الحج والعمرة والسفر', 'Hajj, Umrah & Travel'), rental: () => tt('التأجير والعقود', 'Rentals & contracts'), service: () => tt('الصيانة', 'Service'), projects: () => tt('المشاريع والقضايا', 'Projects & cases'), education: () => tt('المدارس', 'Schools'), manufacturing: () => tt('التصنيع', 'Manufacturing'), accounting: () => tt('المحاسبة', 'Accounting'), crm: () => tt('العملاء والفواتير', 'Customers & invoices'), hr: () => tt('الموارد البشرية', 'Human resources'), whatsapp: () => tt('أتمتة واتساب', 'WhatsApp automation'), vehicles: () => tt('معرض السيارات', 'Car showroom') }
const name = computed(() => (names[String(route.query.m)]?.() || tt('هذه الوحدة', 'This module')))
</script>

<template>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i class="ti ti-lock" style="font-size:52px;color:var(--warn)" />
    <h2 class="mt">{{ tt(`${name} غير مفعّلة لحسابكم`, `${name} is not enabled for your account`) }}</h2>
    <p class="muted">{{ tt('هذه الوحدة ليست ضمن باقتكم الحالية. تواصل مع إدارة المنصّة لتفعيلها.','This module is not part of your current plan. Contact the platform admin to enable it.') }}</p>
    <NuxtLink to="/" class="btn ghost mt">{{ tt('العودة للرئيسية','Back to home') }}</NuxtLink>
  </div>
</template>
