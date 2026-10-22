<script setup lang="ts">
definePageMeta({ title: 'قيد يدوي جديد', titleEn: 'New Journal Entry' })
const api = useApi(); const auth = useAuth(); const { tt } = useI18n()
if (!auth.can('accounting.manage')) navigateTo('/accounting/journal')
const accounts = ref<any[]>([]); const err = ref(''); const saving = ref(false)
const form = reactive<any>(blankJournal())
const totals = computed(() => journalTotals(form.lines))
onMounted(async () => { accounts.value = await api.get('/app/accounting/accounts') })
async function save(status: 'posted' | 'draft') {
  err.value = ''; saving.value = true
  try { const e = await api.post('/app/accounting/journal', { ...journalPayload(form), status }); navigateTo(`/accounting/journal/${e.id}`) }
  catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false }
}
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting/journal">{{ tt('القيود اليومية','Journal') }}</NuxtLink> › <b>{{ tt('قيد يدوي جديد','New journal entry') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="card-title"><i class="ti ti-book" /> {{ tt('بيانات القيد','Entry details') }}</div>
      <div class="grid grid-3">
        <div class="field"><label>{{ tt('التاريخ','Date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
        <div class="field"><label>{{ tt('المرجع','Reference') }}</label><input v-model="form.reference" dir="ltr" /></div>
        <div class="field"><label>{{ tt('البيان','Description') }}</label><input v-model="form.description" :placeholder="tt('مثال: إيداع رأس المال','e.g. capital deposit')" /></div>
      </div>
      <div class="card-title" style="margin-top:8px"><i class="ti ti-list-numbers" /> {{ tt('الأطراف','Lines') }}</div>
      <JournalLinesEditor :lines="form.lines" :accounts="accounts" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !totals.balanced" @click="save('posted')"><i class="ti ti-check" /> {{ tt('ترحيل القيد','Post entry') }}</button>
        <button class="btn ghost" :disabled="saving || !totals.balanced" @click="save('draft')"><i class="ti ti-device-floppy" /> {{ tt('حفظ كمسودّة','Save as draft') }}</button>
        <NuxtLink class="btn ghost" to="/accounting/journal">{{ tt('إلغاء','Cancel') }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
