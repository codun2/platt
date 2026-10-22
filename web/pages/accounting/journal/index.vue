<script setup lang="ts">
definePageMeta({ title: 'القيود اليومية', titleEn: 'Journal' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const pg = ref(1)
const filt = reactive({ q: '', source_type: '', from: '', to: '' })
const canManage = computed(() => auth.can('accounting.manage'))
async function load(p = 1) { pg.value = p; page.value = await api.get('/app/accounting/journal', { ...filt, page: p }) }
onMounted(load)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting">المحاسبة</NuxtLink> › <b>القيود اليومية</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-book" /></div><div><b>{{ page?.stats?.count ?? 0 }}</b><small>إجمالي القيود</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-calendar" /></div><div><b>{{ page?.stats?.month_count ?? 0 }}</b><small>قيود هذا الشهر</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(page?.stats?.month_total) }}</b><small>حركة هذا الشهر</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-pencil" /></div><div><b>{{ page?.stats?.manual_count ?? 0 }}</b><small>قيود يدوية</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="رقم / بيان / مرجع" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" @keyup.enter="load()" />
          <UiSelect v-model="filt.source_type" style="min-width:150px" @change="load()"><option value="">كل المصادر</option><option v-for="(l, k) in journalSources" :key="k" :value="k">{{ l }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/accounting/journal/new"><i class="ti ti-plus" /> قيد يدوي</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>البيان</th><th>المرجع</th><th>المصدر</th><th>الأسطر</th><th>المبلغ</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="e in page?.data" :key="e.id">
            <td><NuxtLink :to="`/accounting/journal/${e.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ e.number }}</NuxtLink></td>
            <td>{{ f.d(e.date) }}</td><td>{{ e.description || '—' }}</td><td dir="ltr" style="text-align:right">{{ e.reference || '' }}</td>
            <td><span class="badge" :class="e.source_type === 'reversal' ? 'warn' : (!e.source_type || e.source_type === 'manual') ? 'info' : 'muted'">{{ journalSources[e.source_type || 'manual'] }}</span></td>
            <td>{{ e.lines_count }}</td><td class="num"><b>{{ f.money(e.total) }}</b></td>
            <td><span class="badge" :class="e.status === 'posted' ? 'ok' : 'muted'">{{ e.status === 'posted' ? 'مرحَّل' : 'مسودّة' }}</span></td>
            <td><NuxtLink class="btn ghost sm icon" :to="`/accounting/journal/${e.id}`"><i class="ti ti-eye" /></NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد قيود.</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">صفحة {{ page.current_page }} من {{ page.last_page }} · {{ page.total }} قيد</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">السابق</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">التالي</button></div>
      </div>
    </div>
  </div>
</template>
