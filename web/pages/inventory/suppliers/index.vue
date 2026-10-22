<script setup lang="ts">
definePageMeta({ title: 'الموردون', titleEn: 'Suppliers' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const rows = ref<any[]>([]); const q = ref(''); const activeOnly = ref(false)
const canManage = computed(() => auth.can('inventory.manage'))
async function load() { rows.value = await api.get('/app/inventory/suppliers') }
onMounted(load)
const list = computed(() => rows.value.filter((s: any) => (!activeOnly.value || s.is_active) && (!q.value || [s.name, s.phone, s.email, s.city, s.contact_person].some(v => String(v || '').includes(q.value)))))
async function remove(s: any) { if (!confirm(tt(`حذف المورد ${s.name}؟`,`Delete supplier ${s.name}?`))) return; try { const r = await api.del(`/app/inventory/suppliers/${s.id}`); if (r.deactivated) alert(tt('المورد له فواتير، تم تعطيله بدل حذفه.','Supplier has invoices; deactivated instead.')); await load() } catch (e: any) { alert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">{{ tt('المخزون','Inventory') }}</NuxtLink> › <b>{{ tt('الموردون','Suppliers') }}</b></div>
    <div class="grid grid-3" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-truck" /></div><div><b>{{ rows.length }}</b><small>{{ tt('مورد مسجّل','suppliers') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-circle-check" /></div><div><b>{{ rows.filter((s: any) => s.is_active).length }}</b><small>{{ tt('نشط','Active') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-file-invoice" /></div><div><b>{{ rows.reduce((n: number, s: any) => n + (s.purchases_count || 0), 0) }}</b><small>{{ tt('فاتورة شراء إجمالاً','purchase invoices') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" :placeholder="tt('اسم / هاتف / مدينة / مسؤول','Name / phone / city / contact')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" />
          <label class="switch"><input v-model="activeOnly" type="checkbox" /><span /> {{ tt('النشطون فقط','Active only') }}</label>
        </div>
        <div class="row"><NuxtLink class="btn ghost" to="/inventory/purchases"><i class="ti ti-truck-delivery" /> {{ tt('المشتريات','Purchases') }}</NuxtLink><NuxtLink v-if="canManage" class="btn" to="/inventory/suppliers/new"><i class="ti ti-plus" /> {{ tt('مورد جديد','New supplier') }}</NuxtLink></div>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('المورد','Supplier') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('المسؤول','Contact') }}</th><th>{{ tt('الهاتف','Phone') }}</th><th>{{ tt('المدينة','City') }}</th><th>{{ tt('شروط السداد','Terms') }}</th><th>{{ tt('الفواتير','Invoices') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(s, i) in list" :key="s.id">
            <td class="muted">{{ i + 1 }}</td>
            <td><NuxtLink :to="`/inventory/suppliers/${s.id}`" style="color:var(--info);font-weight:700">{{ s.name }}</NuxtLink><small v-if="s.code" class="muted" dir="ltr"> {{ s.code }}</small></td>
            <td><span class="badge" :class="s.supplier_type === 'company' ? 'info' : 'muted'">{{ s.supplier_type === 'company' ? tt('شركة','Company') : tt('فرد','Individual') }}</span></td>
            <td>{{ s.contact_person || '—' }}</td>
            <td dir="ltr" style="text-align:right">{{ s.phone || '—' }}</td>
            <td>{{ s.city || '—' }}</td>
            <td>{{ s.payment_terms_days != null ? tt(`${s.payment_terms_days} يوم`,`${s.payment_terms_days}d`) : '—' }}</td>
            <td>{{ s.purchases_count || 0 }}</td>
            <td><span class="badge" :class="s.is_active ? 'ok' : 'muted'">{{ s.is_active ? tt('نشط','Active') : tt('موقوف','Inactive') }}</span></td>
            <td class="row" style="gap:6px"><NuxtLink class="btn ghost sm icon" :to="`/inventory/suppliers/${s.id}`"><i class="ti ti-eye" /></NuxtLink><template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/inventory/suppliers/${s.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(s)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!list.length" class="empty">{{ tt('لا يوجد موردون.','No suppliers.') }}</p>
    </div>
  </div>
</template>
