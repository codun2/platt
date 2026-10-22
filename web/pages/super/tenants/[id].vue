<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'ملف المشترك', titleEn: 'Tenant Profile' })
const api = useApi()
const route = useRoute()
const { tt, L } = useI18n()
const id = route.params.id
const d = ref<any>(null)
const plans = ref<any[]>([]); const presets = ref<any[]>([])
const tab = ref<'info' | 'modules' | 'subs'>('info')
const msg = ref(''); const err = ref('')
const form = reactive<any>({})
const renew = reactive({ months: 1, plan_id: '', amount: '', note: '' })
const newPass = ref('')
const statusLabel: any = { trial: L('تجريبي','Trial'), active: L('فعّال','Active'), expired: L('منتهٍ','Expired'), suspended: L('موقوف','Suspended') }

async function load() {
  d.value = await api.get(`/super/tenants/${id}`)
  const t = d.value.tenant
  Object.assign(form, { name: t.name, phone: t.phone || '', email: t.email || '', plan_id: t.plan_id || '', sector_preset_id: t.sector_preset_id || '', status: t.status, subscription_status: t.subscription_status, subscription_renews_at: t.subscription_renews_at || '', trial_ends_at: t.trial_ends_at || '', notes: t.notes || '' })
  renew.plan_id = t.plan_id || ''
}
onMounted(async () => { await load(); plans.value = await api.get('/super/plans'); presets.value = await api.get('/super/presets') })

async function act(fn: () => Promise<any>, ok = tt('تم الحفظ','Saved')) {
  msg.value = ''; err.value = ''
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message }
}
const save = () => act(() => api.patch(`/super/tenants/${id}`, { ...form, plan_id: form.plan_id || null, sector_preset_id: form.sector_preset_id || null, subscription_renews_at: form.subscription_renews_at || null, trial_ends_at: form.trial_ends_at || null }))
const toggleModule = (m: any) => act(() => api.post(`/super/tenants/${id}/modules`, { module_id: m.id, enabled: !m.enabled }), tt('تم تحديث الوحدة','Module updated'))
const applyPreset = (key: string) => act(() => api.post(`/super/tenants/${id}/apply-preset`, { preset_key: key }), tt('تم تطبيق الباقة','Preset applied'))
const doRenew = () => act(() => api.post(`/super/tenants/${id}/renew`, { ...renew, plan_id: renew.plan_id || null, amount: renew.amount === '' ? null : renew.amount }), tt('تم تسجيل التجديد','Renewal recorded'))
const resetPass = () => act(() => api.post(`/super/tenants/${id}/reset-owner-password`, { password: newPass.value }), tt('تم تغيير كلمة المرور','Password changed'))
</script>

<template>
  <div v-if="d">
    <div class="breadcrumb"><NuxtLink to="/super/tenants">{{ tt('المشتركون','Tenants') }}</NuxtLink> › <b>{{ d.tenant.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d.tenant.users_count }}</b><small>{{ tt('المستخدمون','Users') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-apps" /></div><div><b>{{ d.modules.filter((m: any) => m.enabled).length }}</b><small>{{ tt('وحدات مفعّلة','Enabled modules') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-credit-card" /></div><div><b style="font-size:16px">{{ statusLabel[d.tenant.subscription_status] }}</b><small>{{ d.tenant.plan ? tt(d.tenant.plan.name_ar, d.tenant.plan.name_en || d.tenant.plan.name_ar) : tt('بدون خطة','No plan') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b style="font-size:16px">{{ d.tenant.subscription_renews_at || '—' }}</b><small>{{ tt('تاريخ التجديد','Renewal date') }}</small></div></div>
    </div>

    <div class="card">
      <div class="tabs">
        <button :class="{ on: tab === 'info' }" @click="tab = 'info'">{{ tt('البيانات','Info') }}</button>
        <button :class="{ on: tab === 'modules' }" @click="tab = 'modules'">{{ tt('الوحدات','Modules') }}</button>
        <button :class="{ on: tab === 'subs' }" @click="tab = 'subs'">{{ tt('الاشتراك','Subscription') }}</button>
      </div>

      <div v-if="tab === 'info'" class="grid grid-2">
        <div>
          <div class="field"><label>{{ tt('اسم الشركة','Company name') }}</label><input v-model="form.name" /></div>
          <div class="field"><label>{{ tt('البريد','Email') }}</label><input v-model="form.email" dir="ltr" /></div>
          <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
          <div class="field"><label>{{ tt('القطاع','Sector') }}</label><select v-model="form.sector_preset_id"><option value="">—</option><option v-for="p in presets" :key="p.id" :value="p.id">{{ tt(p.name_ar, p.name_en || p.name_ar) }}</option></select></div>
          <div class="field"><label>{{ tt('الخطة','Plan') }}</label><select v-model="form.plan_id"><option value="">—</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ tt(p.name_ar, p.name_en || p.name_ar) }} ({{ p.price_monthly }} {{ tt('ريال/شهر','SAR/mo') }})</option></select></div>
        </div>
        <div>
          <div class="field"><label>{{ tt('حالة الحساب','Account status') }}</label><select v-model="form.status"><option value="active">{{ tt('نشط','Active') }}</option><option value="suspended">{{ tt('موقوف','Suspended') }}</option></select></div>
          <div class="field"><label>{{ tt('حالة الاشتراك','Subscription status') }}</label><select v-model="form.subscription_status"><option value="trial">{{ tt('تجريبي','Trial') }}</option><option value="active">{{ tt('فعّال','Active') }}</option><option value="expired">{{ tt('منتهٍ','Expired') }}</option><option value="suspended">{{ tt('موقوف','Suspended') }}</option></select></div>
          <div class="field"><label>{{ tt('تاريخ التجديد','Renewal date') }}</label><input v-model="form.subscription_renews_at" type="date" /></div>
          <div class="field"><label>{{ tt('نهاية التجربة','Trial end') }}</label><input v-model="form.trial_ends_at" type="date" /></div>
          <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.notes" rows="3" /></div>
          <button class="btn" @click="save">{{ tt('حفظ','Save') }}</button>
          <div class="mt" style="border-top:1px solid var(--line);padding-top:12px">
            <small class="muted">{{ tt('المالك:','Owner:') }} <b>{{ d.owner?.name }}</b> · <span dir="ltr">{{ d.owner?.email }}</span></small>
            <div class="row mt"><input v-model="newPass" :placeholder="tt('كلمة مرور جديدة للمالك','New owner password')" dir="ltr" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" /><button class="btn ghost sm" @click="resetPass">{{ tt('تغيير','Change') }}</button></div>
          </div>
        </div>
      </div>

      <div v-if="tab === 'modules'">
        <div class="row between" style="margin-bottom:12px">
          <p class="muted" style="margin:0">{{ tt('فعّل/عطّل الوحدات لهذا المشترك. الوحدات الأساسية مفعّلة دائماً.','Enable/disable modules for this tenant. Core modules are always on.') }}</p>
          <div class="row"><span class="muted">{{ tt('تطبيق باقة:','Apply preset:') }}</span><select style="padding:6px 10px;border:1px solid var(--line);border-radius:8px" @change="applyPreset(($event.target as HTMLSelectElement).value)"><option value="">{{ tt('اختر...','Select...') }}</option><option v-for="p in presets" :key="p.key" :value="p.key">{{ tt(p.name_ar, p.name_en || p.name_ar) }}</option></select></div>
        </div>
        <table class="table">
          <thead><tr><th>{{ tt('الوحدة','Module') }}</th><th>{{ tt('المحرّك','Engine') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
          <tbody>
            <tr v-for="m in d.modules" :key="m.id">
              <td><i :class="`ti ${m.icon}`" /> {{ tt(m.name_ar, m.name_en || m.name_ar) }}</td>
              <td class="muted">{{ m.engine }}</td>
              <td><span class="badge" :class="m.enabled ? 'ok' : 'muted'">{{ m.is_core ? tt('أساسية','Core') : (m.enabled ? tt('مفعّلة','Enabled') : tt('معطّلة','Disabled')) }}</span></td>
              <td><label v-if="!m.is_core" class="switch"><input type="checkbox" :checked="m.enabled" @change="toggleModule(m)" /><span /></label></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="tab === 'subs'" class="grid grid-2">
        <div>
          <div class="card-title"><i class="ti ti-refresh" /> {{ tt('تجديد الاشتراك','Renew subscription') }}</div>
          <div class="field"><label>{{ tt('عدد الأشهر','Months') }}</label><input v-model.number="renew.months" type="number" min="1" max="24" /></div>
          <div class="field"><label>{{ tt('الخطة','Plan') }}</label><select v-model="renew.plan_id"><option value="">{{ tt('الخطة الحالية','Current plan') }}</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ tt(p.name_ar, p.name_en || p.name_ar) }} ({{ p.price_monthly }} {{ tt('ريال','SAR') }})</option></select></div>
          <div class="field"><label>{{ tt('المبلغ (اتركه فارغاً للحساب التلقائي)','Amount (leave blank to auto-calc)') }}</label><input v-model="renew.amount" type="number" step="0.001" dir="ltr" /></div>
          <div class="field"><label>{{ tt('ملاحظة','Note') }}</label><input v-model="renew.note" /></div>
          <button class="btn ok" @click="doRenew"><i class="ti ti-check" /> {{ tt('تسجيل الدفع والتجديد','Record payment & renew') }}</button>
        </div>
        <div>
          <div class="card-title"><i class="ti ti-history" /> {{ tt('سجل الفترات','Period history') }}</div>
          <table class="table">
            <thead><tr><th>{{ tt('من','From') }}</th><th>{{ tt('إلى','To') }}</th><th>{{ tt('الخطة','Plan') }}</th><th>{{ tt('المبلغ','Amount') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
            <tbody>
              <tr v-for="s in d.tenant.subscriptions" :key="s.id">
                <td class="num">{{ s.period_start }}</td><td class="num">{{ s.period_end }}</td><td>{{ s.plan ? tt(s.plan.name_ar, s.plan.name_en || s.plan.name_ar) : '—' }}</td><td class="num">{{ s.amount }}</td>
                <td><span class="badge" :class="s.status === 'paid' ? 'ok' : 'warn'">{{ s.status === 'paid' ? tt('مدفوع','Paid') : s.status }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!d.tenant.subscriptions?.length" class="empty">{{ tt('لا يوجد سجل بعد.','No history yet.') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
