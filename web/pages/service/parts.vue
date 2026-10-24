<script setup lang="ts">
definePageMeta({ title: 'قائمة الأسعار', titleEn: 'Price List' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref(''); const q = ref('')
const form = reactive<any>({ name: '', kind: 'labor', price: 0, cost: 0, unit: '', is_active: true })
const canManage = computed(() => auth.can('service.manage'))
async function load() { rows.value = await api.get('/app/service/catalog') }
onMounted(load)
const list = computed(() => rows.value.filter((r: any) => !q.value || r.name.includes(q.value)))
function open(r?: any) { editing.value = r || null; Object.assign(form, r ? { name: r.name, kind: r.kind, price: +r.price, cost: +r.cost, unit: r.unit || '', is_active: !!r.is_active } : { name: '', kind: 'labor', price: 0, cost: 0, unit: '', is_active: true }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { editing.value ? await api.patch(`/app/service/catalog/${editing.value.id}`, form) : await api.post('/app/service/catalog', form); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(r: any) { if (!(await uiConfirm(tt(`حذف ${r.name}؟`, `Delete ${r.name}?`)))) return; try { await api.del(`/app/service/catalog/${r.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/service">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('قائمة الأسعار', 'Price list') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <input v-model="q" :placeholder="tt('بحث…', 'Search…')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" />
        <button v-if="canManage" class="btn" @click="open()"><i class="ti ti-plus" /> {{ tt('إضافة بند', 'Add item') }}</button>
      </div>
      <p class="muted" style="margin-top:0;font-size:13px">{{ tt('تظهر هذه البنود كأزرار سريعة عند إنشاء الطلب، فتُضاف بسعرها بضغطة واحدة.', 'These appear as quick buttons on the order form.') }}</p>
      <table class="table">
        <thead><tr><th>{{ tt('البند', 'Item') }}</th><th>{{ tt('النوع', 'Type') }}</th><th>{{ tt('السعر', 'Price') }}</th><th>{{ tt('التكلفة', 'Cost') }}</th><th>{{ tt('الوحدة', 'Unit') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in list" :key="r.id">
            <td><b>{{ r.name }}</b></td><td><span class="badge" :class="r.kind === 'part' ? 'info' : 'muted'">{{ r.kind === 'part' ? tt('قطعة / مادة', 'Part') : tt('خدمة / أجرة', 'Labor') }}</span></td>
            <td class="num">{{ f.money(r.price) }}</td><td class="num muted">{{ f.money(r.cost) }}</td><td>{{ r.unit || '—' }}</td>
            <td><span class="badge" :class="r.is_active ? 'ok' : 'muted'">{{ r.is_active ? tt('نشط', 'Active') : tt('موقوف', 'Inactive') }}</span></td>
            <td v-if="canManage" class="row"><button class="btn ghost sm icon" @click="open(r)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(r)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!list.length" class="empty">{{ tt('أضف خدماتك وأسعارها (مثلاً: غسيل ثوب، تغيير زيت، كشف عطل).', 'Add your services and prices.') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل بند', 'Edit item') : tt('بند جديد', 'New item')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('الاسم', 'Name') }}</label><input v-model="form.name" /></div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('النوع', 'Type') }}</label><UiSelect v-model="form.kind"><option value="labor">{{ tt('خدمة / أجرة', 'Labor') }}</option><option value="part">{{ tt('قطعة / مادة', 'Part') }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('الوحدة', 'Unit') }}</label><input v-model="form.unit" :placeholder="tt('قطعة، ساعة، م²…', 'piece, hour, m²…')" /></div>
        <div class="field"><label>{{ tt('سعر البيع', 'Price') }}</label><input v-model.number="form.price" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('التكلفة', 'Cost') }}</label><input v-model.number="form.cost" type="number" min="0" step="0.001" dir="ltr" /></div>
      </div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط', 'Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ', 'Save') }}</button></template>
    </UiModal>
  </div>
</template>
