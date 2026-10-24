<script setup lang="ts">
definePageMeta({ title: 'العقارات والإيجارات', titleEn: 'Properties & Leases' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const terms = useRentalTerms()
const d = ref<any>(null)
const canManage = computed(() => auth.can('rental.manage'))
onMounted(async () => { d.value = await api.get('/app/rental/summary') })
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i :class="`ti ${terms.isVehicle ? 'ti-car' : 'ti-building-community'}`" /></div><div><b>{{ d?.stats.assets ?? '…' }}</b><small>{{ terms.assets }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-chart-donut" /></div><div><b>{{ d ? d.stats.occupancy + '%' : '…' }}</b><small>{{ tt('نسبة الإشغال', 'Occupancy') }} ({{ d?.stats.rented ?? 0 }} / {{ d?.stats.assets ?? 0 }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ f.money(d?.stats.overdue) }}</b><small>{{ tt('متأخرات مستحقة', 'Overdue') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d?.stats.collected_month) }}</b><small>{{ tt('محصّل هذا الشهر', 'Collected this month') }} · {{ tt('متبقٍ', 'left') }} {{ f.money(d?.stats.due_month) }}</small></div></div>
    </div>

    <div class="card mt">
      <div class="row between">
        <div class="card-title" style="margin:0"><i class="ti ti-bolt" /> {{ tt('إجراءات سريعة', 'Quick actions') }}</div>
        <div class="row">
          <NuxtLink v-if="canManage" class="btn" to="/rental/contracts/new"><i class="ti ti-file-plus" /> {{ tt('عقد جديد', 'New contract') }}</NuxtLink>
          <NuxtLink v-if="canManage" class="btn ghost" to="/rental/assets/new"><i class="ti ti-plus" /> {{ terms.newAsset }}</NuxtLink>
          <NuxtLink class="btn ghost" to="/rental/installments?scope=overdue"><i class="ti ti-coins" /> {{ tt('التحصيل', 'Collection') }}</NuxtLink>
        </div>
      </div>
    </div>

    <div class="grid grid-2 mt" style="align-items:start">
      <div class="card">
        <div class="card-title"><i class="ti ti-alert-triangle" style="color:var(--primary)" /> {{ tt('دفعات متأخرة', 'Overdue payments') }}</div>
        <table v-if="d?.overdue.length" class="table">
          <thead><tr><th>{{ terms.renter }}</th><th>{{ terms.asset }}</th><th>{{ tt('الاستحقاق', 'Due') }}</th><th>{{ tt('المتبقي', 'Balance') }}</th></tr></thead>
          <tbody>
            <tr v-for="i in d.overdue" :key="i.id">
              <td><NuxtLink :to="`/rental/contracts/${i.contract_id}`" style="color:var(--info);font-weight:700">{{ i.contract?.customer?.name }}</NuxtLink><br /><small class="muted" dir="ltr">{{ i.contract?.customer?.phone }}</small></td>
              <td>{{ i.contract?.asset?.name }}</td>
              <td><span dir="ltr">{{ i.due_date }}</span><br /><small style="color:var(--primary)">{{ tt(`متأخر ${daysLate(i.due_date)} يوم`, `${daysLate(i.due_date)}d late`) }}</small></td>
              <td class="num"><b style="color:var(--primary)">{{ f.money(+i.amount - +i.paid) }}</b></td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty">{{ d ? tt('لا توجد متأخرات 🎉', 'Nothing overdue 🎉') : '…' }}</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="ti ti-calendar-time" /> {{ tt('عقود تنتهي خلال 60 يوماً', 'Contracts ending within 60 days') }}</div>
        <table v-if="d?.expiring.length" class="table">
          <thead><tr><th>{{ tt('العقد', 'Contract') }}</th><th>{{ terms.renter }}</th><th>{{ terms.asset }}</th><th>{{ tt('ينتهي', 'Ends') }}</th></tr></thead>
          <tbody>
            <tr v-for="c in d.expiring" :key="c.id">
              <td><NuxtLink :to="`/rental/contracts/${c.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ c.number }}</NuxtLink></td>
              <td>{{ c.customer?.name }}</td><td>{{ c.asset?.name }}</td><td dir="ltr" style="text-align:right">{{ c.end_date }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty">{{ d ? tt('لا توجد عقود قريبة الانتهاء.', 'No contracts ending soon.') : '…' }}</p>
        <div class="row between mt" style="border-top:1px solid var(--line);padding-top:10px">
          <small class="muted">{{ tt('عقود سارية', 'Active contracts') }}: <b>{{ d?.stats.active_contracts ?? 0 }}</b></small>
          <small class="muted">{{ tt('تأمينات محتجزة', 'Deposits held') }}: <b>{{ f.money(d?.stats.deposits_held) }}</b></small>
        </div>
      </div>
    </div>
  </div>
</template>
