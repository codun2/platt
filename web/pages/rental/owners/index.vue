<script setup lang="ts">
definePageMeta({ title: 'الملّاك والوسطاء', titleEn: 'Owners & Brokers' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
const rows = ref<any[]>([]); const q = ref(''); const type = ref('')
const canManage = computed(() => auth.can('rental.manage'))
async function load() { rows.value = await api.get('/app/rental/owners', { q: q.value, type: type.value }) }
onMounted(load)
async function remove(o: any) { if (!confirm(tt(`حذف ${o.name}؟`, `Delete ${o.name}?`))) return; try { await api.del(`/app/rental/owners/${o.id}`); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/rental">{{ tt('الرئيسية', 'Overview') }}</NuxtLink> › <b>{{ tt('الملّاك والوسطاء', 'Owners & brokers') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" :placeholder="tt('اسم / هاتف', 'Name / phone')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <UiSelect v-model="type" style="min-width:120px" @change="load"><option value="">{{ tt('الكل', 'All') }}</option><option value="owner">{{ tt('ملّاك', 'Owners') }}</option><option value="broker">{{ tt('وسطاء', 'Brokers') }}</option></UiSelect>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/rental/owners/new"><i class="ti ti-plus" /> {{ tt('مالك / وسيط جديد', 'New owner / broker') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>{{ tt('الاسم', 'Name') }}</th><th>{{ tt('الصفة', 'Role') }}</th><th>{{ tt('الهاتف', 'Phone') }}</th><th>{{ tt('الأصول', 'Assets') }}</th><th>{{ tt('العمولة', 'Commission') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="o in rows" :key="o.id">
            <td><NuxtLink :to="`/rental/owners/${o.id}`" style="color:var(--info);font-weight:700">{{ o.name }}</NuxtLink></td>
            <td><span class="badge" :class="o.type === 'owner' ? 'info' : 'muted'">{{ o.type === 'owner' ? tt('مالك', 'Owner') : tt('وسيط', 'Broker') }}</span></td>
            <td dir="ltr" style="text-align:right">{{ o.phone || '—' }}</td>
            <td>{{ o.assets_count }}</td><td>{{ +o.commission_rate ? +o.commission_rate + '%' : '—' }}</td>
            <td><span class="badge" :class="o.is_active ? 'ok' : 'muted'">{{ o.is_active ? tt('نشط', 'Active') : tt('موقوف', 'Inactive') }}</span></td>
            <td class="row"><NuxtLink class="btn ghost sm icon" :to="`/rental/owners/${o.id}`"><i class="ti ti-eye" /></NuxtLink><template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/rental/owners/${o.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(o)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!rows.length" class="empty">{{ tt('لا يوجد ملّاك. أضف ملّاك العقارات التي تديرها لعرض كشوف حساباتهم.', 'No owners yet. Add the owners of properties you manage to see their statements.') }}</p>
    </div>
  </div>
</template>
