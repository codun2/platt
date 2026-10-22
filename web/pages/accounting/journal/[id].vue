<script setup lang="ts">
definePageMeta({ title: 'القيد' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id)
const e = ref<any>(null); const accounts = ref<any[]>([]); const editing = ref(route.query.edit === '1')
const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankJournal())
const canManage = computed(() => auth.can('accounting.manage'))
const isManual = computed(() => !e.value?.source_type || e.value.source_type === 'manual')
const totals = computed(() => journalTotals(form.lines))
const link = computed(() => sourceLink(e.value?.source_type, e.value?.source_id))

async function load() {
  try { e.value = await api.get(`/app/accounting/journal/${id}`) } catch (x: any) { if (x.status === 404) { notFound.value = true; return } throw x }
  Object.assign(form, journalToForm(e.value))
}
onMounted(async () => { await load(); accounts.value = await api.get('/app/accounting/accounts') })
async function save() {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.patch(`/app/accounting/journal/${id}`, journalPayload(form)); msg.value = 'تم حفظ القيد'; editing.value = false; await load() }
  catch (x: any) { err.value = x.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!confirm('حذف القيد نهائياً؟')) return; try { await api.del(`/app/accounting/journal/${id}`); navigateTo('/accounting/journal') } catch (x: any) { err.value = x.message } }
const print = () => window.print()
</script>

<template>
  <div v-if="e">
    <div class="breadcrumb no-print"><NuxtLink to="/accounting/journal">القيود اليومية</NuxtLink> › <b dir="ltr">{{ e.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <div class="card-title"><i class="ti ti-book" /> تعديل القيد {{ e.number }}</div>
      <div class="grid grid-3">
        <div class="field"><label>التاريخ</label><input v-model="form.date" type="date" dir="ltr" /></div>
        <div class="field"><label>المرجع</label><input v-model="form.reference" dir="ltr" /></div>
        <div class="field"><label>البيان</label><input v-model="form.description" /></div>
      </div>
      <JournalLinesEditor :lines="form.lines" :accounts="accounts" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !totals.balanced" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
      </div>
    </div>

    <div v-else class="card">
      <div class="row between no-print" style="align-items:flex-start">
        <div>
          <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0" dir="ltr">{{ e.number }}</h2><span class="badge" :class="e.status === 'posted' ? 'ok' : 'muted'">{{ e.status === 'posted' ? 'مرحَّل' : 'مسودّة' }}</span><span class="badge" :class="isManual ? 'info' : 'muted'">{{ journalSources[e.source_type || 'manual'] }}</span></div>
          <p class="muted" style="margin:6px 0 0">{{ f.d(e.date) }}<span v-if="e.reference"> · مرجع <span dir="ltr">{{ e.reference }}</span></span><span v-if="e.creator"> · {{ e.creator.name }}</span><span v-if="link"> · <NuxtLink :to="link" style="color:var(--info)">فتح المستند المصدر</NuxtLink></span></p>
        </div>
        <div class="row" style="gap:8px">
          <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> طباعة</button>
          <template v-if="canManage && isManual"><button class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button><button class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> حذف</button></template>
        </div>
      </div>
      <p v-if="!isManual" class="muted no-print" style="font-size:12px;margin-top:8px"><i class="ti ti-lock" /> قيد آلي: يُعدَّل أو يُعكس من مستنده المصدر فقط.</p>
      <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
      <h3 style="margin:0 0 6px">{{ e.description || 'قيد يومية' }}</h3>
      <table class="table">
        <thead><tr><th>#</th><th>الحساب</th><th>البيان</th><th>مدين</th><th>دائن</th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in e.lines" :key="l.id"><td class="muted">{{ i + 1 }}</td><td><NuxtLink :to="`/accounting/accounts/${l.account_id}`" style="color:var(--info)">{{ l.account?.code }} — {{ l.account?.name }}</NuxtLink></td><td class="muted">{{ l.description || '' }}</td><td class="num">{{ +l.debit ? f.money(l.debit) : '' }}</td><td class="num">{{ +l.credit ? f.money(l.credit) : '' }}</td></tr>
          <tr style="background:#f8fafc"><td colspan="3"><b>الإجمالي</b></td><td class="num"><b>{{ f.money(e.total) }}</b></td><td class="num"><b>{{ f.money(e.total) }}</b></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>القيد غير موجود</h2><NuxtLink to="/accounting/journal" class="btn ghost mt">العودة</NuxtLink></div>
</template>
