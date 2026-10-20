<script setup lang="ts">
definePageMeta({ layout: 'super', title: 'المشتركون' })
const api = useApi()
const page = ref<any>(null)
const q = ref(''); const status = ref('')
const modal = ref(false); const err = ref('')
const sectors = ref<any[]>([])
const form = reactive({ company_name: '', sector: '', name: '', email: '', phone: '', password: '' })
const statusLabel: any = { trial: 'تجريبي', active: 'فعّال', expired: 'منتهٍ', suspended: 'موقوف' }
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
          <input v-model="q" placeholder="بحث..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <select v-model="status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load">
            <option value="">كل الحالات</option><option value="trial">تجريبي</option><option value="active">فعّال</option><option value="expired">منتهٍ</option><option value="suspended">موقوف</option>
          </select>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <button class="btn" @click="modal = true; err = ''"><i class="ti ti-plus" /> مشترك جديد</button>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الشركة</th><th>القطاع</th><th>الخطة</th><th>المستخدمون</th><th>الاشتراك</th><th>التجديد</th><th>الحالة</th></tr></thead>
        <tbody>
          <tr v-for="t in page?.data" :key="t.id">
            <td>{{ t.id }}</td>
            <td><NuxtLink :to="`/super/tenants/${t.id}`" style="color:var(--info);font-weight:700">{{ t.name }}</NuxtLink><br /><small class="muted" dir="ltr">{{ t.email }}</small></td>
            <td><i :class="`ti ${t.sector_preset?.icon}`" /> {{ t.sector_preset?.name_ar || '—' }}</td>
            <td>{{ t.plan?.name_ar || '—' }}</td>
            <td>{{ t.users_count }}</td>
            <td><span class="badge" :class="statusClass[t.subscription_status]">{{ statusLabel[t.subscription_status] }}</span></td>
            <td class="num">{{ t.subscription_renews_at || '—' }}</td>
            <td><span class="badge" :class="t.status === 'active' ? 'ok' : 'bad'">{{ t.status === 'active' ? 'نشط' : 'موقوف' }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا يوجد مشتركون.</p>
      <p class="muted mt" style="font-size:12px">الإجمالي: {{ page?.total ?? 0 }}</p>
    </div>

    <UiModal v-if="modal" title="إنشاء مشترك جديد" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="field"><label>اسم الشركة</label><input v-model="form.company_name" /></div>
      <div class="field"><label>القطاع</label><select v-model="form.sector"><option v-for="s in sectors" :key="s.key" :value="s.key">{{ s.name_ar }}</option></select></div>
      <div class="field"><label>اسم المالك</label><input v-model="form.name" /></div>
      <div class="field"><label>البريد</label><input v-model="form.email" type="email" dir="ltr" /></div>
      <div class="field"><label>الهاتف</label><input v-model="form.phone" dir="ltr" /></div>
      <div class="field"><label>كلمة المرور</label><input v-model="form.password" type="password" dir="ltr" /></div>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">إنشاء</button></template>
    </UiModal>
  </div>
</template>
