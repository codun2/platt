<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'ملف المشترك' })
const api = useApi()
const route = useRoute()
const id = route.params.id
const d = ref<any>(null)
const plans = ref<any[]>([]); const presets = ref<any[]>([])
const tab = ref<'info' | 'modules' | 'subs'>('info')
const msg = ref(''); const err = ref('')
const form = reactive<any>({})
const renew = reactive({ months: 1, plan_id: '', amount: '', note: '' })
const newPass = ref('')
const statusLabel: any = { trial: 'تجريبي', active: 'فعّال', expired: 'منتهٍ', suspended: 'موقوف' }

async function load() {
  d.value = await api.get(`/super/tenants/${id}`)
  const t = d.value.tenant
  Object.assign(form, { name: t.name, phone: t.phone || '', email: t.email || '', plan_id: t.plan_id || '', sector_preset_id: t.sector_preset_id || '', status: t.status, subscription_status: t.subscription_status, subscription_renews_at: t.subscription_renews_at || '', trial_ends_at: t.trial_ends_at || '', notes: t.notes || '' })
  renew.plan_id = t.plan_id || ''
}
onMounted(async () => { await load(); plans.value = await api.get('/super/plans'); presets.value = await api.get('/super/presets') })

async function act(fn: () => Promise<any>, ok = 'تم الحفظ') {
  msg.value = ''; err.value = ''
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message }
}
const save = () => act(() => api.patch(`/super/tenants/${id}`, { ...form, plan_id: form.plan_id || null, sector_preset_id: form.sector_preset_id || null, subscription_renews_at: form.subscription_renews_at || null, trial_ends_at: form.trial_ends_at || null }))
const toggleModule = (m: any) => act(() => api.post(`/super/tenants/${id}/modules`, { module_id: m.id, enabled: !m.enabled }), 'تم تحديث الوحدة')
const applyPreset = (key: string) => act(() => api.post(`/super/tenants/${id}/apply-preset`, { preset_key: key }), 'تم تطبيق الباقة')
const doRenew = () => act(() => api.post(`/super/tenants/${id}/renew`, { ...renew, plan_id: renew.plan_id || null, amount: renew.amount === '' ? null : renew.amount }), 'تم تسجيل التجديد')
const resetPass = () => act(() => api.post(`/super/tenants/${id}/reset-owner-password`, { password: newPass.value }), 'تم تغيير كلمة المرور')
</script>

<template>
  <div v-if="d">
    <div class="breadcrumb"><NuxtLink to="/super/tenants">المشتركون</NuxtLink> › <b>{{ d.tenant.name }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-users" /></div><div><b>{{ d.tenant.users_count }}</b><small>المستخدمون</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-apps" /></div><div><b>{{ d.modules.filter((m: any) => m.enabled).length }}</b><small>وحدات مفعّلة</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-credit-card" /></div><div><b style="font-size:16px">{{ statusLabel[d.tenant.subscription_status] }}</b><small>{{ d.tenant.plan?.name_ar || 'بدون خطة' }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-calendar-event" /></div><div><b style="font-size:16px">{{ d.tenant.subscription_renews_at || '—' }}</b><small>تاريخ التجديد</small></div></div>
    </div>

    <div class="card">
      <div class="tabs">
        <button :class="{ on: tab === 'info' }" @click="tab = 'info'">البيانات</button>
        <button :class="{ on: tab === 'modules' }" @click="tab = 'modules'">الوحدات</button>
        <button :class="{ on: tab === 'subs' }" @click="tab = 'subs'">الاشتراك</button>
      </div>

      <div v-if="tab === 'info'" class="grid grid-2">
        <div>
          <div class="field"><label>اسم الشركة</label><input v-model="form.name" /></div>
          <div class="field"><label>البريد</label><input v-model="form.email" dir="ltr" /></div>
          <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
          <div class="field"><label>القطاع</label><select v-model="form.sector_preset_id"><option value="">—</option><option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name_ar }}</option></select></div>
          <div class="field"><label>الخطة</label><select v-model="form.plan_id"><option value="">—</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name_ar }} ({{ p.price_monthly }} ريال/شهر)</option></select></div>
        </div>
        <div>
          <div class="field"><label>حالة الحساب</label><select v-model="form.status"><option value="active">نشط</option><option value="suspended">موقوف</option></select></div>
          <div class="field"><label>حالة الاشتراك</label><select v-model="form.subscription_status"><option value="trial">تجريبي</option><option value="active">فعّال</option><option value="expired">منتهٍ</option><option value="suspended">موقوف</option></select></div>
          <div class="field"><label>تاريخ التجديد</label><input v-model="form.subscription_renews_at" type="date" /></div>
          <div class="field"><label>نهاية التجربة</label><input v-model="form.trial_ends_at" type="date" /></div>
          <div class="field"><label>ملاحظات</label><textarea v-model="form.notes" rows="3" /></div>
          <button class="btn" @click="save">حفظ</button>
          <div class="mt" style="border-top:1px solid var(--line);padding-top:12px">
            <small class="muted">المالك: <b>{{ d.owner?.name }}</b> · <span dir="ltr">{{ d.owner?.email }}</span></small>
            <div class="row mt"><input v-model="newPass" placeholder="كلمة مرور جديدة للمالك" dir="ltr" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" /><button class="btn ghost sm" @click="resetPass">تغيير</button></div>
          </div>
        </div>
      </div>

      <div v-if="tab === 'modules'">
        <div class="row between" style="margin-bottom:12px">
          <p class="muted" style="margin:0">فعّل/عطّل الوحدات لهذا المشترك. الوحدات الأساسية مفعّلة دائماً.</p>
          <div class="row"><span class="muted">تطبيق باقة:</span><select style="padding:6px 10px;border:1px solid var(--line);border-radius:8px" @change="applyPreset(($event.target as HTMLSelectElement).value)"><option value="">اختر...</option><option v-for="p in presets" :key="p.key" :value="p.key">{{ p.name_ar }}</option></select></div>
        </div>
        <table class="table">
          <thead><tr><th>الوحدة</th><th>المحرّك</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
            <tr v-for="m in d.modules" :key="m.id">
              <td><i :class="`ti ${m.icon}`" /> {{ m.name_ar }}</td>
              <td class="muted">{{ m.engine }}</td>
              <td><span class="badge" :class="m.enabled ? 'ok' : 'muted'">{{ m.is_core ? 'أساسية' : (m.enabled ? 'مفعّلة' : 'معطّلة') }}</span></td>
              <td><label v-if="!m.is_core" class="switch"><input type="checkbox" :checked="m.enabled" @change="toggleModule(m)" /><span /></label></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="tab === 'subs'" class="grid grid-2">
        <div>
          <div class="card-title"><i class="ti ti-refresh" /> تجديد الاشتراك</div>
          <div class="field"><label>عدد الأشهر</label><input v-model.number="renew.months" type="number" min="1" max="24" /></div>
          <div class="field"><label>الخطة</label><select v-model="renew.plan_id"><option value="">الخطة الحالية</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name_ar }} ({{ p.price_monthly }} ريال)</option></select></div>
          <div class="field"><label>المبلغ (اتركه فارغاً للحساب التلقائي)</label><input v-model="renew.amount" type="number" step="0.001" dir="ltr" /></div>
          <div class="field"><label>ملاحظة</label><input v-model="renew.note" /></div>
          <button class="btn ok" @click="doRenew"><i class="ti ti-check" /> تسجيل الدفع والتجديد</button>
        </div>
        <div>
          <div class="card-title"><i class="ti ti-history" /> سجل الفترات</div>
          <table class="table">
            <thead><tr><th>من</th><th>إلى</th><th>الخطة</th><th>المبلغ</th><th>الحالة</th></tr></thead>
            <tbody>
              <tr v-for="s in d.tenant.subscriptions" :key="s.id">
                <td class="num">{{ s.period_start }}</td><td class="num">{{ s.period_end }}</td><td>{{ s.plan?.name_ar || '—' }}</td><td class="num">{{ s.amount }}</td>
                <td><span class="badge" :class="s.status === 'paid' ? 'ok' : 'warn'">{{ s.status === 'paid' ? 'مدفوع' : s.status }}</span></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!d.tenant.subscriptions?.length" class="empty">لا يوجد سجل بعد.</p>
        </div>
      </div>
    </div>
  </div>
</template>
