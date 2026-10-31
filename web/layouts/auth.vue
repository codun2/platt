<script setup lang="ts">
/** Split-screen auth shell: animated brand panel + form area. Shared by login/register. */
const { tt, toggle, isEn } = useI18n()
const features = computed(() => [
  { icon: 'ti-calculator', t: tt('محاسبة ومخزون ونقاط بيع', 'Accounting, stock & POS') },
  { icon: 'ti-wifi-off', t: tt('الكاشير يعمل حتى دون إنترنت', 'POS keeps selling offline') },
  { icon: 'ti-qrcode', t: tt('فواتير ضريبية إلكترونية (ZATCA)', 'ZATCA e-invoices') },
  { icon: 'ti-apps', t: tt('وحدات تتفعّل حسب قطاعك', 'Modules per your sector') },
])
</script>

<template>
  <div class="auth">
    <!-- Brand panel -->
    <aside class="auth-brand">
      <div class="blob b1" /><div class="blob b2" /><div class="blob b3" />
      <div class="ab-inner">
        <a href="/" class="ab-logo" :title="tt('العودة للرئيسية', 'Back to home')">
          <span class="mark">ر</span>
          <span><b>{{ tt('ركائز', 'Rakeez') }}</b><small>{{ tt('منصّة الأعمال المتكاملة', 'All-in-one business platform') }}</small></span>
        </a>
        <div class="ab-mid">
          <h1>{{ tt('نظامٌ واحد', 'One system') }}<br /><span class="grad">{{ tt('لكل قطاع', 'for every sector') }}</span></h1>
          <p>{{ tt('أساس قوي ووحدات متخصّصة تتفعّل حسب نشاطك — من المتاجر والصيدليات إلى الطاقة الشمسية والمدارس والورش.', 'A strong core plus specialized modules that switch on for your business — from shops and pharmacies to solar, schools and workshops.') }}</p>
          <ul class="ab-feat">
            <li v-for="(f, i) in features" :key="i" :style="{ animationDelay: 0.15 + i * 0.1 + 's' }"><i :class="`ti ${f.icon}`" /> {{ f.t }}</li>
          </ul>
        </div>
        <div class="ab-foot">
          <div class="chips"><span>+20 {{ tt('قطاع', 'sectors') }}</span><span>{{ tt('قاعدة بيانات موحّدة', 'Unified database') }}</span><span>{{ tt('عربي كامل', 'Full Arabic') }}</span></div>
        </div>
      </div>
    </aside>

    <!-- Form area -->
    <main class="auth-main">
      <div class="am-top">
        <a href="/" class="back"><i class="ti ti-arrow-right rtl-flip" /> {{ tt('العودة للرئيسية', 'Back to home') }}</a>
        <button class="btn ghost sm" @click="toggle"><i class="ti ti-language" /> {{ isEn ? 'ع' : 'EN' }}</button>
      </div>
      <!-- Mobile logo (brand panel hidden on small screens) -->
      <a href="/" class="am-logo"><span class="mark">ر</span> <b>{{ tt('ركائز', 'Rakeez') }}</b></a>
      <div class="am-slot"><slot /></div>
    </main>
  </div>
</template>
