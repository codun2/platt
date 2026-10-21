<script setup lang="ts">
definePageMeta({ title: 'العملاء' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const q = ref('')

async function load() { page.value = await api.get('/app/customers', { q: q.value }) }
onMounted(load)
async function remove(c: any) {
  if (!confirm(`حذف العميل ${c.name}؟`)) return
  try { await api.del(`/app/customers/${c.id}`); await load() } catch (e: any) { alert(e.message) }
}
const typeLabel = (t: string) => (t === 'company' ? 'شركة' : 'فرد')
</script>

<template>
  <div>
    <div class="breadcrumb">العملاء والفواتير › <b>العملاء</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><input v-model="q" placeholder="بحث بالاسم أو الهاتف..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
        <NuxtLink v-if="auth.can('crm.manage')" class="btn" to="/crm/customers/new"><i class="ti ti-plus" /> عميل جديد</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>الهاتف</th><th>المدينة</th><th>الفواتير</th><th>إجمالي المشتريات</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
        <tbody>
          <tr v-for="(c, i) in page?.data" :key="c.id">
            <td>{{ i + 1 }}</td>
            <td><NuxtLink :to="`/crm/customers/${c.id}`" style="color:var(--info);font-weight:700">{{ c.name }}</NuxtLink></td>
            <td><span class="badge" :class="c.customer_type === 'company' ? 'info' : 'muted'">{{ typeLabel(c.customer_type) }}</span></td>
            <td dir="ltr" style="text-align:right">{{ c.phone || '—' }}</td>
            <td>{{ c.city || '—' }}</td>
            <td>{{ c.sales_count }}</td>
            <td class="num">{{ f.money(c.sales_total) }}</td>
            <td><span class="badge" :class="c.is_active ? 'ok' : 'muted'">{{ c.is_active ? 'نشط' : 'موقوف' }}</span></td>
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
      <p v-if="page && !page.data.length" class="empty">لا يوجد عملاء.</p>
    </div>
  </div>
</template>
