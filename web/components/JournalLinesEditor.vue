<script setup lang="ts">
/**
 * Lines of a manual journal entry. Each line is ONE side (debit or credit) with one
 * amount — the side switch decides which column it is saved in. Mutates `lines`.
 */
const props = defineProps<{ lines: any[]; accounts: any[] }>()
const f = useFmt(); const { tt } = useI18n()
const postable = computed(() => props.accounts.filter((a: any) => !a.is_group && a.is_active))
const totals = computed(() => journalTotals(props.lines))
const amount = (l: any) => l.side === 'credit' ? +l.credit || 0 : +l.debit || 0
function setAmount(l: any, v: any) { const n = Math.max(0, +v || 0); if (l.side === 'credit') { l.credit = n; l.debit = 0 } else { l.debit = n; l.credit = 0 } }
function setSide(l: any, side: 'debit' | 'credit') { const n = amount(l); l.side = side; setAmount(l, n) }
function add(side?: 'debit' | 'credit') { props.lines.push(blankJournalLine(side || (totals.value.diff > 0 ? 'credit' : 'debit'))) }
function remove(i: number) { props.lines.splice(i, 1); while (props.lines.length < 2) add() }
/** Put the difference on the last line that has no amount, on the side that balances the entry. */
function balance() {
  const diff = totals.value.diff; if (!diff) return
  let l = [...props.lines].reverse().find(x => !amount(x))
  if (!l) { add(); l = props.lines[props.lines.length - 1] }
  l.side = diff > 0 ? 'credit' : 'debit'; setAmount(l, Math.abs(diff))
}

// Ready-made entries for the things a shop owner actually records by hand.
const byKey = (k: string) => postable.value.find((a: any) => a.system_key === k)?.id || ''
const templates = computed(() => [
  { label: tt('إيداع رأس مال', 'Capital deposit'), lines: [['debit', 'cash', 'رأس مال أودعه المالك'], ['credit', 'capital', '']] },
  { label: tt('من الصندوق إلى البنك', 'Cash → bank'), lines: [['debit', 'bank', 'إيداع في البنك'], ['credit', 'cash', '']] },
  { label: tt('من البنك إلى الصندوق', 'Bank → cash'), lines: [['debit', 'cash', 'سحب من البنك'], ['credit', 'bank', '']] },
  { label: tt('مسحوبات المالك', 'Owner drawings'), lines: [['debit', 'drawings', 'سحب شخصي للمالك'], ['credit', 'cash', '']] },
  { label: tt('إيراد آخر نقدي', 'Other cash income'), lines: [['debit', 'cash', ''], ['credit', 'other_income', 'إيراد آخر']] },
].filter(t => t.lines.every(([, k]) => byKey(k as string))))
function apply(t: any) {
  props.lines.splice(0, props.lines.length, ...t.lines.map(([side, key, desc]: any) => ({ ...blankJournalLine(side), account_id: byKey(key), description: desc })))
  nextTick(() => (document.querySelector('.lines-editor input[type=number]') as HTMLInputElement)?.focus())
}
// Typing an amount on the first line of a two-line entry fills the second (the usual case).
watch(() => amount(props.lines[0]), v => { if (props.lines.length === 2 && !amount(props.lines[1]) && props.lines[1].side !== props.lines[0].side) setAmount(props.lines[1], v) })
</script>

<template>
  <div class="lines-editor">
    <div v-if="templates.length" class="row" style="gap:6px;flex-wrap:wrap;margin-bottom:10px"><span class="muted" style="font-size:13px">{{ tt('قوالب جاهزة:', 'Templates:') }}</span><span v-for="t in templates" :key="t.label" class="chip" @click="apply(t)">{{ t.label }}</span></div>
    <table class="table">
      <thead><tr><th style="width:36px">#</th><th>{{ tt('الحساب','Account') }}</th><th style="width:170px">{{ tt('الطرف','Side') }}</th><th style="width:150px">{{ tt('المبلغ','Amount') }}</th><th>{{ tt('البيان','Description') }}</th><th style="width:48px"></th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in lines" :key="i">
          <td class="muted">{{ i + 1 }}</td>
          <td><UiSelect v-model="l.account_id" :placeholder="tt('اختر الحساب','Select account')"><option value="">—</option><option v-for="a in postable" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></UiSelect></td>
          <td>
            <div class="side">
              <button type="button" :class="{ on: l.side !== 'credit', dr: true }" @click="setSide(l, 'debit')">{{ tt('مدين', 'Debit') }}</button>
              <button type="button" :class="{ on: l.side === 'credit', cr: true }" @click="setSide(l, 'credit')">{{ tt('دائن', 'Credit') }}</button>
            </div>
          </td>
          <td><input :value="amount(l) || ''" type="number" step="0.001" min="0" dir="ltr" placeholder="0" @input="setAmount(l, ($event.target as HTMLInputElement).value)" /></td>
          <td><input v-model="l.description" :placeholder="tt('اختياري','Optional')" /></td>
          <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(i)"><i class="ti ti-x" /></button></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td></td>
          <td style="text-align:left"><b>{{ tt('الإجمالي','Total') }}</b></td>
          <td colspan="2"><span class="dr-t">{{ tt('مدين', 'Debit') }} <b>{{ f.money(totals.debit) }}</b></span> · <span class="cr-t">{{ tt('دائن', 'Credit') }} <b>{{ f.money(totals.credit) }}</b></span></td>
          <td colspan="2" class="row" style="gap:8px">
            <span class="badge" :class="totals.balanced ? 'ok' : 'bad'">{{ totals.balanced ? tt('القيد متوازن ✔','Balanced ✔') : (totals.debit === 0 && totals.credit === 0 ? tt('أدخل المبالغ','Enter amounts') : tt(`فرق ${f.money(Math.abs(totals.diff))}`,`Diff ${f.money(Math.abs(totals.diff))}`)) }}</span>
            <button v-if="!totals.balanced && totals.diff" type="button" class="btn ghost sm" @click="balance"><i class="ti ti-scale" /> {{ tt('وازن القيد', 'Balance it') }}</button>
          </td>
        </tr>
      </tfoot>
    </table>
    <div class="row" style="gap:6px;margin-top:8px">
      <button type="button" class="btn ghost sm" @click="add('debit')"><i class="ti ti-plus" /> {{ tt('سطر مدين', 'Debit line') }}</button>
      <button type="button" class="btn ghost sm" @click="add('credit')"><i class="ti ti-plus" /> {{ tt('سطر دائن', 'Credit line') }}</button>
    </div>
  </div>
</template>

<style scoped>
.side { display: inline-flex; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.side button { border: 0; background: var(--card); padding: 7px 12px; font: inherit; font-size: 13px; cursor: pointer; color: var(--muted); }
.side button.on.dr { background: #dbeafe; color: #1d4ed8; font-weight: 700; }
.side button.on.cr { background: #fee2e2; color: #b91c1c; font-weight: 700; }
.dr-t { color: #1d4ed8; } .cr-t { color: #b91c1c; }
</style>
