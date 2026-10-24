<script setup lang="ts">
/** New case / project page. */
const props = defineProps<{ kind: 'case' | 'project' }>()
const api = useApi(); const auth = useAuth()
const T = computed(() => projectTerms(props.kind))
if (!auth.can('projects.manage')) navigateTo(T.value.base)
const err = ref(''); const saving = ref(false); const customers = ref<any[]>([]); const people = ref<any[]>([])
const form = reactive<any>(blankProject(props.kind))
useDraft(`project-new-${props.kind}`, form)
onMounted(async () => { customers.value = await api.get('/app/customers', { all: 1, active_only: 1 }); people.value = await api.get('/app/projects/people'); if (!form.lead_id) form.lead_id = auth.state.value.user?.id || '' })
async function save() {
  err.value = ''; saving.value = true
  try { const p = await api.post('/app/projects/items', projectPayload(form)); navigateTo(`${T.value.base}/${p.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink :to="T.base">{{ T.many }}</NuxtLink> › <b>{{ T.newOne }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <ProjectForm :form="form" :customers="customers" :people="people" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button>
        <NuxtLink class="btn ghost" :to="T.base">{{ tt('إلغاء', 'Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
