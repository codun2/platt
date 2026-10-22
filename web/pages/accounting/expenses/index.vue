<script setup lang="ts">
definePageMeta({ title: 'المصروفات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const meta = ref<any>({ categories: [], money: [] }); const pg = ref(1)
const filt = reactive({ q: '', account_id: '', from: '', to: '' })
const canManage = computed(() => auth.can('accounting.manage'))
async function load(p = 1) { pg.value = p; page.value = await api.get('/app/accounting/expenses', { ...filt, page: p }) }
onMounted(async () => { await load(); meta.value = await api.get('/app/accounting/expenses/meta') })
async function remove(e: any) { if (!confirm(`حذف المصروف ${e.number}؟ سيُحذف قيده أيضاً.`)) return; try { await api.del(`/app/accounting/expenses/${e.id}`); await load(pg.value) } catch (x: any) { alert(x.message) } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting">المحاسبة</NuxtLink> › <b>المصروفات</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-receipt" /></div><div><b>{{ f.money(page?.stats?.month_total) }}</b><small>مصروفات هذا الشهر ({{ page?.stats?.month_count ?? 0 }})</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-coins" /></div><div><b>{{ f.money(page?.stats?.total) }}</b><small>إجمالي المصروفات ({{ page?.stats?.count ?? 0 }})</small></div></div>
      <div class="card stat" style="grid-column:span 2"><div class="ic" style="background:var(--info)"><i class="ti ti-chart-pie" /></div><div><b style="font-size:13px;white-space:normal">{{ (page?.stats?.by_category || []).map((c: any) => `${c.name} ${f.money(c.total)}`).join(' · ') || '—' }}</b><small>أعلى الأنواع هذا الشهر</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="filt.q" placeholder="رقم / مستفيد / وصف / مرجع" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:220px" @keyup.enter="load()" />
          <UiSelect v-model="filt.account_id" style="min-width:170px" @change="load()"><option value="">كل الأنواع</option><option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option></UiSelect>
          <input v-model="filt.from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <input v-model="filt.to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load()" />
          <button class="btn ghost" @click="load()"><i class="ti ti-search" /></button>
        </div>
        <NuxtLink v-if="canManage" class="btn" to="/accounting/expenses/new"><i class="ti ti-plus" /> مصروف جديد</NuxtLink>
      </div>
      <table class="table">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>النوع</th><th>المستفيد</th><th>الوصف</th><th>مدفوع من</th><th>المبلغ</th><th>الضريبة</th><th>الإجمالي</th><th></th></tr></thead>
        <tbody>
          <tr v-for="e in page?.data" :key="e.id">
            <td><NuxtLink :to="`/accounting/expenses/${e.id}`" style="color:var(--info);font-weight:700" dir="ltr">{{ e.number }}</NuxtLink></td>
            <td>{{ f.d(e.date) }}</td><td>{{ e.account?.name }}</td><td>{{ e.payee || e.supplier?.name || '—' }}</td><td class="muted">{{ e.description || '—' }}</td><td>{{ e.paid_from?.name }}</td>
            <td class="num">{{ f.money(e.amount) }}</td><td class="num">{{ f.money(e.tax) }}</td><td class="num"><b>{{ f.money(e.total) }}</b></td>
            <td class="row" style="gap:4px"><NuxtLink class="btn ghost sm icon" :to="`/accounting/expenses/${e.id}`"><i class="ti ti-eye" /></NuxtLink><template v-if="canManage"><NuxtLink class="btn ghost sm icon" :to="`/accounting/expenses/${e.id}?edit=1`"><i class="ti ti-pencil" /></NuxtLink><button class="btn ghost sm icon" style="color:var(--primary)" @click="remove(e)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد مصروفات.</p>
      <div v-if="page && page.last_page > 1" class="row between mt">
        <span class="muted" style="font-size:12px">صفحة {{ page.current_page }} من {{ page.last_page }} · {{ page.total }} مصروف</span>
        <div class="row"><button class="btn ghost sm" :disabled="pg <= 1" @click="load(pg - 1)">السابق</button><button class="btn ghost sm" :disabled="pg >= page.last_page" @click="load(pg + 1)">التالي</button></div>
      </div>
    </div>
  </div>
</template>
