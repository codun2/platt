<script setup lang="ts">
definePageMeta({ title: 'العملاء' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const q = ref('')
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', phone: '', email: '', tax_number: '', address: '', notes: '', is_active: true })
const detail = ref<any>(null)

async function load() { page.value = await api.get('/app/customers', { q: q.value }) }
onMounted(load)
function openNew() { editing.value = null; Object.assign(form, { name: '', phone: '', email: '', tax_number: '', address: '', notes: '', is_active: true }); err.value = ''; modal.value = true }
function openEdit(c: any) { editing.value = c; Object.assign(form, { ...c, phone: c.phone || '', email: c.email || '', tax_number: c.tax_number || '', address: c.address || '', notes: c.notes || '' }); err.value = ''; modal.value = true }
async function save() {
  err.value = ''
  try { editing.value ? await api.patch(`/app/customers/${editing.value.id}`, form) : await api.post('/app/customers', form); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function remove(c: any) { if (!confirm(`حذف العميل ${c.name}؟`)) return; try { await api.del(`/app/customers/${c.id}`); await load() } catch (e: any) { alert(e.message) } }
async function show(c: any) { detail.value = await api.get(`/app/customers/${c.id}`) }
</script>

<template>
  <div>
    <div class="breadcrumb">العملاء والفواتير › <b>العملاء</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row"><input v-model="q" placeholder="بحث بالاسم أو الهاتف..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button></div>
        <button v-if="auth.can('crm.manage')" class="btn" @click="openNew"><i class="ti ti-plus" /> عميل جديد</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>البريد</th><th>الفواتير</th><th>إجمالي المشتريات</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
        <tbody>
          <tr v-for="(c, i) in page?.data" :key="c.id">
            <td>{{ i + 1 }}</td>
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(c)">{{ c.name }}</a></td>
            <td dir="ltr" style="text-align:right">{{ c.phone || '—' }}</td>
            <td dir="ltr" style="text-align:right">{{ c.email || '—' }}</td>
            <td>{{ c.sales_count }}</td>
            <td class="num">{{ f.money(c.sales_total) }}</td>
            <td><span class="badge" :class="c.is_active ? 'ok' : 'muted'">{{ c.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td class="row" style="gap:6px">
              <button class="btn ghost sm icon" @click="show(c)"><i class="ti ti-eye" /></button>
              <template v-if="auth.can('crm.manage')">
                <button class="btn ghost sm icon" @click="openEdit(c)"><i class="ti ti-pencil" /></button>
                <button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(c)"><i class="ti ti-trash" /></button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا يوجد عملاء.</p>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل عميل' : 'عميل جديد'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>الاسم</label><input v-model="form.name" /></div>
        <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
        <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
        <div class="field"><label>الرقم الضريبي</label><input v-model="form.tax_number" dir="ltr" /></div>
      </div>
      <div class="field"><label>العنوان</label><input v-model="form.address" /></div>
      <div class="field"><label>ملاحظات</label><textarea v-model="form.notes" rows="2" /></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>

    <UiModal v-if="detail" :title="detail.customer.name" lg @close="detail = null">
      <div class="row" style="gap:24px;margin-bottom:12px">
        <div><small class="muted">الهاتف</small><br /><b dir="ltr">{{ detail.customer.phone || '—' }}</b></div>
        <div><small class="muted">الرصيد المستحق</small><br /><b style="color:var(--primary)">{{ f.money(detail.balance_due, true) }}</b></div>
      </div>
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
    </UiModal>
  </div>
</template>
