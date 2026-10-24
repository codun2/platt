<script setup lang="ts">
definePageMeta({ title: 'ملف الطالب', titleEn: 'Student' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute()
const id = Number(route.params.id)
const s = ref<any>(null); const editing = ref(route.query.edit === '1'); const form = reactive<any>(blankStudent()); const err = ref(''); const msg = ref('')
const classes = ref<any[]>([]); const guardians = ref<any[]>([])
const pay = ref<any>(null); const payForm = reactive({ amount: 0, method: 'cash' })
async function load() { s.value = await api.get(`/app/education/students/${id}`); if (!editing.value) Object.assign(form, studentToForm(s.value)) }
onMounted(async () => { await load(); if (editing.value) await startEdit() })
usePageTitle(() => s.value?.name)
async function startEdit() { Object.assign(form, studentToForm(s.value)); [classes.value, guardians.value] = await Promise.all([api.get('/app/education/classes'), api.get('/app/customers', { all: 1, active_only: 1 })]); editing.value = true }
async function save() { err.value = ''; try { await api.patch(`/app/education/students/${id}`, studentPayload(form)); editing.value = false; msg.value = tt('تم الحفظ', 'Saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!(await uiConfirm(tt(`حذف الطالب ${s.value.name}؟`, `Delete ${s.value.name}?`)))) return; try { const r = await api.del(`/app/education/students/${id}`); if (r.deactivated) { uiAlert(tt('للطالب فواتير رسوم، تم تحويله إلى «منسحب» بدل حذفه.', 'Has fee invoices; marked withdrawn instead.')); await load() } else navigateTo('/education/students') } catch (e: any) { uiAlert(e.message) } }
function openPay(i: any) { pay.value = i; payForm.amount = +(+i.total - +i.paid).toFixed(3); payForm.method = 'cash' }
async function doPay() { err.value = ''; try { await api.post(`/app/invoices/${pay.value.id}/pay`, payForm); pay.value = null; msg.value = tt('تم تسجيل الدفعة', 'Payment recorded'); await load() } catch (e: any) { err.value = e.message } }
const billed = computed(() => (s.value?.invoices || []).filter((i: any) => !['draft', 'cancelled'].includes(i.status)).reduce((n: number, i: any) => n + +i.total, 0))
const paid = computed(() => (s.value?.invoices || []).filter((i: any) => !['draft', 'cancelled'].includes(i.status)).reduce((n: number, i: any) => n + +i.paid, 0))
const age = computed(() => s.value?.birth_date ? Math.floor((Date.now() - new Date(s.value.birth_date).getTime()) / 31557600000) : null)
const today = f.today()
</script>

<template>
  <div v-if="s">
    <div class="breadcrumb"><NuxtLink to="/education/students">{{ tt('الطلاب', 'Students') }}</NuxtLink> › <b>{{ s.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div v-if="editing" class="card">
      <StudentForm :form="form" :classes="classes" :guardians="guardians" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px"><button class="btn" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button><button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button></div>
    </div>
    <template v-else>
      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div class="row" style="gap:14px">
            <div class="avatar-lg" :style="{ background: s.gender === 'female' ? '#db2777' : 'var(--info)' }">{{ s.name.slice(0, 1) }}</div>
            <div>
              <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0">{{ s.name }}</h2><span class="badge" :class="studentStatus[s.status]?.cls">{{ studentStatus[s.status]?.label }}</span></div>
              <p class="muted" style="margin:6px 0 0"><span dir="ltr">{{ s.student_no }}</span> · {{ s.edu_class?.name || tt('بدون صف', 'No class') }}<template v-if="age != null"> · {{ age }} {{ tt('سنة', 'yrs') }}</template><template v-if="+s.discount_pct"> · {{ tt('خصم', 'discount') }} {{ +s.discount_pct }}%</template></p>
              <p v-if="s.guardian" class="muted" style="margin:4px 0 0">{{ tt('ولي الأمر', 'Guardian') }}: <NuxtLink :to="`/crm/customers/${s.guardian.id}`" style="color:var(--info)">{{ s.guardian.name }}</NuxtLink> ({{ guardianRelations[s.guardian_relation] || '' }}) <a v-if="s.guardian.phone" :href="`https://wa.me/${String(s.guardian.phone).replace(/\D/g, '')}`" target="_blank" style="color:var(--ok)" dir="ltr"><i class="ti ti-brand-whatsapp" /> {{ s.guardian.phone }}</a></p>
              <p v-else class="muted" style="margin:4px 0 0;color:var(--warn)"><i class="ti ti-alert-triangle" /> {{ tt('لا يوجد ولي أمر — لن تُصدر رسوم لهذا الطالب.', 'No guardian — fees cannot be issued.') }}</p>
            </div>
          </div>
          <div v-if="auth.can('education.manage')" class="row" style="gap:8px"><button class="btn ghost sm" @click="startEdit"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button></div>
        </div>
      </div>
      <div class="grid grid-4 mt">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-invoice" /></div><div><b>{{ f.money(billed) }}</b><small>{{ tt('إجمالي الرسوم', 'Total fees') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(paid) }}</b><small>{{ tt('المدفوع', 'Paid') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-wallet" /></div><div><b>{{ f.money(billed - paid) }}</b><small>{{ tt('المتبقي', 'Balance') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-user-x" /></div><div><b>{{ s.attendance_summary?.absent || 0 }}</b><small>{{ tt('أيام غياب (آخر 3 أشهر)', 'Absences (3 months)') }}</small></div></div>
      </div>
      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-coins" /> {{ tt('الرسوم والأقساط', 'Fees') }}</div>
          <table v-if="s.invoices.length" class="table">
            <thead><tr><th>{{ tt('البيان', 'Item') }}</th><th>{{ tt('الاستحقاق', 'Due') }}</th><th>{{ tt('المبلغ', 'Amount') }}</th><th>{{ tt('المدفوع', 'Paid') }}</th><th></th></tr></thead>
            <tbody><tr v-for="i in s.invoices" :key="i.id" :style="['sent', 'partial'].includes(i.status) && i.due_date && i.due_date < today ? 'background:var(--primary-50)' : ''">
              <td><NuxtLink :to="`/crm/invoices/${i.id}`" style="color:var(--info)">{{ (i.subject || '').split(' — ')[0] }}</NuxtLink><br /><small class="muted" dir="ltr">{{ i.number }}</small></td>
              <td dir="ltr" style="text-align:right">{{ i.due_date || '—' }}</td><td class="num">{{ f.money(i.total) }}</td><td class="num">{{ f.money(i.paid) }}</td>
              <td><button v-if="['sent', 'partial', 'draft'].includes(i.status)" class="btn sm" @click="openPay(i)"><i class="ti ti-cash" /> {{ tt('تحصيل', 'Collect') }}</button><span v-else class="badge" :class="invoiceStatus[i.status]?.cls">{{ invoiceStatus[i.status]?.label }}</span></td></tr></tbody>
          </table>
          <p v-else class="empty">{{ tt('لم تُصدر رسوم بعد. أصدرها من صفحة «الرسوم والأقساط».', 'No fees yet. Issue them from the Fees page.') }}</p>
        </div>
        <div>
          <div class="card">
            <div class="card-title"><i class="ti ti-calendar-check" /> {{ tt('الحضور (آخر 3 أشهر)', 'Attendance (3 months)') }}</div>
            <div class="chips" style="margin-bottom:8px"><span v-for="(st, k) in attendStatus" :key="k" class="chip"><i :class="`ti ${st.icon}`" /> {{ st.label }} <b>{{ s.attendance_summary?.[k] || 0 }}</b></span></div>
            <div v-for="a in s.attendance.filter((x: any) => x.status !== 'present').slice(0, 12)" :key="a.date" class="row between" style="padding:4px 0;border-bottom:1px dashed var(--line)"><span dir="ltr">{{ a.date }}</span><span><span class="badge" :class="attendStatus[a.status]?.cls">{{ attendStatus[a.status]?.label }}</span> <small class="muted">{{ a.note }}</small></span></div>
          </div>
          <div class="card">
            <div class="card-title"><i class="ti ti-id" /> {{ tt('بيانات إضافية', 'More') }}</div>
            <div class="grid grid-2">
              <div><small class="muted">{{ tt('تاريخ الميلاد', 'Birth date') }}</small><br /><b dir="ltr">{{ s.birth_date || '—' }}</b></div>
              <div><small class="muted">{{ tt('الهوية', 'ID') }}</small><br /><b dir="ltr">{{ s.national_id || '—' }}</b></div>
              <div><small class="muted">{{ tt('الجنسية', 'Nationality') }}</small><br /><b>{{ s.nationality || '—' }}</b></div>
              <div><small class="muted">{{ tt('النقل', 'Transport') }}</small><br /><b>{{ s.transport || '—' }}</b></div>
              <div><small class="muted">{{ tt('هاتف طوارئ', 'Emergency') }}</small><br /><b dir="ltr">{{ s.emergency_phone || '—' }}</b></div>
              <div><small class="muted">{{ tt('الالتحاق', 'Enrolled') }}</small><br /><b dir="ltr">{{ s.enrolled_at || '—' }}</b></div>
            </div>
            <div v-if="s.medical_notes" class="alert warn mt" style="margin-bottom:0"><i class="ti ti-first-aid-kit" /> {{ s.medical_notes }}</div>
            <div v-if="s.siblings.length" class="mt"><small class="muted">{{ tt('الإخوة بالمدرسة', 'Siblings') }}</small><div class="chips"><NuxtLink v-for="b in s.siblings" :key="b.id" :to="`/education/students/${b.id}`" class="chip">{{ b.name }}</NuxtLink></div></div>
          </div>
        </div>
      </div>
    </template>
    <UiModal v-if="pay" :title="tt('تحصيل قسط', 'Collect fee')" @close="pay = null">
      <p class="muted" style="margin-top:0">{{ pay.subject }} · {{ tt('المتبقي', 'balance') }} {{ f.money(+pay.total - +pay.paid) }}</p>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('المبلغ', 'Amount') }}</label><input v-model.number="payForm.amount" type="number" min="0" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="payForm.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option></UiSelect></div>
      </div>
      <template #footer><button class="btn ghost" @click="pay = null">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn ok" :disabled="!(payForm.amount > 0)" @click="doPay"><i class="ti ti-check" /> {{ tt('تسجيل', 'Record') }}</button></template>
    </UiModal>
  </div>
</template>

<style scoped>
.avatar-lg { width: 54px; height: 54px; border-radius: 50%; color: #fff; display: grid; place-items: center; font-size: 22px; font-weight: 800; flex-shrink: 0; }
</style>
