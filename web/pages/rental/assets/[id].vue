<script setup lang="ts">
definePageMeta({ title: 'ملف العقار', titleEn: 'Property' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute(); const terms = useRentalTerms()
const id = Number(route.params.id)
const a = ref<any>(null); const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref('')
const owners = ref<any[]>([]); const buildings = ref<any[]>([]); const form = reactive<any>(blankAsset())
const canManage = computed(() => auth.can('rental.manage'))
async function load() { a.value = await api.get(`/app/rental/assets/${id}`); Object.assign(form, assetToForm(a.value)) }
onMounted(async () => { await load(); owners.value = await api.get('/app/rental/owners'); buildings.value = (await api.get('/app/rental/assets', { type: 'building' })).data })
async function save() { err.value = ''; try { await api.patch(`/app/rental/assets/${id}`, assetPayload(form)); editing.value = false; msg.value = tt('تم حفظ التعديلات', 'Changes saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!confirm(tt(`حذف ${a.value.name}؟`, `Delete ${a.value.name}?`))) return; try { await api.del(`/app/rental/assets/${id}`); navigateTo('/rental/assets') } catch (e: any) { alert(e.message) } }
const active = computed(() => a.value?.contracts?.find((c: any) => c.status === 'active'))
const collected = computed(() => (a.value?.contracts || []).reduce((n: number, c: any) => n + +c.paid, 0))
const spec = computed(() => {
  const x = a.value; if (!x) return []
  const rows: [string, any][] = x.type === 'vehicle'
    ? [[tt('رقم اللوحة', 'Plate'), x.plate_no], [tt('الشركة / الطراز', 'Make / model'), [x.make, x.model].filter(Boolean).join(' ')], [tt('سنة الصنع', 'Year'), x.year], [tt('اللون', 'Color'), x.color], ['VIN', x.vin], [tt('العدّاد', 'Odometer'), x.odometer ? `${x.odometer} km` : null]]
    : [[tt('المدينة', 'City'), x.city], [tt('العنوان', 'Address'), x.address], [tt('المساحة', 'Area'), x.area ? `${+x.area} ${tt('م²', 'm²')}` : null], [tt('الغرف / الحمّامات', 'Rooms / baths'), x.rooms != null ? `${x.rooms} / ${x.bathrooms ?? '—'}` : null], [tt('الطابق', 'Floor'), x.floor], [tt('مفروش', 'Furnished'), x.type === 'building' ? null : (x.furnished ? tt('نعم', 'Yes') : tt('لا', 'No'))], [tt('عدّاد الكهرباء', 'Electricity meter'), x.electricity_meter], [tt('عدّاد المياه', 'Water meter'), x.water_meter]]
  return rows.filter(r => r[1] !== null && r[1] !== '' && r[1] !== undefined)
})
</script>

<template>
  <div v-if="a">
    <div class="breadcrumb"><NuxtLink to="/rental/assets">{{ terms.assets }}</NuxtLink><template v-if="a.parent"> › <NuxtLink :to="`/rental/assets/${a.parent.id}`">{{ a.parent.name }}</NuxtLink></template> › <b>{{ a.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>

    <div v-if="editing" class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <RentalAssetForm :form="form" :owners="owners" :buildings="buildings" :editing-id="id" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <button class="btn ghost" @click="editing = false; load()">{{ tt('إلغاء', 'Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="card">
        <div class="row between">
          <div>
            <div class="row" style="gap:10px;align-items:center"><i :class="`ti ${assetTypeIcon[a.type]}`" style="font-size:26px;color:var(--primary)" /><h2 style="margin:0">{{ a.name }}</h2><span v-if="a.type !== 'building'" class="badge" :class="assetStatus[a.status]?.cls">{{ assetStatus[a.status]?.label }}</span></div>
            <p class="muted" style="margin:6px 0 0">{{ assetTypes[a.type] }}<span v-if="a.code" dir="ltr"> · {{ a.code }}</span><span v-if="a.owner"> · {{ tt('المالك', 'Owner') }}: <NuxtLink :to="`/rental/owners/${a.owner.id}`" style="color:var(--info)">{{ a.owner.name }}</NuxtLink></span></p>
          </div>
          <div v-if="canManage" class="row" style="gap:8px">
            <NuxtLink v-if="a.type === 'building'" class="btn sm" :to="`/rental/assets/new?parent_id=${a.id}`"><i class="ti ti-plus" /> {{ tt('إضافة وحدة', 'Add unit') }}</NuxtLink>
            <NuxtLink v-else-if="!active && a.status !== 'inactive'" class="btn sm" :to="`/rental/contracts/new?asset_id=${a.id}`"><i class="ti ti-file-plus" /> {{ tt('عقد جديد', 'New contract') }}</NuxtLink>
            <button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button>
            <button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button>
          </div>
        </div>
      </div>

      <div v-if="a.type !== 'building'" class="grid grid-3 mt">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-user" /></div><div><b style="font-size:16px">{{ active?.customer?.name || '—' }}</b><small>{{ active ? tt(`${terms.renter} حتى ${active.end_date}`, `${terms.renter} until ${active.end_date}`) : tt('لا يوجد عقد ساري', 'No active contract') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ active ? f.money(active.rent_amount) : f.money(a.rent_amount) }}</b><small>{{ terms.rent }} {{ frequencies[active?.frequency || a.rent_frequency] }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-coins" /></div><div><b>{{ f.money(collected) }}</b><small>{{ tt('إجمالي المحصّل من كل العقود', 'Collected across all contracts') }}</small></div></div>
      </div>

      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> {{ tt('المواصفات', 'Details') }}</div>
          <div class="grid grid-2">
            <div v-for="[k, v] in spec" :key="k"><small class="muted">{{ k }}</small><br /><b>{{ v }}</b></div>
          </div>
          <p v-if="!spec.length" class="muted">{{ tt('لا توجد مواصفات مسجّلة.', 'No details recorded.') }}</p>
          <p v-if="a.description" class="muted mt">{{ a.description }}</p>
        </div>

        <div v-if="a.type === 'building'" class="card">
          <div class="card-title"><i class="ti ti-building-community" /> {{ tt('الوحدات', 'Units') }} ({{ a.units.length }})</div>
          <table v-if="a.units.length" class="table">
            <thead><tr><th>{{ tt('الوحدة', 'Unit') }}</th><th>{{ terms.renter }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
            <tbody><tr v-for="u in a.units" :key="u.id"><td><NuxtLink :to="`/rental/assets/${u.id}`" style="color:var(--info);font-weight:700">{{ u.name }}</NuxtLink> <small class="muted">{{ assetTypes[u.type] }}</small></td><td>{{ u.active_contract?.customer?.name || '—' }}</td><td><span class="badge" :class="assetStatus[u.status]?.cls">{{ assetStatus[u.status]?.label }}</span></td></tr></tbody>
          </table>
          <p v-else class="empty">{{ tt('لا توجد وحدات بعد. أضف شقق ومحلات هذه البناية.', 'No units yet. Add the apartments and shops of this building.') }}</p>
        </div>

        <div v-else class="card">
          <div class="card-title"><i class="ti ti-history" /> {{ tt('سجل العقود', 'Contract history') }}</div>
          <table v-if="a.contracts.length" class="table">
            <thead><tr><th>{{ tt('العقد', 'Contract') }}</th><th>{{ terms.renter }}</th><th>{{ tt('الفترة', 'Period') }}</th><th>{{ tt('الحالة', 'Status') }}</th></tr></thead>
            <tbody><tr v-for="c in a.contracts" :key="c.id"><td><NuxtLink :to="`/rental/contracts/${c.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ c.number }}</NuxtLink></td><td>{{ c.customer?.name }}</td><td dir="ltr" style="text-align:right">{{ c.start_date }} → {{ c.end_date }}</td><td><span class="badge" :class="contractStatus[c.status]?.cls">{{ contractStatus[c.status]?.label }}</span></td></tr></tbody>
          </table>
          <p v-else class="empty">{{ tt('لم يُؤجَّر بعد.', 'Not leased yet.') }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
