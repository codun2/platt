<script setup lang="ts">
definePageMeta({ title: 'الرحلات', titleEn: 'Trips' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const page = ref<any>(null)
const filt = reactive({ q: '', type: '', status: '' })
const typeLabel: any = { umrah: L('عمرة','Umrah'), hajj: L('حج','Hajj'), tourism: L('سياحة','Tourism') }
const stLabel: any = { scheduled: L('مجدولة','Scheduled'), ongoing: L('جارية','Ongoing'), completed: L('مكتملة','Completed'), cancelled: L('ملغاة','Cancelled') }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const canManage = computed(() => auth.can('travel.manage'))

async function load() { page.value = await api.get('/app/travel/trips', filt) }
onMounted(load)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">{{ tt('الحج والعمرة والسفر','Hajj, Umrah & Travel') }}</NuxtLink> › <b>{{ tt('الرحلات','Trips') }}</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <UiSelect v-model="filt.type" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('جميع الأنواع','All types') }}</option><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></UiSelect>
          <UiSelect v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">{{ tt('جميع الحالات','All statuses') }}</option><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></UiSelect>
          <input v-model="filt.q" :placeholder="tt('البحث بالاسم أو الوجهة...','Search by name or destination...')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/travel/trips/new"><i class="ti ti-plus" /> {{ tt('إضافة رحلة','New trip') }}</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>{{ tt('الاسم','Name') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('الحالة','Status') }}</th><th>{{ tt('المغادرة','Departure') }}</th><th>{{ tt('الإرجاع','Return') }}</th><th>{{ tt('الوجهة','Destination') }}</th><th>{{ tt('الطاقة','Capacity') }}</th><th>{{ tt('المسافرون','Travelers') }}</th><th>{{ tt('الإيراد','Revenue') }}</th><th>{{ tt('المحصّل','Collected') }}</th><th>{{ tt('المصروفات','Expenses') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="(t, i) in page?.data" :key="t.id">
            <td>{{ i + 1 }}</td>
            <td><NuxtLink :to="`/travel/trips/${t.id}`" style="color:var(--info);font-weight:700">{{ t.name }}</NuxtLink></td>
            <td><span class="badge" :class="t.type === 'hajj' ? 'bad' : t.type === 'umrah' ? 'ok' : 'info'">{{ typeLabel[t.type] }}</span></td>
            <td><span class="badge" :class="stClass[t.status]">{{ stLabel[t.status] }}</span></td>
            <td>{{ f.d(t.departure_date) }}</td><td>{{ f.d(t.return_date) }}</td><td>{{ t.destination }}</td><td>{{ t.capacity }}</td>
            <td><span class="badge" :class="t.capacity && t.travelers_count >= t.capacity ? 'bad' : 'muted'">{{ t.travelers_count }}</span></td>
            <td class="num">{{ f.money(t.revenue) }}</td><td class="num" style="color:var(--ok)">{{ f.money(t.collected) }}</td><td class="num" style="color:var(--primary)">{{ f.money(t.expenses_total) }}</td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/travel/trips/${t.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">{{ tt('لا توجد رحلات.','No trips.') }}</p>
    </div>
  </div>
</template>
