<script setup lang="ts">
definePageMeta({ title: 'العملاء', titleEn: 'Customers' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null); const q = ref('')

async function load() { page.value = await api.get('/app/customers', { q: q.value }) }
onMounted(load)
async function remove(c: any) {
  if (!confirm(tt(`حذف العميل ${c.name}؟`, `Delete customer ${c.name}?`))) return
  try { await api.del(`/app/customers/${c.id}`); await load() } catch (e: any) { alert(e.message) }
}
const typeLabel = (t: string) => (t === 'company' ? tt('شركة', 'Company') : tt('فرد', 'Individual'))
</script>

<template>
  <div>
    <div class="breadcrumb">{{ tt('العملاء والفواتير', 'Customers & Invoices') }} › <b>{{ tt('العملاء', 'Customers') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><input v-model="q" :placeholder="tt('بحث بالاسم أو الهاتف...', 'Search by name or phone...')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
        <NuxtLink v-if="auth.can('crm.manage')" class="btn" to="/crm/customers/new"><i class="ti ti-plus" /> {{ tt('عميل جديد', 'New customer') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الاسم', 'Name') }}</th><th>{{ tt('النوع', 'Type') }}</th><th>{{ tt('الهاتف', 'Phone') }}</th><th>{{ tt('المدينة', 'City') }}</th><th>{{ tt('الفواتير', 'Invoices') }}</th><th>{{ tt('إجمالي المشتريات', 'Total purchases') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th>{{ tt('الإجراءات', 'Actions') }}</th></tr></thead>
        <tbody>
          <tr v-for="(c, i) in page?.data" :key="c.id">
            <td>{{ i + 1 }}</td>
            <td><NuxtLink :to="`/crm/customers/${c.id}`" style="color:var(--info);font-weight:700">{{ c.name }}</NuxtLink></td>
            <td><span class="badge" :class="c.customer_type === 'company' ? 'info' : 'muted'">{{ typeLabel(c.customer_type) }}</span></td>
            <td dir="ltr" style="text-align:right">{{ c.phone || '—' }}</td>
            <td>{{ c.city || '—' }}</td>
            <td>{{ c.sales_count }}</td>
            <td class="num">{{ f.money(c.sales_total) }}</td>
            <td><span class="badge" :class="c.is_active ? 'ok' : 'muted'">{{ c.is_active ? tt('نشط', 'Active') : tt('موقوف', 'Inactive') }}</span></td>
            <td class="row" style="gap:6px">
              <NuxtLink class="btn ghost sm icon" :to="`/crm/customers/${c.id}`"><i class="ti ti-eye" /></NuxtLink>
              <template v-if="auth.can('crm.manage')">
                <NuxtLink class="btn ghost sm icon" :to="`/crm/customers/${c.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink>
                <button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(c)"><i class="ti ti-trash" /></button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا يوجد عملاء.', 'No customers.') }}</p>
    </div>
  </div>
</template>
