<script setup lang="ts">
definePageMeta({ title: 'العروض والكوبونات', titleEn: 'Promotions & Coupons' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[] | null>(null); const state = ref('')
const canManage = computed(() => auth.can('promotions.manage'))
async function load() { rows.value = await api.get('/app/promotions', { state: state.value }) }
onMounted(load)
const { rows: pRows, pager: pInfo, go: pGo } = usePaged(() => rows.value || [], 25)
const totals = computed(() => (rows.value || []).reduce((a: any, p: any) => ({ live: a.live + (p.state === 'live' ? 1 : 0), given: a.given + +p.discount_given, uses: a.uses + p.used_count }), { live: 0, given: 0, uses: 0 }))
async function toggle(p: any) {
  try { await api.patch(`/app/promotions/${p.id}`, promoPayload({ ...promoForm(p), is_active: !p.is_active })); await load() } catch (e: any) { uiAlert(e.message) }
}
async function remove(p: any) {
  if (!(await uiConfirm(tt(`حذف العرض «${p.name}»؟`, `Delete «${p.name}»?`)))) return
  try { await api.del(`/app/promotions/${p.id}`); await load() } catch (e: any) { uiAlert(e.message) }
}
const when = (p: any) => [
  p.starts_on || p.ends_on ? `${p.starts_on || '…'} → ${p.ends_on || '…'}` : '',
  p.days?.length ? p.days.map((d: number) => weekDays()[d]).join('، ') : '',
  p.time_from ? `${p.time_from.slice(0, 5)}–${p.time_to?.slice(0, 5)}` : '',
].filter(Boolean).join(' · ') || tt('دائماً', 'Always')
</script>

<template>
  <div>
    <div class="grid grid-3">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-discount-2" /></div><div><b>{{ totals.live }}</b><small>{{ tt('عروض فعّالة الآن', 'Live now') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-receipt" /></div><div><b>{{ totals.uses }}</b><small>{{ tt('فاتورة استفادت من عرض', 'Receipts with an offer') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coin" /></div><div><b>{{ f.money(totals.given) }}</b><small>{{ tt('إجمالي الخصومات الممنوحة', 'Total discount given') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="row between" style="margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <div class="chips">
          <span class="chip" :class="{ on: !state }" @click="state = ''; load()">{{ tt('الكل', 'All') }}</span>
          <span v-for="(s, k) in promoState" :key="k" class="chip" :class="{ on: state === k }" @click="state = String(k); load()">{{ s.label }}</span>
        </div>
        <NuxtLink v-if="canManage" to="/promotions/new" class="btn"><i class="ti ti-plus" /> {{ tt('عرض جديد', 'New offer') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('العرض', 'Offer') }}</th><th>{{ tt('التفاصيل', 'Details') }}</th><th>{{ tt('المدة والأوقات', 'When') }}</th><th>{{ tt('الاستخدام', 'Used') }}</th><th>{{ tt('الخصم الممنوح', 'Given') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in pRows" :key="p.id">
            <td><NuxtLink :to="`/promotions/${p.id}`"><b>{{ p.name }}</b></NuxtLink><br /><span v-if="p.code" class="badge info" dir="ltr"><i class="ti ti-ticket" /> {{ p.code }}</span><small v-else class="muted">{{ tt('تلقائي', 'Automatic') }}</small></td>
            <td><i :class="`ti ${promoType[p.type]?.icon}`" /> {{ promoSummary(p, f) }}<br /><small class="muted">{{ p.applies_to === 'all' ? tt('كل الأصناف', 'All items') : p.applies_to === 'categories' ? tt(`${p.category_ids?.length} تصنيف`, `${p.category_ids?.length} categories`) : tt(`${p.item_ids?.length} صنف`, `${p.item_ids?.length} items`) }}</small></td>
            <td><small>{{ when(p) }}</small></td>
            <td class="num">{{ p.used_count }}<small v-if="p.usage_limit" class="muted"> / {{ p.usage_limit }}</small></td>
            <td class="num">{{ f.money(p.discount_given) }}</td>
            <td><span class="badge" :class="promoState[p.state]?.cls">{{ promoState[p.state]?.label }}</span></td>
            <td v-if="canManage" class="row" style="gap:4px"><label class="switch" :title="tt('تشغيل / إيقاف', 'On / off')"><input type="checkbox" :checked="p.is_active" @change="toggle(p)" /><span /></label><NuxtLink :to="`/promotions/${p.id}`" class="btn ghost sm icon"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(p)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="rows && !rows.length" class="empty">{{ tt('لا توجد عروض بعد — أنشئ عرضاً ليُطبَّق تلقائياً عند الكاشير.', 'No offers yet — create one and it applies automatically at the till.') }}</p>
      <div v-if="rows?.length" class="mt"><UiPager :p="pInfo" @go="pGo" /></div>
    </div>
  </div>
</template>
