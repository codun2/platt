<script setup lang="ts">
definePageMeta({ title: 'الصفوف والشعب', titleEn: 'Classes' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const teachers = ref<any[]>([]); const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const blank = () => ({ name: '', grade: '', section: '', stage: 'primary', academic_year: currentAcademicYear(), teacher_id: '', room: '', capacity: 25, annual_fee: 0, is_active: true, sort: 0 })
const form = reactive<any>(blank())
const canManage = computed(() => auth.can('education.manage'))
async function load() { rows.value = await api.get('/app/education/classes') }
onMounted(async () => { await load(); teachers.value = await api.get('/app/education/teachers') })
// Name follows grade + section unless typed by hand.
watch(() => [form.grade, form.section], ([g, s]) => { if (!editing.value && g) form.name = s ? `${g} — ${s}` : g })
function open(r?: any) { editing.value = r || null; Object.assign(form, r ? { ...blank(), ...r, teacher_id: r.teacher_id || '', annual_fee: +r.annual_fee } : blank()); err.value = ''; modal.value = true }
async function save() { err.value = ''; try { const b = { ...form, teacher_id: form.teacher_id || null, capacity: form.capacity || null }; editing.value ? await api.patch(`/app/education/classes/${editing.value.id}`, b) : await api.post('/app/education/classes', b); modal.value = false; await load() } catch (e: any) { err.value = e.message } }
async function remove(r: any) { if (!(await uiConfirm(tt(`حذف ${r.name}؟`, `Delete ${r.name}?`)))) return; try { await api.del(`/app/education/classes/${r.id}`); await load() } catch (e: any) { uiAlert(e.message) } }
const total = computed(() => rows.value.reduce((n, r) => n + r.students_count, 0))
// Long lists are shown 25 per page.
const { rows: pRows, pager: pInfo, go: pGo } = usePaged(() => rows.value || [], 25)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/education">{{ tt('نظرة عامة', 'Overview') }}</NuxtLink> › <b>{{ tt('الصفوف والشعب', 'Classes') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px"><span class="muted">{{ rows.length }} {{ tt('شعبة', 'classes') }} · {{ total }} {{ tt('طالب', 'students') }}</span><button v-if="canManage" class="btn" @click="open()"><i class="ti ti-plus" /> {{ tt('شعبة جديدة', 'New class') }}</button></div>
      <table class="table">
        <thead><tr><th>{{ tt('الشعبة', 'Class') }}</th><th>{{ tt('المرحلة', 'Stage') }}</th><th>{{ tt('العام الدراسي', 'Year') }}</th><th>{{ tt('رائد الصف', 'Homeroom') }}</th><th>{{ tt('الطلاب', 'Students') }}</th><th>{{ tt('الرسوم السنوية', 'Annual fee') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in pRows" :key="r.id">
            <td><NuxtLink :to="`/education/students?class_id=${r.id}`" style="color:var(--info);font-weight:700">{{ r.name }}</NuxtLink><small v-if="r.room" class="muted"> · {{ tt('قاعة', 'room') }} {{ r.room }}</small></td>
            <td>{{ eduStages[r.stage] || '—' }}</td><td dir="ltr" style="text-align:right">{{ r.academic_year || '—' }}</td><td>{{ r.teacher?.name || '—' }}</td>
            <td><b :style="r.capacity && r.students_count >= r.capacity ? 'color:var(--primary)' : ''">{{ r.students_count }}</b><small v-if="r.capacity" class="muted"> / {{ r.capacity }}</small></td>
            <td class="num">{{ f.money(r.annual_fee) }}</td>
            <td><span class="badge" :class="r.is_active ? 'ok' : 'muted'">{{ r.is_active ? tt('نشطة', 'Active') : tt('موقوفة', 'Inactive') }}</span></td>
            <td class="row"><NuxtLink class="btn ghost sm" :to="`/education/attendance?class_id=${r.id}`"><i class="ti ti-checklist" /></NuxtLink><template v-if="canManage"><button class="btn ghost sm icon" @click="open(r)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(r)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="pInfo" @go="pGo" />
      <p v-if="!rows.length" class="empty">{{ tt('أضف صفوف وشعب مدرستك (مثلاً: الصف الأول — أ).', 'Add your classes (e.g. Grade 1 — A).') }}</p>
    </div>
    <UiModal v-if="modal" :title="editing ? tt('تعديل شعبة', 'Edit class') : tt('شعبة جديدة', 'New class')" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('الصف', 'Grade') }}</label><input v-model="form.grade" :placeholder="tt('الصف الأول', 'Grade 1')" /></div>
        <div class="field"><label>{{ tt('الشعبة', 'Section') }}</label><input v-model="form.section" placeholder="أ" /></div>
        <div class="field"><label>{{ tt('الاسم المعروض', 'Display name') }}</label><input v-model="form.name" /></div>
        <div class="field"><label>{{ tt('المرحلة', 'Stage') }}</label><UiSelect v-model="form.stage"><option v-for="(l, k) in eduStages" :key="k" :value="k">{{ l }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('العام الدراسي', 'Academic year') }}</label><input v-model="form.academic_year" dir="ltr" /></div>
        <div class="field"><label>{{ tt('رائد الصف', 'Homeroom teacher') }}</label><UiSelect v-model="form.teacher_id"><option value="">—</option><option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('القاعة', 'Room') }}</label><input v-model="form.room" /></div>
        <div class="field"><label>{{ tt('السعة', 'Capacity') }}</label><input v-model.number="form.capacity" type="number" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الرسوم السنوية', 'Annual fee') }}</label><input v-model.number="form.annual_fee" type="number" min="0" step="0.001" dir="ltr" /></div>
      </div>
      <label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشطة', 'Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ', 'Save') }}</button></template>
    </UiModal>
  </div>
</template>
