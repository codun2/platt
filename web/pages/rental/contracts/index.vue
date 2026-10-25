<script setup lang="ts">
definePageMeta({ title: 'عقود الإيجار', titleEn: 'Contracts' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const terms = useRentalTerms(); const route = useRoute()
const d = ref<any>(null)
const filt = reactive({ q: '', status: 'active', overdue: route.query.overdue === '1', expiring: route.query.expiring === '1', page: 1 })
const canManage = computed(() => auth.can('rental.manage'))
async function load() { d.value = await api.get('/app/rental/contracts', { ...filt, overdue: filt.overdue ? 1 : '', expiring: filt.expiring ? 1 : '' }) }
onMounted(load)
const progress = (c: any) => +c.total ? Math.min(100, Math.round(+c.paid * 100 / +c.total)) : 0
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental">{{ tt('الرئيسية', 'Overview') }}</NuxtLink> › <b>{{ tt('العقود', 'Contracts') }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-file-certificate" /></div><div><b>{{ d?.stats.active ?? 0 }}</b><small>{{ tt('عقود سارية', 'Active contracts') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar-repeat" /></div><div><b>{{ f.money(d?.stats.monthly_value) }}</b><small>{{ tt('القيمة الشهرية للعقود', 'Monthly contract value') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ f.money(d?.stats.overdue) }}</b><small>{{ tt('متأخرات', 'Overdue') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-calendar-time" /></div><div><b>{{ d?.stats.expiring ?? 0 }}</b><small>{{ tt('تنتهي خلال 60 يوماً', 'Ending within 60 days') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="tt('رقم العقد / الاسم / الهاتف / العقار', 'No. / name / phone / asset')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="filt.page = 1; load()" />
          <UiSelect v-model="filt.status" style="min-width:130px" @change="filt.page = 1; load()"><option value="">{{ tt('كل الحالات', 'All statuses') }}</option><option v-for="(s, k) in contractStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <label class="switch"><input v-model="filt.overdue" type="checkbox" @change="filt.page = 1; load()" /><span /> {{ tt('عليها متأخرات', 'With arrears') }}</label>
          <label class="switch"><input v-model="filt.expiring" type="checkbox" @change="filt.page = 1; load()" /><span /> {{ tt('قريبة الانتهاء', 'Ending soon') }}</label>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/rental/contracts/new"><i class="ti ti-file-plus" /> {{ tt('عقد جديد', 'New contract') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('العقد', 'Contract') }}</th><th>{{ terms.renter }}</th><th>{{ terms.asset }}</th><th>{{ tt('الفترة', 'Period') }}</th><th>{{ terms.rent }}</th><th>{{ tt('السداد', 'Paid') }}</th><th>{{ tt('متأخر', 'Overdue') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
        <tbody>
          <tr v-for="c in d?.page.data" :key="c.id">
            <td><NuxtLink :to="`/rental/contracts/${c.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ c.number }}</NuxtLink><br /><small v-if="c.kind === 'installment'" class="muted">{{ tt('تقسيط', 'Installment') }}</small></td>
            <td>{{ c.customer?.name }}<br /><small class="muted" dir="ltr">{{ c.customer?.phone }}</small></td>
            <td>{{ c.asset?.name }}<br /><small v-if="c.asset?.parent" class="muted">{{ c.asset.parent.name }}</small></td>
            <td dir="ltr" style="text-align:right;white-space:nowrap">{{ c.start_date }}<br /><small class="muted">→ {{ c.end_date }}</small></td>
            <td class="num">{{ f.money(c.rent_amount) }}<br /><small class="muted">{{ frequencies[c.frequency] }}</small></td>
            <td style="min-width:120px"><div style="height:6px;background:var(--line);border-radius:4px;overflow:hidden"><div :style="{ width: progress(c) + '%', height: '100%', background: 'var(--ok)' }" /></div><small class="muted">{{ f.money(c.paid) }} / {{ f.money(c.total) }}</small></td>
            <td class="num"><b v-if="+c.overdue > 0" style="color:var(--primary)">{{ f.money(c.overdue) }}</b><span v-else class="muted">—</span></td>
            <td><span class="badge" :class="contractStatus[c.status]?.cls">{{ contractStatus[c.status]?.label }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !d.page.data.length" class="empty">{{ tt('لا توجد عقود مطابقة.', 'No matching contracts.') }}</p>
      <UiPager :p="d?.page" @go="(n: number) => { filt.page = n; load() }" />
    </div>
  </div>
</template>
