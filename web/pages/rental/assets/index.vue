<script setup lang="ts">
definePageMeta({ title: 'العقارات والوحدات', titleEn: 'Properties & Units' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const terms = useRentalTerms(); const route = useRoute()
const d = ref<any>(null); const owners = ref<any[]>([])
const filt = reactive({ q: '', status: '', type: '', owner_id: String(route.query.owner_id || '') })
const canManage = computed(() => auth.can('rental.manage'))
async function load() { d.value = await api.get('/app/rental/assets', filt) }
onMounted(async () => { await load(); owners.value = await api.get('/app/rental/owners') })
// Buildings first, each followed by its units (indented); then standalone assets.
const rows = computed(() => {
  const all = d.value?.data || []
  const kids = (id: number) => all.filter((a: any) => a.parent_id === id)
  const out: any[] = []
  for (const a of all.filter((x: any) => !x.parent_id || !all.some((p: any) => p.id === x.parent_id))) { out.push({ ...a, depth: 0 }); for (const u of kids(a.id)) out.push({ ...u, depth: 1 }) }
  return out
})
async function remove(a: any) { if (!await uiConfirm(tt(`حذف ${a.name}؟`, `Delete ${a.name}?`))) return; try { await api.del(`/app/rental/assets/${a.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental">{{ tt('الرئيسية', 'Overview') }}</NuxtLink> › <b>{{ terms.assets }}</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i :class="`ti ${terms.isVehicle ? 'ti-car' : 'ti-building-community'}`" /></div><div><b>{{ d?.stats.total ?? 0 }}</b><small>{{ tt('قابلة للتأجير', 'Leasable') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-key" /></div><div><b>{{ d?.stats.rented ?? 0 }}</b><small>{{ tt('مؤجّرة', 'Rented') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-door" /></div><div><b>{{ d?.stats.available ?? 0 }}</b><small>{{ tt('متاحة', 'Available') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-tool" /></div><div><b>{{ d?.stats.maintenance ?? 0 }}</b><small>{{ tt('في الصيانة', 'In maintenance') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" :placeholder="terms.isVehicle ? tt('اسم / لوحة / رمز', 'Name / plate / code') : tt('اسم / رمز / عنوان', 'Name / code / address')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load" />
          <UiSelect v-model="filt.status" style="min-width:130px" @change="load"><option value="">{{ tt('كل الحالات', 'All statuses') }}</option><option v-for="(s, k) in assetStatus" :key="k" :value="k">{{ s.label }}</option></UiSelect>
          <UiSelect v-if="!terms.isVehicle" v-model="filt.type" style="min-width:130px" @change="load"><option value="">{{ tt('كل الأنواع', 'All types') }}</option><option v-for="k in terms.types" :key="k" :value="k">{{ assetTypes[k] }}</option></UiSelect>
          <UiSelect v-if="owners.length" v-model="filt.owner_id" style="min-width:130px" @change="load"><option value="">{{ tt('كل الملّاك', 'All owners') }}</option><option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option></UiSelect>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/rental/assets/new"><i class="ti ti-plus" /> {{ terms.newAsset }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ terms.asset }}</th><th>{{ tt('النوع', 'Type') }}</th><th>{{ terms.isVehicle ? tt('اللوحة', 'Plate') : tt('الموقع', 'Location') }}</th><th>{{ tt('المالك', 'Owner') }}</th><th>{{ terms.renter }}</th><th>{{ terms.rent }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="a in rows" :key="a.id">
            <td :style="a.depth ? 'padding-right:34px' : ''">
              <i :class="`ti ${a.depth ? 'ti-corner-down-left' : assetTypeIcon[a.type]}`" class="muted" />
              <NuxtLink :to="`/rental/assets/${a.id}`" style="color:var(--info);font-weight:700"> {{ a.name }}</NuxtLink>
              <small v-if="a.code" class="muted" dir="ltr"> {{ a.code }}</small>
              <small v-if="a.type === 'building'" class="muted"> · {{ tt(`${a.units_count} وحدة`, `${a.units_count} units`) }}</small>
            </td>
            <td>{{ assetTypes[a.type] }}</td>
            <td>{{ terms.isVehicle ? (a.plate_no || '—') : ([a.city, a.address].filter(Boolean).join('، ') || '—') }}</td>
            <td>{{ a.owner?.name || '—' }}</td>
            <td><NuxtLink v-if="a.active_contract" :to="`/rental/contracts/${a.active_contract.id}`" style="color:var(--info)">{{ a.active_contract.customer?.name }}</NuxtLink><span v-else class="muted">—</span></td>
            <td class="num">{{ a.active_contract ? f.money(a.active_contract.rent_amount) + ' / ' + frequencies[a.active_contract.frequency] : (+a.rent_amount ? f.money(a.rent_amount) : '—') }}</td>
            <td><span v-if="a.type !== 'building'" class="badge" :class="assetStatus[a.status]?.cls">{{ assetStatus[a.status]?.label }}</span></td>
            <td class="row"><NuxtLink class="btn ghost sm icon" :to="`/rental/assets/${a.id}`"><i class="ti ti-eye" /></NuxtLink><template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/rental/assets/${a.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(a)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="d && !rows.length" class="empty">{{ terms.isVehicle ? tt('أضف سياراتك لتبدأ التأجير.', 'Add your vehicles to start renting.') : tt('أضف بناياتك ووحداتك لتبدأ التأجير.', 'Add your buildings and units to start leasing.') }}</p>
    </div>
  </div>
</template>
