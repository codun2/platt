<script setup lang="ts">
definePageMeta({ title: 'كشف الرواتب', titleEn: 'Payroll Run' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const p = ref<any>(null); const money = ref<any[]>([]); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const paying = ref(false); const payAcc = ref<any>(''); const payDate = ref(new Date().toISOString().slice(0, 10))
const editItem = ref<any>(null); const ef = reactive<any>({ overtime: 0, bonus: 0, absence_days: 0, absence_deduction: 0, other_deductions: 0, advance: 0, note: '' })
const canManage = computed(() => auth.can('hr.manage'))
const isDraft = computed(() => p.value?.status === 'draft')

async function load() { try { p.value = await api.get(`/app/hr/payroll/${id}`) } catch (x: any) { if (x.status === 404) { notFound.value = true; return } throw x } }
onMounted(async () => { await load(); try { money.value = (await api.get('/app/hr/payroll')).money; payAcc.value = money.value.find((m: any) => m.system_key === 'bank')?.id || money.value[0]?.id || '' } catch {} })
async function act(fn: () => Promise<any>, ok: string) { err.value = ''; msg.value = ''; saving.value = true; try { await fn(); msg.value = ok; await load() } catch (x: any) { err.value = x.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) } }
function openEdit(i: any) { editItem.value = i; Object.assign(ef, { overtime: +i.overtime, bonus: +i.bonus, absence_days: +i.absence_days, absence_deduction: +i.absence_deduction, other_deductions: +i.other_deductions, advance: +i.advance, note: i.note || '' }) }
const saveItem = () => act(async () => { await api.patch(`/app/hr/payroll/${id}/items/${editItem.value.id}`, ef); editItem.value = null }, tt('تم تحديث سطر الموظف','Line updated'))
const approve = () => act(() => api.post(`/app/hr/payroll/${id}/approve`), tt('تم اعتماد الكشف','Payroll approved'))
const pay = () => act(async () => { await api.post(`/app/hr/payroll/${id}/pay`, { paid_from_account_id: payAcc.value, date: payDate.value }); paying.value = false }, tt('تم صرف الرواتب وترحيل القيد','Salaries paid and entry posted'))
async function remove() { if (!confirm(tt('حذف الكشف؟','Delete payroll?'))) return; try { await api.del(`/app/hr/payroll/${id}`); navigateTo('/hr/payroll') } catch (x: any) { err.value = x.message } }
const print = () => window.print()
const deductions = (i: any) => +i.absence_deduction + +i.fixed_deductions + +i.other_deductions + +i.advance
</script>

<template>
  <div v-if="p">
    <div class="breadcrumb no-print"><NuxtLink to="/hr/payroll">{{ tt('الرواتب','Payroll') }}</NuxtLink> › <b>{{ monthLabel(p.period) }}</b></div>
    <div v-if="msg" class="alert ok no-print">{{ msg }}</div>
    <div v-if="err" class="alert bad no-print">{{ err }}</div>
    <div class="grid grid-4 no-print" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ p.items.length }}</b><small>{{ tt('موظف في الكشف','Employees in run') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-plus" /></div><div><b>{{ f.money(+p.total_basic + +p.total_allowances + +p.total_additions) }}</b><small>{{ tt('إجمالي المستحقات','Total earnings') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-minus" /></div><div><b>{{ f.money(p.total_deductions) }}</b><small>{{ tt('إجمالي الخصومات','Total deductions') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(p.total_net, true) }}</b><small>{{ tt('صافي الرواتب','Net payroll') }}</small></div></div>
    </div>

    <div class="card">
      <div class="row between no-print" style="align-items:flex-start">
        <div><div class="row" style="gap:10px;align-items:center"><h2 style="margin:0">{{ tt('كشف رواتب','Payroll') }} {{ monthLabel(p.period) }}</h2><span class="badge" :class="payrollStatus[p.status]?.cls">{{ payrollStatus[p.status]?.label }}</span></div><p class="muted" style="margin:6px 0 0"><span dir="ltr">{{ p.number }}</span><span v-if="p.creator"> · أنشأه {{ p.creator.name }}</span><span v-if="p.approved_at"> · اعتُمد {{ f.dt(p.approved_at) }}</span><span v-if="p.paid_at"> · صُرف {{ f.dt(p.paid_at) }} من {{ p.paid_from?.name }}</span></p></div>
        <div class="row" style="gap:8px;flex-wrap:wrap">
          <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> {{ tt('طباعة','Print') }}</button>
          <template v-if="canManage">
            <button v-if="isDraft" class="btn ok sm" :disabled="saving" @click="approve"><i class="ti ti-check" /> {{ tt('اعتماد الكشف','Approve') }}</button>
            <button v-if="p.status === 'approved'" class="btn sm" @click="paying = !paying"><i class="ti ti-cash" /> {{ tt('صرف الرواتب','Pay salaries') }}</button>
            <button v-if="p.status !== 'paid'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> {{ tt('حذف','Delete') }}</button>
          </template>
        </div>
      </div>
      <div v-if="paying" class="pay-panel mt no-print">
        <div class="card-title" style="margin-bottom:8px"><i class="ti ti-cash" /> {{ tt('صرف','Pay') }} {{ f.money(p.total_net, true) }}</div>
        <div class="row" style="align-items:flex-end;gap:10px">
          <div class="field" style="margin:0"><label>{{ tt('الصرف من','Pay from') }}</label><UiSelect v-model="payAcc" style="min-width:200px"><option v-for="m in money" :key="m.id" :value="m.id">{{ m.code }} — {{ m.name }}</option></UiSelect></div>
          <div class="field" style="margin:0"><label>{{ tt('التاريخ','Date') }}</label><input v-model="payDate" type="date" dir="ltr" /></div>
          <button class="btn" :disabled="saving || !payAcc" @click="pay"><i class="ti ti-check" /> {{ tt('تأكيد الصرف','Confirm payment') }}</button><button class="btn ghost" @click="paying = false">{{ tt('إغلاق','Close') }}</button>
        </div>
      </div>
      <hr class="no-print" style="border:none;border-top:1px solid var(--line);margin:14px 0">
      <h3 class="print-only" style="text-align:center">{{ tt('كشف رواتب','Payroll') }} {{ monthLabel(p.period) }} — {{ auth.state.value.tenant?.name }}</h3>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الموظف','Employee') }}</th><th>{{ tt('الأساسي','Basic') }}</th><th>{{ tt('البدلات','Allow.') }}</th><th>{{ tt('إضافي','OT') }}</th><th>{{ tt('مكافأة','Bonus') }}</th><th>{{ tt('غياب (يوم)','Absence (d)') }}</th><th>{{ tt('خصم غياب','Absence ded.') }}</th><th>{{ tt('ثابتة','Fixed') }}</th><th>{{ tt('أخرى','Other') }}</th><th>{{ tt('سلفة','Advance') }}</th><th>{{ tt('الصافي','Net') }}</th><th class="no-print"></th></tr></thead>
        <tbody>
          <tr v-for="(i, idx) in p.items" :key="i.id">
            <td class="muted">{{ idx + 1 }}</td>
            <td><NuxtLink :to="`/hr/employees/${i.employee?.id}`" style="color:var(--info);font-weight:700">{{ i.employee?.name }}</NuxtLink><br /><small class="muted">{{ [i.employee?.job_title, i.employee?.department].filter(Boolean).join(' · ') }}<span v-if="i.note"> · {{ i.note }}</span></small></td>
            <td class="num">{{ f.money(i.basic) }}</td><td class="num">{{ f.money(i.allowances) }}</td><td class="num">{{ f.money(i.overtime) }}</td><td class="num">{{ f.money(i.bonus) }}</td>
            <td class="num">{{ +i.absence_days || '' }}</td><td class="num" :style="+i.absence_deduction ? 'color:var(--primary)' : ''">{{ f.money(i.absence_deduction) }}</td><td class="num">{{ f.money(i.fixed_deductions) }}</td><td class="num">{{ f.money(i.other_deductions) }}</td><td class="num">{{ f.money(i.advance) }}</td>
            <td class="num"><b>{{ f.money(i.net) }}</b></td>
            <td class="no-print"><button v-if="canManage && isDraft" class="btn ghost sm icon" @click="openEdit(i)"><i class="ti ti-pencil" /></button></td>
          </tr>
          <tr style="background:#f8fafc;font-weight:700"><td></td><td>{{ tt('الإجمالي','Total') }}</td><td class="num">{{ f.money(p.total_basic) }}</td><td class="num">{{ f.money(p.total_allowances) }}</td><td class="num" colspan="2">{{ f.money(p.total_additions) }}</td><td></td><td class="num" colspan="4">{{ f.money(p.total_deductions) }}</td><td class="num">{{ f.money(p.total_net) }}</td><td class="no-print"></td></tr>
        </tbody>
      </table>
      <p v-if="!p.items.length" class="empty">{{ tt('لا يوجد موظفون في هذا الكشف.','No employees in this run.') }}</p>
    </div>

    <!-- Small essential edit: one payroll line -->
    <UiModal v-if="editItem" :title="tt(`تعديل سطر: ${editItem.employee?.name}`,`Edit line: ${editItem.employee?.name}`)" @close="editItem = null">
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('عمل إضافي (مبلغ)','Overtime (amount)') }}</label><input v-model.number="ef.overtime" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('مكافأة','Bonus') }}</label><input v-model.number="ef.bonus" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('أيام الغياب','Absence days') }}</label><input v-model.number="ef.absence_days" type="number" step="0.5" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('خصم الغياب (مبلغ)','Absence deduction') }}</label><input v-model.number="ef.absence_deduction" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('خصومات أخرى','Other deductions') }}</label><input v-model.number="ef.other_deductions" type="number" step="0.001" min="0" dir="ltr" /></div>
        <div class="field"><label>{{ tt('سلفة مستردّة','Advance recovered') }}</label><input v-model.number="ef.advance" type="number" step="0.001" min="0" dir="ltr" /></div>
      </div>
      <div class="field"><label>{{ tt('ملاحظة','Note') }}</label><input v-model="ef.note" /></div>
      <p class="muted" style="font-size:12px">{{ tt('الصافي المتوقع:','Expected net:') }} <b>{{ f.money(+editItem.basic + +editItem.allowances + (+ef.overtime || 0) + (+ef.bonus || 0) - (+ef.absence_deduction || 0) - +editItem.fixed_deductions - (+ef.other_deductions || 0) - (+ef.advance || 0)) }}</b></p>
      <template #footer><button class="btn ghost" @click="editItem = null">{{ tt('إلغاء','Cancel') }}</button><button class="btn" :disabled="saving" @click="saveItem">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>{{ tt('الكشف غير موجود','Payroll not found') }}</h2><NuxtLink to="/hr/payroll" class="btn ghost mt">{{ tt('العودة','Back') }}</NuxtLink></div>
</template>
