<script setup lang="ts">
definePageMeta({ title: 'وصفة جديدة', titleEn: 'New Recipe' })
const api = useApi(); const auth = useAuth()
if (!auth.can('manufacturing.manage')) navigateTo('/manufacturing/bom')
const err = ref(''); const saving = ref(false); const items = ref<any[]>([])
const form = reactive<any>(blankBom()); if (!form.lines.length) form.lines.push({ item_id: '', qty: 1, waste_pct: 0 })
useDraft('bom-new', form)
onMounted(async () => { items.value = await api.get('/app/inventory/items', { all: 1, active_only: 1 }) })
async function save() { err.value = ''; saving.value = true; try { const b = await api.post('/app/manufacturing/bom', { ...form, lines: form.lines.filter((l: any) => l.item_id) }); if (!returnAfterCreate(b.id)) navigateTo(`/manufacturing/bom/${b.id}`) } catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { saving.value = false } }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/manufacturing/bom">{{ tt('الوصفات', 'Recipes') }}</NuxtLink> › <b>{{ tt('جديدة', 'New') }}</b></div>
    <div class="card">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <BomForm :form="form" :items="items" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px"><button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> {{ tt('حفظ', 'Save') }}</button><NuxtLink class="btn ghost" :to="cancelLink('/manufacturing/bom')">{{ tt('إلغاء', 'Cancel') }}</NuxtLink></div>
    </div>
  </div>
</template>
