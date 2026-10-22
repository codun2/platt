<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'المشتركون', titleEn: 'Tenants' })
const api = useApi()
const { tt, L } = useI18n()
const page = ref<any>(null)
const q = ref(''); const status = ref('')
const modal = ref(false); const err = ref('')
const sectors = ref<any[]>([])
const form = reactive({ company_name: '', sector: '', name: '', email: '', phone: '', password: '' })
const statusLabel: any = { trial: L('تجريبي','Trial'), active: L('فعّال','Active'), expired: L('منتهٍ','Expired'), suspended: L('موقوف','Suspended') }
const statusClass: any = { trial: 'warn', active: 'ok', expired: 'bad', suspended: 'muted' }

async function load() { page.value = await api.get('/super/tenants', { q: q.value, status: status.value }) }
onMounted(async () => { await load(); sectors.value = await api.get('/public/sectors') })

async function save() {
  err.value = ''
  try { await api.post('/super/tenants', form); modal.value = false; await load() } catch (e: any) { err.value = e.message }
}
</script>

<template>
  <div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" :placeholder="tt('بحث...','Search...')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <select v-model="status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load">
            <option value="">{{ tt('كل الحالات','All statuses') }}</option><option value="trial">{{ tt('تجريبي','Trial') }}</option><option value="active">{{ tt('فعّال','Active') }}</option><option value="expired">{{ tt('منتهٍ','Expired') }}</option><option value="suspended">{{ tt('موقوف','Suspended') }}</option>
          </select>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <button class="btn" @click="modal = true; err = ''"><i class="ti ti-plus" /> {{ tt('مشترك جديد','New tenant') }}</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الشركة','Company') }}</th><th>{{ tt('القطاع','Sector') }}</th><th>{{ tt('الخطة','Plan') }}</th><th>{{ tt('المستخدمون','Users') }}</th><th>{{ tt('الاشتراك','Subscription') }}</th><th>{{ tt('التجديد','Renewal') }}</th><th>{{ tt('الحالة','Status') }}</th></tr></thead>
        <tbody>
          <tr v-for="t in page?.data" :key="t.id">
            <td>{{ t.id }}</td>
            <td><NuxtLink :to="`/super/tenants/${t.id}`" style="color:var(--info);font-weight:700">{{ t.name }}</NuxtLink><br /><small class="muted" dir="ltr">{{ t.email }}</small></td>
            <td><i :class="`ti ${t.sector_preset?.icon}`" /> {{ t.sector_preset ? tt(t.sector_preset.name_ar, t.sector_preset.name_en || t.sector_preset.name_ar) : '—' }}</td>
            <td>{{ t.plan ? tt(t.plan.name_ar, t.plan.name_en || t.plan.name_ar) : '—' }}</td>
            <td>{{ t.users_count }}</td>
            <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            <td class="num">{{ t.subscription_renews_at || '—' }}</td>
            <td><span class="badge" :class="t.status === 'active' ? 'ok' : 'bad'">{{ t.status === 'active' ? tt('نشط','Active') : tt('موقوف','Suspended') }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا يوجد مشتركون.','No tenants.') }}</p>
      <p class="muted mt" style="font-size:12px">{{ tt('الإجمالي','Total') }}: {{ page?.total ?? 0 }}</p>
    </div>

    <UiModal v-if="modal" :title="tt('إنشاء مشترك جديد','Create new tenant')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>{{ tt('اسم الشركة','Company name') }}</label><input v-model="form.company_name" /></div>
      <div class="field"><label>{{ tt('القطاع','Sector') }}</label><select v-model="form.sector"><option v-for="s in sectors" :key="s.key" :value="s.key">{{ tt(s.name_ar, s.name_en || s.name_ar) }}</option></select></div>
      <div class="field"><label>{{ tt('اسم المالك','Owner name') }}</label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('البريد','Email') }}</label><input v-model="form.email" type="email" dir="ltr" /></div>
      <div class="field"><label>{{ tt('الهاتف','Phone') }}</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>{{ tt('كلمة المرور','Password') }}</label><input v-model="form.password" type="password" dir="ltr" /></div>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('إنشاء','Create') }}</button></template>
    </UiModal>
  </div>
</template>
