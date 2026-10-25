<script setup lang="ts">
definePageMeta({ title: 'الفنيون', titleEn: 'Technicians' })
const api = useApi(); const auth = useAuth(); const S = useServiceSector()
const rows = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ name: '', phone: '', specialty: '', is_active: true })
const canManage = computed(() => auth.can('service.manage'))
async function load() { rows.value = await api.get('/app/service/technicians') }
onMounted(load)
function open(r?: any) { editing.value = r || null; Object.assign(form, r ? { name: r.name, phone: r.phone || '', specialty: r.specialty || '', is_active: !!r.is_active } : { name: '', phone: '', specialty: '', is_active: true }); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { editing.value ? await api.patch(`/app/service/technicians/${editing.value.id}`, form) : await api.post('/app/service/technicians', form); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(r: any) { if (!(await uiConfirm(tt(`حذف ${r.name}؟`, `Delete ${r.name}?`)))) return; try { const x = await api.del(`/app/service/technicians/${r.id}`); if (x.deactivated) uiAlert(tt('له أوامر عمل سابقة، تم إيقافه بدل حذفه.', 'Has past orders; deactivated instead.')); await load() } catch (e: any) { uiAlert(e.message) } }
// Long lists are shown 25 per page.
const { rows: pRows, pager: pInfo, go: pGo } = usePaged(() => rows.value || [], 25)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/service">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ S.techs }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px"><p class="muted" style="margin:0">{{ tt('يُسند لكل أمر عمل فنّي مسؤول، ويظهر هنا حمل العمل الحالي وإنجاز الشهر.', 'Each order is assigned to someone; see current load and this month\'s output.') }}</p><button v-if="canManage" class="btn" @click="open()"><i class="ti ti-plus" /> {{ tt('إضافة', 'Add') }}</button></div>
      <table class="table">
        <thead><tr><th>{{ tt('الاسم', 'Name') }}</th><th>{{ tt('التخصص', 'Specialty') }}</th><th>{{ tt('الجوال', 'Mobile') }}</th><th>{{ tt('أعمال مفتوحة', 'Open jobs') }}</th><th>{{ tt('أُنجزت هذا الشهر', 'Done this month') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in pRows" :key="r.id">
            <td><NuxtLink :to="`/service/tickets?technician_id=${r.id}`" style="color:var(--info);font-weight:700">{{ r.name }}</NuxtLink></td><td>{{ r.specialty || '—' }}</td><td dir="ltr" style="text-align:right">{{ r.phone || '—' }}</td>
            <td><b>{{ r.open_orders }}</b></td><td>{{ r.done_month }}</td>
            <td><span class="badge" :class="r.is_active ? 'ok' : 'muted'">{{ r.is_active ? tt('نشط', 'Active') : tt('موقوف', 'Inactive') }}</span></td>
            <td v-if="canManage" class="row"><button class="btn ghost sm icon" @click="open(r)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(r)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="pInfo" @go="pGo" />
      <p v-if="!rows.length" class="empty">{{ tt('أضف فريق العمل لتوزيع الأعمال عليهم.', 'Add your team to assign jobs.') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل', 'Edit') : tt('إضافة', 'Add')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('الاسم', 'Name') }}</label><input v-model="form.name" /></div>
      <div class="grid grid-2"><div class="field"><label>{{ tt('الجوال', 'Mobile') }}</label><input v-model="form.phone" dir="ltr" /></div><div class="field"><label>{{ tt('التخصص', 'Specialty') }}</label><input v-model="form.specialty" /></div></div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط', 'Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ', 'Save') }}</button></template>
    </UiModal>
  </div>
</template>
