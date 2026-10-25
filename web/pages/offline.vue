<script setup lang="ts">
definePageMeta({ title: 'البيانات المحلية', titleEn: 'Local Data' })
const off = useOffline(); const f = useFmt()
const ops = ref<OutboxOp[]>([])
async function load() { ops.value = await off.outbox.all() }
onMounted(load)
watch(() => [off.state.pending, off.state.failed], load)
async function retry(op: OutboxOp) { op.status = 'pending'; op.error = undefined; await off.outbox.put(op); await off.syncNow(); await load() }
async function discard(op: OutboxOp) { if (!(await uiConfirm(tt(`حذف «${op.label}» من الجهاز نهائياً؟ لن يُرفع للنظام.`, `Discard «${op.label}»? It will never be uploaded.`)))) return; await off.outbox.remove(op.id); await load() }
async function syncAll() { await off.syncNow(); await load() }
const secure = typeof window !== 'undefined' && window.isSecureContext && 'serviceWorker' in navigator
</script>

<template>
  <div>
    <div class="grid grid-4">
      <div class="card stat"><div class="ic" :style="{ background: off.state.online ? 'var(--ok)' : 'var(--warn)' }"><i :class="`ti ${off.state.online ? 'ti-wifi' : 'ti-wifi-off'}`" /></div><div><b>{{ off.state.online ? tt('متصل', 'Online') : tt('غير متصل', 'Offline') }}</b><small>{{ tt('حالة الاتصال', 'Connection') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-cloud-upload" /></div><div><b>{{ off.state.pending }}</b><small>{{ tt('بانتظار الرفع', 'Waiting to upload') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ off.state.failed }}</b><small>{{ tt('رفضها النظام — تحتاج مراجعة', 'Rejected — needs review') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--muted)"><i class="ti ti-clock" /></div><div><b style="font-size:14px">{{ off.state.lastSync ? f.dt(off.state.lastSync) : '—' }}</b><small>{{ tt('آخر مزامنة', 'Last sync') }}</small></div></div>
    </div>
    <div class="card mt">
      <div class="row between" style="margin-bottom:12px">
        <div class="card-title" style="margin:0"><i class="ti ti-database" /> {{ tt('عمليات محفوظة على هذا الجهاز', 'Operations saved on this device') }}</div>
        <button class="btn" :disabled="!off.state.online || off.state.syncing || !off.state.pending" @click="syncAll"><i :class="`ti ${off.state.syncing ? 'ti-loader-2 spin' : 'ti-refresh'}`" /> {{ tt('رفع الآن', 'Upload now') }}</button>
      </div>
      <table v-if="ops.length" class="table">
        <thead><tr><th>{{ tt('العملية', 'Operation') }}</th><th>{{ tt('وقت الحفظ', 'Saved at') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="op in ops" :key="op.id">
            <td><b>{{ op.label }}</b></td>
            <td dir="ltr" style="text-align:right">{{ f.dt(op.created_at) }}</td>
            <td><span class="badge" :class="op.status === 'failed' ? 'bad' : 'info'">{{ op.status === 'failed' ? tt('مرفوضة', 'Rejected') : tt('بانتظار الرفع', 'Pending') }}</span><small v-if="op.error" style="display:block;color:var(--primary)">{{ op.error }}</small></td>
            <td class="row"><button v-if="op.status === 'failed'" class="btn ghost sm" @click="retry(op)"><i class="ti ti-refresh" /> {{ tt('إعادة المحاولة', 'Retry') }}</button><button class="btn ghost sm icon" style="color:var(--primary)" @click="discard(op)"><i class="ti ti-trash" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="empty">{{ tt('كل العمليات مرفوعة للنظام ✔', 'Everything is uploaded ✔') }}</p>
    </div>
    <div class="card">
      <div class="card-title"><i class="ti ti-info-circle" /> {{ tt('كيف يعمل العمل بدون إنترنت', 'How offline works') }}</div>
      <ul style="margin:0;padding-inline-start:20px;line-height:2">
        <li>{{ tt('إذا انقطع الإنترنت تستمر بالبيع في شاشة البيع؛ الفواتير تُحفظ على الجهاز برقم مؤقت وتُرفع تلقائياً عند عودة الاتصال.', 'If the internet drops, keep selling; receipts are saved here with a temporary number and upload automatically.') }}</li>
        <li>{{ tt('كل فاتورة تُرفع مرة واحدة فقط حتى لو تكررت المحاولة.', 'Each receipt uploads exactly once, even if retried.') }}</li>
        <li>{{ tt('لا تمسح بيانات المتصفح ولا تسجّل الخروج قبل رفع العمليات المعلّقة.', 'Do not clear browser data or sign out before pending operations upload.') }}</li>
        <li v-if="!secure" style="color:var(--warn)">{{ tt('فتح النظام من البداية بدون إنترنت يتطلب الدخول عبر رابط آمن (https). حالياً يعمل البيع بدون إنترنت طالما الصفحة مفتوحة.', 'Opening the app from scratch offline needs a secure (https) address. Right now offline selling works while the page stays open.') }}</li>
      </ul>
    </div>
  </div>
</template>
