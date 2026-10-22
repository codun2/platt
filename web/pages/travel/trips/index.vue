<script setup lang="ts">
definePageMeta({ title: 'الرحلات', titleEn: 'Trips' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null)
const filt = reactive({ q: '', type: '', status: '' })
const typeLabel: any = { umrah: 'عمرة', hajj: 'حج', tourism: 'سياحة' }
const stLabel: any = { scheduled: 'مجدولة', ongoing: 'جارية', completed: 'مكتملة', cancelled: 'ملغاة' }
const stClass: any = { scheduled: 'info', ongoing: 'ok', completed: 'muted', cancelled: 'bad' }
const canManage = computed(() => auth.can('travel.manage'))

async function load() { page.value = await api.get('/app/travel/trips', filt) }
onMounted(load)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/travel">الحج والعمرة والسفر</NuxtLink> › <b>الرحلات</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <UiSelect v-model="filt.type" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">جميع الأنواع</option><option v-for="(l, k) in typeLabel" :key="k" :value="k">{{ l }}</option></UiSelect>
          <UiSelect v-model="filt.status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">جميع الحالات</option><option v-for="(l, k) in stLabel" :key="k" :value="k">{{ l }}</option></UiSelect>
          <input v-model="filt.q" placeholder="البحث بالاسم أو الوجهة..." style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" /><button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/travel/trips/new"><i class="ti ti-plus" /> إضافة رحلة</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>#</th><th>الاسم</th><th>النوع</th><th>الحالة</th><th>المغادرة</th><th>الإرجاع</th><th>الوجهة</th><th>الطاقة</th><th>المسافرون</th><th>الإيراد</th><th>المحصّل</th><th>المصروفات</th><th></th></tr></thead>
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
      <p v-if="page && !page.data.length" class="empty">لا توجد رحلات.</p>
    </div>
  </div>
</template>
