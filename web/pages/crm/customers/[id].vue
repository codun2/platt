<script setup lang="ts">
definePageMeta({ title: 'ملف العميل' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = route.params.id

const detail = ref<any>(null)
const tab = ref<'profile' | 'invoices'>('profile')
const editing = ref(route.query.edit === '1')
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankCustomer())
const canManage = computed(() => auth.can('crm.manage'))

async function load() {
  detail.value = await api.get(`/app/customers/${id}`)
  Object.assign(form, customerToForm(detail.value.customer))
}
onMounted(load)

async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try {
    await api.patch(`/app/customers/${id}`, form)
    msg.value = 'تم حفظ التعديلات'; editing.value = false; await load()
  } catch (e: any) { err.value = e.message } finally { saving.value = false }
}
async function remove() {
  if (!confirm(`حذف العميل ${detail.value.customer.name}؟`)) return
  try { await api.del(`/app/customers/${id}`); navigateTo('/crm/customers') } catch (e: any) { alert(e.message) }
}
const typeLabel = (t: string) => (t === 'company' ? 'شركة / جهة' : 'فرد')
</script>

<template>
  <div v-if="detail">
    <div class="breadcrumb"><NuxtLink to="/crm/customers">العملاء</NuxtLink> › <b>{{ detail.customer.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-user" /></div><div><b style="font-size:15px">{{ typeLabel(detail.customer.customer_type) }}</b><small>{{ detail.customer.category || 'عميل' }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-file-invoice" /></div><div><b>{{ detail.sales.length }}</b><small>الفواتير</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-wallet" /></div><div><b style="font-size:15px">{{ f.money(detail.customer.credit_limit, true) }}</b><small>حد الائتمان</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b style="font-size:15px">{{ f.money(detail.balance_due, true) }}</b><small>الرصيد المستحق</small></div></div>
    </div>

    <div class="card">
      <div class="row between" style="margin-bottom:4px">
        <div class="tabs" style="margin-bottom:0;border:none">
          <button :class="{ on: tab === 'profile' }" @click="tab = 'profile'">الملف</button>
          <button :class="{ on: tab === 'invoices' }" @click="tab = 'invoices'">الفواتير ({{ detail.sales.length }})</button>
        </div>
        <div v-if="canManage && tab === 'profile'" class="row" style="gap:8px">
          <button v-if="!editing" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button>
          <button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> حذف</button>
        </div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

      <!-- Profile: edit mode -->
      <div v-if="tab === 'profile' && editing">
        <CustomerForm :form="form" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
        </div>
      </div>

      <!-- Profile: read-only -->
      <div v-else-if="tab === 'profile'" class="grid grid-3">
        <div><small class="muted">الاسم</small><br /><b>{{ detail.customer.name }}</b></div>
        <div><small class="muted">كود العميل</small><br /><b dir="ltr">{{ detail.customer.code || '—' }}</b></div>
        <div><small class="muted">الهاتف</small><br /><b dir="ltr">{{ detail.customer.phone || '—' }}</b></div>
        <div><small class="muted">هاتف إضافي</small><br /><b dir="ltr">{{ detail.customer.phone2 || '—' }}</b></div>
        <div><small class="muted">واتساب</small><br /><b dir="ltr">{{ detail.customer.whatsapp || '—' }}</b></div>
        <div><small class="muted">البريد</small><br /><b dir="ltr">{{ detail.customer.email || '—' }}</b></div>
        <div><small class="muted">رقم الهوية / المستند</small><br /><b dir="ltr">{{ detail.customer.id_number || '—' }}</b></div>
        <div><small class="muted">الرقم الضريبي</small><br /><b dir="ltr">{{ detail.customer.tax_number || '—' }}</b></div>
        <div><small class="muted">الجنسية</small><br /><b>{{ detail.customer.nationality || '—' }}</b></div>
        <div v-if="detail.customer.customer_type === 'company'"><small class="muted">الشركة / الجهة</small><br /><b>{{ detail.customer.company_name || '—' }}</b></div>
        <div v-if="detail.customer.customer_type === 'company'"><small class="muted">الشخص المسؤول</small><br /><b>{{ detail.customer.contact_person || '—' }}</b></div>
        <div><small class="muted">المدينة / المنطقة</small><br /><b>{{ [detail.customer.city, detail.customer.area].filter(Boolean).join(' - ') || '—' }}</b></div>
        <div><small class="muted">العنوان</small><br /><b>{{ detail.customer.address || '—' }}</b></div>
        <div><small class="muted">أيام السداد</small><br /><b>{{ detail.customer.payment_terms_days != null ? detail.customer.payment_terms_days + ' يوم' : '—' }}</b></div>
        <div><small class="muted">الرصيد الافتتاحي</small><br /><b dir="ltr">{{ f.money(detail.customer.opening_balance) }}</b></div>
        <div v-if="detail.customer.tags"><small class="muted">الوسوم</small><br /><b>{{ detail.customer.tags }}</b></div>
        <div style="grid-column:1/-1" v-if="detail.customer.notes"><small class="muted">ملاحظات</small><br /><b>{{ detail.customer.notes }}</b></div>
      </div>

      <!-- Invoices -->
      <div v-else-if="tab === 'invoices'">
        <table class="table">
          <thead><tr><th>الفاتورة</th><th>التاريخ</th><th>الإجمالي</th><th>المدفوع</th><th>الدفع</th><th>الحالة</th></tr></thead>
          <tbody>
            <tr v-for="s in detail.sales" :key="s.id">
              <td><NuxtLink :to="`/pos/sales?open=${s.id}`" style="color:var(--info)">{{ s.number }}</NuxtLink></td><td>{{ f.d(s.date) }}</td>
              <td class="num">{{ f.money(s.total) }}</td><td class="num">{{ f.money(s.paid) }}</td><td>{{ f.methodLabel(s.payment_method) }}</td>
              <td><span class="badge" :class="s.status === 'completed' ? 'ok' : 'bad'">{{ s.status === 'completed' ? 'مكتملة' : 'ملغاة' }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!detail.sales.length" class="empty">لا توجد فواتير.</p>
      </div>
    </div>
  </div>
</template>
