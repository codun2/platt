<script setup lang="ts">
/**
 * Add / edit a task or hearing, or close one (done / postponed with outcome,
 * optionally scheduling the next hearing). Emits `saved` after any change.
 */
const props = defineProps<{ mode: 'edit' | 'complete'; task?: any; projectId?: number | null; projects?: any[]; people: any[]; defaultKind?: 'task' | 'hearing' }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const api = useApi()
const err = ref(''); const busy = ref(false)
const t = props.task
const form = reactive<any>({
  project_id: t?.project_id || props.projectId || '', kind: t?.kind || props.defaultKind || 'task', title: t?.title || '',
  due_at: t?.due_at ? t.due_at.replace(' ', 'T') : localDT(new Date(Date.now() + 86400000)), location: t?.location || '',
  assigned_to: t?.assigned_to || '', priority: t?.priority || 'normal', status: t?.status || 'todo', notes: t?.notes || '',
})
const done = reactive({ status: 'done', outcome: t?.outcome || '', next: false, next_at: '', next_location: t?.location || '' })
const isHearing = computed(() => (t?.kind || form.kind) === 'hearing')
async function save() {
  err.value = ''; busy.value = true
  try {
    if (props.mode === 'complete') {
      await api.post(`/app/projects/tasks/${t.id}/complete`, { status: done.status, outcome: done.outcome || null, next_at: done.next && done.next_at ? done.next_at.replace('T', ' ') : null, next_location: done.next_location || null })
    } else {
      const body = { ...form, project_id: form.project_id || null, assigned_to: form.assigned_to || null, due_at: form.due_at ? form.due_at.replace('T', ' ') : null }
      t?.id ? await api.patch(`/app/projects/tasks/${t.id}`, body) : await api.post('/app/projects/tasks', body)
    }
    emit('saved'); emit('close')
  } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
const title = computed(() => props.mode === 'complete'
  ? (isHearing.value ? tt('نتيجة الجلسة', 'Hearing outcome') : tt('إغلاق المهمة', 'Close task'))
  : (t?.id ? tt('تعديل', 'Edit') : (form.kind === 'hearing' ? tt('جلسة جديدة', 'New hearing') : tt('مهمة جديدة', 'New task'))))
</script>

<template>
  <UiModal :title="title" @close="emit('close')">
    <div v-if="err" class="alert bad">{{ err }}</div>
    <template v-if="mode === 'complete'">
      <p class="muted" style="margin-top:0"><b>{{ t.title }}</b> · {{ fmtWhen(t.due_at) }}</p>
      <div class="chips" style="margin-bottom:10px">
        <span class="chip" :class="{ on: done.status === 'done' }" @click="done.status = 'done'">{{ isHearing ? tt('انعقدت', 'Held') : tt('منجزة', 'Done') }}</span>
        <span class="chip" :class="{ on: done.status === 'postponed' }" @click="done.status = 'postponed'">{{ tt('أُجّلت', 'Postponed') }}</span>
        <span class="chip" :class="{ on: done.status === 'cancelled' }" @click="done.status = 'cancelled'">{{ tt('أُلغيت', 'Cancelled') }}</span>
      </div>
      <div class="field"><label>{{ isHearing ? tt('ما جرى في الجلسة / القرار', 'What happened / decision') : tt('ملاحظة الإنجاز', 'Outcome note') }}</label><textarea v-model="done.outcome" rows="3" /></div>
      <label class="switch"><input v-model="done.next" type="checkbox" /><span /> {{ isHearing ? tt('تحديد موعد الجلسة القادمة', 'Schedule next hearing') : tt('إنشاء مهمة متابعة', 'Create follow-up') }}</label>
      <div v-if="done.next" class="grid grid-2 mt">
        <div class="field"><label>{{ tt('الموعد', 'When') }}</label><input v-model="done.next_at" type="datetime-local" dir="ltr" /></div>
        <div class="field"><label>{{ tt('المكان / القاعة', 'Place / hall') }}</label><input v-model="done.next_location" /></div>
      </div>
    </template>
    <template v-else>
      <div class="grid grid-2">
        <div v-if="!projectId" class="field" style="grid-column:span 2"><label>{{ tt('الملف', 'File') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.project_id" :placeholder="tt('اختر', 'Select')"><option value="">—</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.number }} — {{ p.title }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('النوع', 'Type') }}</label><UiSelect v-model="form.kind"><option value="hearing">{{ tt('جلسة', 'Hearing') }}</option><option value="task">{{ tt('مهمة', 'Task') }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('العنوان', 'Title') }} <span style="color:var(--primary)">*</span></label><input v-model="form.title" :placeholder="form.kind === 'hearing' ? tt('جلسة مرافعة', 'Pleading hearing') : tt('إعداد مذكرة…', 'Prepare memo…')" /></div>
        <div class="field"><label>{{ tt('الموعد', 'When') }}</label><input v-model="form.due_at" type="datetime-local" dir="ltr" /></div>
        <div class="field"><label>{{ form.kind === 'hearing' ? tt('القاعة / الدائرة', 'Hall / circuit') : tt('المكان', 'Place') }}</label><input v-model="form.location" /></div>
        <div class="field"><label>{{ tt('المسؤول', 'Assignee') }}</label><UiSelect v-model="form.assigned_to"><option value="">—</option><option v-for="u in people" :key="u.id" :value="u.id">{{ u.name }}</option></UiSelect></div>
        <div class="field"><label>{{ tt('الأولوية', 'Priority') }}</label><UiSelect v-model="form.priority"><option v-for="(s, k) in priorities" :key="k" :value="k">{{ s.label }}</option></UiSelect></div>
      </div>
      <div class="field"><label>{{ tt('ملاحظات', 'Notes') }}</label><textarea v-model="form.notes" rows="2" /></div>
    </template>
    <template #footer>
      <button class="btn ghost" @click="emit('close')">{{ tt('إلغاء', 'Cancel') }}</button>
      <button class="btn" :disabled="busy" @click="save"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
    </template>
  </UiModal>
</template>
