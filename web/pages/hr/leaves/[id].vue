<script setup lang="ts">
definePageMeta({ title: 'طلب الإجازة', titleEn: 'Leave Request' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const d = ref<any>(null); const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false); const note = ref('')
const l = computed(() => d.value?.leave)
const canManage = computed(() => auth.can('hr.manage'))
async function load() { try { d.value = await api.get(`/app/hr/leaves/${id}`); note.value = d.value.leave.note || '' } catch (x: any) { if (x.status === 404) notFound.value = true; else throw x } }
onMounted(load)
async function setStatus(status: string, label: string) {
  err.value = ''; msg.value = ''; saving.value = true
  try { await api.post(`/app/hr/leaves/${id}/status`, { status, note: note.value }); msg.value = label; await load() } catch (x: any) { err.value = x.message } finally { saving.value = false }
}
async function remove() { if (!confirm(tt('حذف الطلب؟','Delete request?'))) return; try { await api.del(`/app/hr/leaves/${id}`); navigateTo('/hr/attendance?tab=leaves') } catch (x: any) { err.value = x.message } }
</script>

<template>
  <div v-if="l">
    <div class="breadcrumb"><NuxtLink to="/hr/attendance?tab=leaves">{{ tt('الإجازات','Leaves') }}</NuxtLink> › <b>{{ l.employee?.name }} — {{ leaveTypes[l.type] }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-calendar" /></div><div><b>{{ l.days }} يوم</b><small>{{ f.d(l.from) }} → {{ f.d(l.to) }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-beach" /></div><div><b>{{ leaveTypes[l.type] }}</b><small>{{ tt('نوع الإجازة','Leave type') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-chart-donut" /></div><div><b>{{ d.balance.remaining }} / {{ d.balance.annual }}</b><small>{{ tt('رصيد سنوي متبقٍ (بدون هذا الطلب)','Annual balance left (excl. this request)') }}</small></div></div>
      <div class="card stat"><div class="ic" :style="`background:${l.status === 'approved' ? 'var(--ok)' : l.status === 'rejected' ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-flag" /></div><div><b style="font-size:15px">{{ leaveStatus[l.status]?.label }}</b><small>{{ l.approver ? `بواسطة ${l.approver.name}` : 'الحالة' }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="align-items:flex-start">
        <div><h2 style="margin:0"><NuxtLink :to="`/hr/employees/${l.employee?.id}`" style="color:var(--info)">{{ l.employee?.name }}</NuxtLink></h2><p class="muted" style="margin:6px 0 0">{{ [l.employee?.job_title, l.employee?.department].filter(Boolean).join(' · ') }}</p></div>
        <div v-if="canManage" class="row" style="gap:8px;flex-wrap:wrap">
          <button v-if="l.status === 'pending'" class="btn ok sm" :disabled="saving" @click="setStatus('approved', tt('تمت الموافقة وتسجيل أيام الإجازة في الحضور','Approved; leave days marked in attendance'))"><i class="ti ti-check" /> {{ tt('موافقة','Approve') }}</button>
          <button v-if="l.status === 'pending'" class="btn ghost sm" :disabled="saving" @click="setStatus('rejected', tt('تم رفض الطلب','Request rejected'))"><i class="ti ti-x" /> {{ tt('رفض','Reject') }}</button>
          <button v-if="l.status !== 'pending'" class="btn ghost sm" :disabled="saving" @click="setStatus('pending', tt('أُعيد فتح الطلب','Request reopened'))"><i class="ti ti-refresh" /> {{ tt('إعادة فتح','Reopen') }}</button>
          <button v-if="l.status !== 'approved'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> {{ tt('حذف','Delete') }}</button>
        </div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:14px 0">
      <div class="grid grid-3">
        <div><small class="muted">{{ tt('السبب','Reason') }}</small><br /><b>{{ l.reason || '—' }}</b></div>
        <div><small class="muted">{{ tt('تاريخ القرار','Decision date') }}</small><br /><b>{{ l.approved_at ? f.dt(l.approved_at) : '—' }}</b></div>
        <div><small class="muted">{{ tt('أُنشئ','Created') }}</small><br /><b>{{ f.dt(l.created_at) }}</b></div>
      </div>
      <div v-if="canManage" class="field mt"><label>{{ tt('ملاحظة الإدارة (تُحفظ مع القرار)','Admin note (saved with decision)') }}</label><textarea v-model="note" rows="2" /></div>
      <p v-else-if="l.note" class="mt"><small class="muted">{{ tt('ملاحظة','Note') }}</small><br />{{ l.note }}</p>
    </div>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><h2>{{ tt('الطلب غير موجود','Request not found') }}</h2><NuxtLink to="/hr/attendance?tab=leaves" class="btn ghost mt">{{ tt('العودة','Back') }}</NuxtLink></div>
</template>
