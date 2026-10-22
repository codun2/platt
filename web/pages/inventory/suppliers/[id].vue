<script setup lang="ts">
definePageMeta({ title: 'ملف المورد' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id)
const d = ref<any>(null); const tab = ref<'profile' | 'purchases'>('profile')
const editing = ref(route.query.edit === '1'); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankSupplier())
const s = computed(() => d.value?.supplier)
const canManage = computed(() => auth.can('inventory.manage'))

async function load() {
  try { d.value = await api.get(`/app/inventory/suppliers/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, supplierToForm(d.value.supplier))
}
onMounted(load)
async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/inventory/suppliers/${id}`, supplierPayload(form)); msg.value = 'تم حفظ التعديلات'; editing.value = false; await load() }
  catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!confirm(`حذف المورد ${s.value.name}؟`)) return; try { const r = await api.del(`/app/inventory/suppliers/${id}`); if (r.deactivated) { msg.value = 'المورد له فواتير، تم تعطيله بدل حذفه.'; await load() } else navigateTo('/inventory/suppliers') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="s">
    <div class="breadcrumb"><NuxtLink to="/inventory/suppliers">الموردون</NuxtLink> › <b>{{ s.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <SupplierForm :form="form" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b>{{ s.purchases_count }}</b><small>فاتورة شراء · {{ s.items_count }} صنف مرتبط</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-shopping-cart" /></div><div><b>{{ f.money(d.stats.purchases_total) }}</b><small>إجمالي المشتريات</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(d.stats.paid_total) }}</b><small>المدفوع له</small></div></div>
        <div class="card stat"><div class="ic" :style="`background:${d.stats.balance > 0 ? 'var(--primary)' : 'var(--ok)'}`"><i class="ti ti-wallet" /></div><div><b>{{ f.money(d.stats.balance) }}</b><small>مستحق للمورد<span v-if="+s.opening_balance"> (شامل رصيد افتتاحي {{ f.money(s.opening_balance) }})</span></small></div></div>
      </div>

      <div class="card">
        <div class="row between" style="margin-bottom:4px">
          <div class="tabs" style="margin-bottom:0;border:none">
            <button :class="{ on: tab === 'profile' }" @click="tab = 'profile'">الملف</button>
            <button :class="{ on: tab === 'purchases' }" @click="tab = 'purchases'">فواتير الشراء ({{ d.purchases.length }})</button>
          </div>
          <div class="row" style="gap:8px">
            <span class="badge" :class="s.is_active ? 'ok' : 'muted'">{{ s.is_active ? 'نشط' : 'موقوف' }}</span>
            <template v-if="canManage"><NuxtLink class="btn sm" :to="`/inventory/purchases/new?supplier_id=${s.id}`"><i class="ti ti-plus" /> فاتورة شراء</NuxtLink><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button><button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></template>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--line);margin:0 0 16px">

        <div v-if="tab === 'profile'" class="grid grid-3">
          <div><small class="muted">الاسم</small><br /><b>{{ s.name }}</b><small v-if="s.code" class="muted" dir="ltr"> {{ s.code }}</small></div>
          <div><small class="muted">النوع / التصنيف</small><br /><b>{{ s.supplier_type === 'company' ? 'شركة' : 'فرد' }}<span v-if="s.category"> · {{ ({ main: 'رئيسي', secondary: 'ثانوي', services: 'خدمات', importer: 'مستورد' } as any)[s.category] || s.category }}</span></b></div>
          <div><small class="muted">الشخص المسؤول</small><br /><b>{{ s.contact_person || '—' }}</b></div>
          <div><small class="muted">الهاتف</small><br /><b dir="ltr">{{ [s.phone, s.phone2].filter(Boolean).join(' · ') || '—' }}</b></div>
          <div><small class="muted">واتساب</small><br /><b dir="ltr">{{ s.whatsapp || '—' }}</b></div>
          <div><small class="muted">البريد</small><br /><b dir="ltr">{{ s.email || '—' }}</b></div>
          <div><small class="muted">الرقم الضريبي</small><br /><b dir="ltr">{{ s.tax_number || '—' }}</b></div>
          <div><small class="muted">السجل التجاري</small><br /><b dir="ltr">{{ s.cr_number || '—' }}</b></div>
          <div><small class="muted">شروط السداد</small><br /><b>{{ s.payment_terms_days != null ? `${s.payment_terms_days} يوم` : '—' }}</b></div>
          <div><small class="muted">العنوان</small><br /><b>{{ [s.country, s.city, s.area, s.address].filter(Boolean).join('، ') || '—' }}</b></div>
          <div><small class="muted">آخر شراء</small><br /><b>{{ d.stats.last_purchase || '—' }}</b></div>
          <div v-if="s.tags"><small class="muted">الوسوم</small><br /><b>{{ s.tags }}</b></div>
          <div v-if="s.bank_details" style="grid-column:1/-1"><small class="muted">بيانات التحويل البنكي</small><br />{{ s.bank_details }}</div>
          <div v-if="s.notes" style="grid-column:1/-1;background:var(--bg);padding:10px;border-radius:10px"><small class="muted">ملاحظات</small><br />{{ s.notes }}</div>
        </div>

        <div v-else>
          <table class="table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>الاستحقاق</th><th>المستودع</th><th>بنود</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th></tr></thead>
            <tbody><tr v-for="p in d.purchases" :key="p.id"><td><NuxtLink :to="`/inventory/purchases/${p.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ p.number }}</NuxtLink></td><td>{{ f.d(p.date) }}</td><td>{{ f.d(p.due_date) }}</td><td>{{ p.warehouse?.name }}</td><td>{{ p.items_count }}</td><td class="num"><b>{{ f.money(p.total) }}</b></td><td class="num" style="color:var(--ok)">{{ f.money(p.paid) }}</td><td class="num" :style="+p.total - +p.paid > 0 && p.status !== 'cancelled' ? 'color:var(--primary);font-weight:700' : ''">{{ f.money(+p.total - +p.paid) }}</td><td><span class="badge" :class="purchaseStatus[p.status]?.cls">{{ purchaseStatus[p.status]?.label }}</span></td></tr></tbody></table>
          <p v-if="!d.purchases.length" class="empty">لا توجد فواتير شراء.</p>
        </div>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><i class="ti ti-truck-off" style="font-size:48px;color:var(--muted)" /><h2 class="mt">المورد غير موجود</h2><NuxtLink to="/inventory/suppliers" class="btn ghost mt">العودة إلى الموردين</NuxtLink></div>
</template>
