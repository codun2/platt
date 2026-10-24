<script setup lang="ts">
definePageMeta({ title: 'كشف المالك', titleEn: 'Owner Statement' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute(); const terms = useRentalTerms()
const id = Number(route.params.id)
const d = ref<any>(null); const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref('')
const form = reactive<any>(blankOwner()); const range = reactive({ from: '', to: '' })
const canManage = computed(() => auth.can('rental.manage'))
async function load() { d.value = await api.get(`/app/rental/owners/${id}`, range); range.from = d.value.from; range.to = d.value.to; const o = d.value.owner; Object.assign(form, { ...blankOwner(), ...Object.fromEntries(Object.keys(blankOwner()).map(k => [k, o[k] ?? ''])), commission_rate: +o.commission_rate, is_active: !!o.is_active }) }
onMounted(load)
async function save() { err.value = ''; try { await api.patch(`/app/rental/owners/${id}`, form); editing.value = false; msg.value = tt('تم الحفظ', 'Saved'); await load() } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="d">
    <div class="breadcrumb"><NuxtLink to="/rental/owners">{{ tt('الملّاك والوسطاء', 'Owners & brokers') }}</NuxtLink> › <b>{{ d.owner.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="editing" class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <RentalOwnerForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button>
      </div>
    </div>
    <template v-else>
      <div class="card">
        <div class="row between">
          <div>
            <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0">{{ d.owner.name }}</h2><span class="badge" :class="d.owner.type === 'owner' ? 'info' : 'muted'">{{ d.owner.type === 'owner' ? tt('مالك', 'Owner') : tt('وسيط', 'Broker') }}</span></div>
            <p class="muted" style="margin:6px 0 0"><span dir="ltr">{{ d.owner.phone }}</span><template v-if="d.owner.iban"> · IBAN <span dir="ltr">{{ d.owner.iban }}</span></template> · {{ tt('العمولة', 'Commission') }} {{ +d.owner.commission_rate }}%</p>
          </div>
          <div class="row">
            <input v-model="range.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">{{ tt('إلى', 'to') }}</span><input v-model="range.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
            <button v-if="canManage" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button>
          </div>
        </div>
      </div>
      <div class="grid grid-3 mt">
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d.stats.collected) }}</b><small>{{ tt('الإيجار المحصّل في الفترة', 'Rent collected in period') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-percentage" /></div><div><b>{{ f.money(d.stats.commission) }}</b><small>{{ tt('عمولة الإدارة', 'Management fee') }} ({{ +d.owner.commission_rate }}%)</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(d.stats.net) }}</b><small>{{ tt('الصافي المستحق للمالك', 'Net due to owner') }}</small></div></div>
      </div>
      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-building-community" /> {{ terms.assets }} ({{ d.owner.assets.length }})</div>
          <table v-if="d.owner.assets.length" class="table"><tbody><tr v-for="a in d.owner.assets" :key="a.id"><td><NuxtLink :to="`/rental/assets/${a.id}`" style="color:var(--info);font-weight:700">{{ a.name }}</NuxtLink> <small class="muted">{{ assetTypes[a.type] }}</small></td><td>{{ a.active_contract?.customer?.name || '—' }}</td><td><span v-if="a.type !== 'building'" class="badge" :class="assetStatus[a.status]?.cls">{{ assetStatus[a.status]?.label }}</span></td></tr></tbody></table>
          <p v-else class="empty">{{ tt('لا توجد أصول مرتبطة بهذا المالك.', 'No assets linked to this owner.') }}</p>
        </div>
        <div class="card">
          <div class="card-title"><i class="ti ti-receipt" /> {{ tt('الإيجارات المحصّلة', 'Rent collected') }}</div>
          <table v-if="d.payments.length" class="table"><tbody><tr v-for="p in d.payments" :key="p.id"><td dir="ltr" style="text-align:right">{{ p.date }}</td><td>{{ p.contract?.asset?.name }}</td><td>{{ p.contract?.customer?.name }}</td><td class="num">{{ f.money(p.amount) }}</td></tr></tbody></table>
          <p v-else class="empty">{{ tt('لا توجد تحصيلات في هذه الفترة.', 'No collections in this period.') }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
