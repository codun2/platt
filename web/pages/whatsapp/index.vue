<script setup lang="ts">
definePageMeta({ title: 'رسائل واتساب', titleEn: 'WhatsApp Messages' })
const api = useApi(); const f = useFmt()
const tab = ref<'send' | 'templates' | 'history'>('send')
const audiences = ref<any[]>([]); const templates = ref<any[]>([]); const audience = ref(''); const templateId = ref<any>('')
const rows = ref<any[]>([]); const loading = ref(false); const history = ref<any>(null)
const custom = reactive({ phone: '', name: '', body: '' })
const modal = ref<any>(null); const err = ref('')
const VARS = ['{name}', '{amount}', '{due_date}', '{number}', '{title}', '{asset}', '{unit}', '{date}', '{time}', '{court}', '{company}']

async function loadTemplates() { templates.value = await api.get('/app/whatsapp/templates') }
onMounted(async () => {
  [audiences.value] = await Promise.all([api.get('/app/whatsapp/audiences'), loadTemplates()])
  if (audiences.value.length) audience.value = audiences.value[0].key
})
const forAudience = computed(() => templates.value.filter((t: any) => t.is_active && (t.audience === audience.value || t.audience === 'custom')))
watch(audience, async a => {
  templateId.value = templates.value.find((t: any) => t.audience === a && t.is_active)?.id || ''
  if (!a) return
  loading.value = true
  try { rows.value = await api.get('/app/whatsapp/recipients', { audience: a }) } finally { loading.value = false }
})
watch(tab, t => { if (t === 'history') loadHistory() })
const template = computed(() => templates.value.find((t: any) => String(t.id) === String(templateId.value)))
const fill = (body: string, vars: Record<string, string>) => body.replace(/\{(\w+)\}/g, (m, k) => (vars[k] ?? m))
const preview = (r: any) => template.value ? fill(template.value.body, r.vars) : ''

async function send(r: any) {
  if (!r.wa) { uiAlert(tt('لا يوجد رقم جوال صالح لهذا العميل.', 'No valid mobile number for this customer.')); return }
  const body = preview(r)
  window.open(`https://wa.me/${r.wa}?text=${encodeURIComponent(body)}`, '_blank')
  try { await api.post('/app/whatsapp/log', { phone: r.wa, body, recipient: r.name, context: r.context, customer_id: r.customer_id, template_id: templateId.value || null }); r.sent_today = true } catch { /* logging is best-effort */ }
}
function normalize(p: string) { let d = p.replace(/\D/g, ''); if (d.startsWith('00')) d = d.slice(2); else if (d.startsWith('0')) d = '966' + d.slice(1); else if (d.length <= 9) d = '966' + d; return d }
async function sendCustom() {
  if (!custom.phone.trim() || !custom.body.trim()) return
  const wa = normalize(custom.phone)
  window.open(`https://wa.me/${wa}?text=${encodeURIComponent(custom.body)}`, '_blank')
  try { await api.post('/app/whatsapp/log', { phone: wa, body: custom.body, recipient: custom.name || null }) } catch { /* best-effort */ }
  custom.body = ''
}
async function loadHistory() { history.value = await api.get('/app/whatsapp/history') }
function editTemplate(t?: any) { err.value = ''; modal.value = t ? { ...t } : { name: '', audience: audience.value || 'custom', body: 'مرحباً {name}، ', is_active: true } }
async function saveTemplate() { err.value = ''; try { modal.value.id ? await api.patch(`/app/whatsapp/templates/${modal.value.id}`, modal.value) : await api.post('/app/whatsapp/templates', modal.value); modal.value = null; await loadTemplates() } catch (e: any) { err.value = e.message } }
async function removeTemplate(t: any) { if (!(await uiConfirm(tt(`حذف القالب «${t.name}»؟`, `Delete template «${t.name}»?`)))) return; await api.del(`/app/whatsapp/templates/${t.id}`); await loadTemplates() }
const audienceName = (k: string) => audiences.value.find((a: any) => a.key === k)?.name || (k === 'custom' ? tt('عام', 'General') : k)
const pending = computed(() => rows.value.filter((r: any) => !r.sent_today).length)
</script>

<template>
  <div>
    <div class="card" style="display:flex;gap:12px;align-items:center">
      <i class="ti ti-brand-whatsapp" style="font-size:30px;color:#16a34a" />
      <div style="flex:1"><b>{{ tt('تذكيرات العملاء عبر واتساب', 'WhatsApp customer reminders') }}</b><br /><small class="muted">{{ tt('اختر الفئة فتظهر قائمة جاهزة من بياناتك برسالة معبّأة لكل شخص؛ زر «إرسال» يفتح واتساب بالنص جاهزاً، ويُسجَّل الإرسال حتى لا تتكرر الرسالة في نفس اليوم.', 'Pick a list; each message is pre-filled and opens in WhatsApp. Sends are logged so nobody gets the same reminder twice a day.') }}</small></div>
    </div>
    <div class="card">
      <div class="tabs">
        <button :class="{ on: tab === 'send' }" @click="tab = 'send'"><i class="ti ti-send" /> {{ tt('إرسال', 'Send') }}</button>
        <button :class="{ on: tab === 'templates' }" @click="tab = 'templates'"><i class="ti ti-template" /> {{ tt('القوالب', 'Templates') }} ({{ templates.length }})</button>
        <button :class="{ on: tab === 'history' }" @click="tab = 'history'"><i class="ti ti-history" /> {{ tt('السجل', 'History') }}</button>
      </div>

      <template v-if="tab === 'send'">
        <div class="chips" style="margin-bottom:12px"><span v-for="a in audiences" :key="a.key" class="chip" :class="{ on: audience === a.key }" @click="audience = a.key">{{ a.name }}</span></div>
        <div class="row" style="margin-bottom:12px">
          <span class="muted">{{ tt('القالب', 'Template') }}:</span>
          <UiSelect v-model="templateId" style="min-width:240px" :add-label="tt('قالب جديد', 'New template')" :quick-add="async (n: string) => { const t = await api.post('/app/whatsapp/templates', { name: n, audience: audience || 'custom', body: 'مرحباً {name}، ', is_active: true }); await loadTemplates(); return t.id }"><option value="">—</option><option v-for="t in forAudience" :key="t.id" :value="t.id">{{ t.name }}</option></UiSelect>
          <button v-if="template" class="btn ghost sm" @click="editTemplate(template)"><i class="ti ti-pencil" /> {{ tt('تعديل النص', 'Edit text') }}</button>
          <span v-if="rows.length" class="muted" style="margin-inline-start:auto">{{ rows.length }} {{ tt('شخص', 'people') }} · {{ pending }} {{ tt('لم يُرسل لهم اليوم', 'not messaged today') }}</span>
        </div>
        <p v-if="loading" class="muted">…</p>
        <table v-else-if="rows.length" class="table">
          <thead><tr><th style="width:180px">{{ tt('الاسم', 'Name') }}</th><th>{{ tt('الرسالة', 'Message') }}</th><th style="width:140px"></th></tr></thead>
          <tbody>
            <tr v-for="r in rows" :key="r.context" :style="r.sent_today ? 'opacity:.6' : ''">
              <td><b>{{ r.name }}</b><br /><small class="muted" dir="ltr">{{ r.phone || tt('بلا رقم', 'no number') }}</small></td>
              <td style="white-space:pre-line;font-size:13px">{{ preview(r) || tt('اختر قالباً', 'Pick a template') }}</td>
              <td><button class="btn sm" :class="r.sent_today ? 'ghost' : 'ok'" :disabled="!template || !r.wa" @click="send(r)"><i class="ti ti-brand-whatsapp" /> {{ r.sent_today ? tt('أُرسلت — إعادة', 'Sent — again') : tt('إرسال', 'Send') }}</button></td>
            </tr>
          </tbody>
        </table>
        <p v-else-if="audience" class="empty">{{ tt('لا يوجد أحد في هذه القائمة الآن 🎉', 'Nobody on this list right now 🎉') }}</p>
        <p v-else class="empty">{{ tt('لا توجد قوائم جاهزة لقطاعك بعد — استخدم الرسالة الحرّة بالأسفل.', 'No ready lists for your sector — use a free message below.') }}</p>

        <div class="card-title mt"><i class="ti ti-message" /> {{ tt('رسالة حرّة لأي رقم', 'Free message to any number') }}</div>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('الجوال', 'Mobile') }}</label><input v-model="custom.phone" dir="ltr" placeholder="05xxxxxxxx" /></div>
          <div class="field"><label>{{ tt('الاسم (للسجل)', 'Name (for the log)') }}</label><input v-model="custom.name" /></div>
        </div>
        <div class="field"><textarea v-model="custom.body" rows="3" :placeholder="tt('نص الرسالة…', 'Message…')" /></div>
        <button class="btn ok" :disabled="!custom.phone.trim() || !custom.body.trim()" @click="sendCustom"><i class="ti ti-brand-whatsapp" /> {{ tt('فتح في واتساب', 'Open in WhatsApp') }}</button>
      </template>

      <template v-if="tab === 'templates'">
        <div class="row between" style="margin-bottom:12px"><small class="muted">{{ tt('المتغيرات المتاحة:', 'Variables:') }} <code v-for="v in VARS" :key="v" style="margin-inline-end:6px" dir="ltr">{{ v }}</code></small><button class="btn" @click="editTemplate()"><i class="ti ti-plus" /> {{ tt('قالب جديد', 'New template') }}</button></div>
        <table class="table">
          <thead><tr><th>{{ tt('القالب', 'Template') }}</th><th>{{ tt('الفئة', 'List') }}</th><th>{{ tt('النص', 'Text') }}</th><th></th></tr></thead>
          <tbody><tr v-for="t in templates" :key="t.id" :style="t.is_active ? '' : 'opacity:.5'"><td><b>{{ t.name }}</b></td><td>{{ audienceName(t.audience) }}</td><td style="font-size:13px;max-width:460px">{{ t.body }}</td><td class="row"><button class="btn ghost sm icon" @click="editTemplate(t)"><i class="ti ti-pencil" /></button><button class="btn ghost sm icon" style="color:var(--primary)" @click="removeTemplate(t)"><i class="ti ti-trash" /></button></td></tr></tbody>
        </table>
      </template>

      <template v-if="tab === 'history'">
        <table v-if="history?.data.length" class="table">
          <thead><tr><th>{{ tt('الوقت', 'Time') }}</th><th>{{ tt('إلى', 'To') }}</th><th>{{ tt('الرسالة', 'Message') }}</th><th>{{ tt('بواسطة', 'By') }}</th></tr></thead>
          <tbody><tr v-for="m in history.data" :key="m.id"><td dir="ltr" style="text-align:right;white-space:nowrap">{{ f.dt(m.created_at) }}</td><td>{{ m.recipient || '—' }}<br /><small class="muted" dir="ltr">{{ m.phone }}</small></td><td style="font-size:13px;white-space:pre-line">{{ m.body }}</td><td>{{ m.user?.name }}</td></tr></tbody>
        </table>
        <p v-else class="empty">{{ tt('لم تُرسل رسائل بعد.', 'No messages yet.') }}</p>
      </template>
    </div>

    <UiModal v-if="modal" :title="modal.id ? tt('تعديل القالب', 'Edit template') : tt('قالب جديد', 'New template')" lg @close="modal = null">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('الاسم', 'Name') }}</label><input v-model="modal.name" /></div>
        <div class="field"><label>{{ tt('الفئة', 'List') }}</label><UiSelect v-model="modal.audience"><option v-for="a in audiences" :key="a.key" :value="a.key">{{ a.name }}</option><option value="custom">{{ tt('عام', 'General') }}</option></UiSelect></div>
      </div>
      <div class="field"><label>{{ tt('النص', 'Text') }}</label><textarea v-model="modal.body" rows="5" /></div>
      <div class="chips"><span v-for="v in VARS" :key="v" class="chip" dir="ltr" @click="modal.body += ' ' + v">{{ v }}</span></div>
      <label class="switch mt"><input v-model="modal.is_active" type="checkbox" /><span /> {{ tt('فعّال', 'Active') }}</label>
      <template #footer><button class="btn ghost" @click="modal = null">{{ tt('إلغاء', 'Cancel') }}</button><button class="btn" @click="saveTemplate">{{ tt('حفظ', 'Save') }}</button></template>
    </UiModal>
  </div>
</template>
