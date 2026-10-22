<script setup lang="ts">
definePageMeta({ title: 'الورديات', titleEn: 'Shifts' })
const api = useApi(); const f = useFmt()
const page = ref<any>(null); const current = ref<any>(null); const view = ref<any>(null)
const openCash = ref(0); const closing = ref(0); const note = ref('')
async function load() { page.value = await api.get('/app/pos/shifts'); current.value = await api.get('/app/pos/shifts/current'); if (current.value) closing.value = Number(current.value.expected_cash) }
onMounted(load)
async function open() { try { await api.post('/app/pos/shifts/open', { opening_cash: openCash.value }); await load() } catch (e: any) { alert(e.message) } }
async function close() { if (!confirm('إغلاق الوردية؟')) return; try { await api.post(`/app/pos/shifts/${current.value.id}/close`, { closing_cash: closing.value, note: note.value }); await load() } catch (e: any) { alert(e.message) } }
async function show(s: any) { view.value = await api.get(`/app/pos/shifts/${s.id}`) }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/pos">نقطة البيع</NuxtLink> › <b>الورديات</b></div>
    <div class="card">
      <div v-if="current" class="grid grid-4">
        <div class="stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-clock-play" /></div><div><b>#{{ current.id }}</b><small>وردية مفتوحة منذ {{ f.dt(current.opened_at) }}</small></div></div>
        <div class="stat"><div class="ic" style="background:var(--info)"><i class="ti ti-receipt" /></div><div><b>{{ current.sales_count }}</b><small>فواتير · {{ f.money(current.sales_total) }}</small></div></div>
        <div class="stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-cash" /></div><div><b>{{ f.money(current.expected_cash) }}</b><small>النقد المتوقع بالدرج (افتتاحي {{ f.money(current.opening_cash) }})</small></div></div>
        <div><div class="field"><label>النقد الفعلي عند الإغلاق</label><input v-model.number="closing" type="number" step="0.001" dir="ltr" /></div><div class="field"><label>ملاحظة</label><input v-model="note" /></div><button class="btn" @click="close"><i class="ti ti-lock" /> إغلاق الوردية</button></div>
      </div>
      <div v-else class="row"><span class="muted">لا توجد وردية مفتوحة لك.</span><input v-model.number="openCash" type="number" step="0.001" placeholder="النقد الافتتاحي" dir="ltr" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;width:160px" /><button class="btn ok" @click="open"><i class="ti ti-clock-play" /> فتح وردية</button></div>
    </div>

    <div class="card mt">
      <div class="card-title"><i class="ti ti-history" /> سجل الورديات</div>
      <table class="table">
        <thead><tr><th>#</th><th>الكاشير</th><th>المستودع</th><th>فتح</th><th>إغلاق</th><th>فواتير</th><th>المبيعات</th><th>افتتاحي</th><th>متوقع</th><th>فعلي</th><th>الفرق</th><th>الحالة</th></tr></thead>
        <tbody>
          <tr v-for="s in page?.data" :key="s.id">
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(s)">#{{ s.id }}</a></td><td>{{ s.user?.name }}</td><td>{{ s.warehouse?.name }}</td><td>{{ f.dt(s.opened_at) }}</td><td>{{ f.dt(s.closed_at) }}</td><td>{{ s.sales_count }}</td>
            <td class="num">{{ f.money(s.sales_total) }}</td><td class="num">{{ f.money(s.opening_cash) }}</td><td class="num">{{ f.money(s.expected_cash) }}</td><td class="num">{{ s.closing_cash != null ? f.money(s.closing_cash) : '—' }}</td>
            <td class="num" :style="s.closing_cash != null && Math.abs(s.closing_cash - s.expected_cash) > 0.0005 ? 'color:var(--primary);font-weight:700' : ''">{{ s.closing_cash != null ? f.money(s.closing_cash - s.expected_cash) : '—' }}</td>
            <td><span class="badge" :class="s.status === 'open' ? 'ok' : 'muted'">{{ s.status === 'open' ? 'مفتوحة' : 'مغلقة' }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد ورديات.</p>
    </div>

    <UiModal v-if="view" :title="`وردية #${view.id} — ${view.user?.name}`" lg @close="view = null">
      <div class="chips" style="margin-bottom:12px"><span v-for="m in view.by_method" :key="m.payment_method" class="chip">{{ f.methodLabel(m.payment_method) }}: <b>{{ f.money(m.amount) }}</b> ({{ m.n }})</span></div>
      <table class="table"><thead><tr><th>الفاتورة</th><th>العميل</th><th>الوقت</th><th>الإجمالي</th><th>المدفوع</th><th>الدفع</th><th>الحالة</th></tr></thead>
        <tbody><tr v-for="s in view.sales" :key="s.id"><td><NuxtLink :to="`/pos/sales?open=${s.id}`" style="color:var(--info)">{{ s.number }}</NuxtLink></td><td>{{ s.customer?.name || 'نقدي' }}</td><td>{{ f.dt(s.created_at).slice(11) }}</td><td class="num">{{ f.money(s.total) }}</td><td class="num">{{ f.money(s.paid) }}</td><td>{{ f.methodLabel(s.payment_method) }}</td><td><span class="badge" :class="s.status === 'completed' ? 'ok' : 'bad'">{{ s.status === 'completed' ? 'مكتملة' : 'ملغاة' }}</span></td></tr></tbody></table>
    </UiModal>
  </div>
</template>
