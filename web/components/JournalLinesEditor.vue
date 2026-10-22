<script setup lang="ts">
/** Debit/credit lines for a manual journal entry. Mutates `lines` in place. */
const props = defineProps<{ lines: any[]; accounts: any[] }>()
const f = useFmt()
const postable = computed(() => props.accounts.filter((a: any) => !a.is_group && a.is_active))
const totals = computed(() => journalTotals(props.lines))
function add() { props.lines.push(blankJournalLine()) }
function remove(i: number) { props.lines.splice(i, 1); while (props.lines.length < 2) add() }
// Typing on one side clears the other so a line is never both debit and credit.
function onDebit(l: any) { if (+l.debit > 0) l.credit = 0 }
function onCredit(l: any) { if (+l.credit > 0) l.debit = 0 }
</script>

<template>
  <div class="lines-editor">
    <table class="table">
      <thead><tr><th style="width:36px">#</th><th>الحساب</th><th style="width:140px">مدين</th><th style="width:140px">دائن</th><th>البيان</th><th style="width:48px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in lines" :key="i">
          <td class="muted">{{ i + 1 }}</td>
          <td><UiSelect v-model="l.account_id" placeholder="اختر الحساب"><option value="">—</option><option v-for="a in postable" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></td>
          <td><input v-model.number="l.debit" type="number" step="0.001" min="0" dir="ltr" @input="onDebit(l)" /></td>
          <td><input v-model.number="l.credit" type="number" step="0.001" min="0" dir="ltr" @input="onCredit(l)" /></td>
          <td><input v-model="l.description" placeholder="اختياري" /></td>
          <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-x" /></button></td>
        </tr>
      </tbody>
      <tfoot><tr><td></td><td style="text-align:left"><b>الإجمالي</b></td><td class="num"><b>{{ f.money(totals.debit) }}</b></td><td class="num"><b>{{ f.money(totals.credit) }}</b></td><td colspan="2"><span class="badge" :class="totals.balanced ? 'ok' : 'bad'">{{ totals.balanced ? 'القيد متوازن ✔' : (totals.debit === 0 && totals.credit === 0 ? 'أدخل المبالغ' : `فرق ${f.money(Math.abs(totals.diff))}`) }}</span></td></tr></tfoot>
    </table>
    <button type="button" class="btn ghost sm mt" @click="add"><i class="ti ti-plus" /> إضافة سطر</button>
  </div>
</template>
