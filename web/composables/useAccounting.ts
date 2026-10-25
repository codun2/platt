/** Accounting UI helpers: labels, form shapes, payloads. */
export const accountTypes: Record<string, { label: string; cls: string }> = {
  asset: L('أصول', 'Assets', { cls: 'info' }), liability: L('التزامات', 'Liabilities', { cls: 'warn' }), equity: L('حقوق ملكية', 'Equity', { cls: 'muted' }),
  revenue: L('إيرادات', 'Revenue', { cls: 'ok' }), expense: L('مصروفات', 'Expenses', { cls: 'bad' }),
}
export const journalSources: Record<string, any> = {
  manual: L('يدوي', 'Manual'), invoice: L('فاتورة مبيعات', 'Sales invoice'), invoice_payment: L('تحصيل فاتورة', 'Invoice payment'), sale: L('نقطة بيع', 'POS sale'), sale_payment: L('تحصيل نقطة بيع', 'POS payment'),
  expense: L('مصروف', 'Expense'), booking_payment: L('حجز', 'Booking'), rental_payment: L('تحصيل إيجار', 'Rent payment'), payroll: L('رواتب', 'Payroll'), reversal: L('قيد عكسي', 'Reversal'),
}
export const sourceLink = (type: string | null, id: number | null, contractId?: number | null) => {
  if (type === 'rental_payment') return contractId ? `/rental/contracts/${contractId}` : null
  if (!id) return null
  if (type === 'invoice') return `/crm/invoices/${id}`
  if (type === 'expense') return `/accounting/expenses/${id}`
  if (type === 'sale') return `/pos/sales?open=${id}`
  if (type === 'reversal') return `/accounting/journal/${id}`
  return null
}
export const expenseMethods: Record<string, any> = { cash: L('نقدي', 'Cash'), card: L('بطاقة', 'Card'), transfer: L('تحويل بنكي', 'Bank transfer'), cheque: L('شيك', 'Cheque') }

export const monthStart = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01` }
export const todayStr = () => ymd()

/* ---- expenses ---- */
export const blankExpense = () => ({
  date: todayStr(), account_id: '', paid_from_account_id: '', supplier_id: '', payee: '',
  amount: 0, tax: 0, payment_method: 'cash', reference: '', description: '', notes: '',
})
export const expenseToForm = (e: any) => ({
  ...blankExpense(), date: e.date, account_id: e.account_id, paid_from_account_id: e.paid_from_account_id, supplier_id: e.supplier_id || '',
  payee: e.payee || '', amount: +e.amount, tax: +e.tax || 0, payment_method: e.payment_method || 'cash', reference: e.reference || '', description: e.description || '', notes: e.notes || '',
})
export const expensePayload = (f: any) => ({ ...f, supplier_id: f.supplier_id || null, amount: +f.amount || 0, tax: +f.tax || 0 })

/* ---- journal ---- */
/** A line is one side only: `side` drives the form, debit/credit is what gets saved. */
export const blankJournalLine = (side: 'debit' | 'credit' = 'debit') => ({ account_id: '', side, debit: 0, credit: 0, description: '' })
export const blankJournal = () => ({ date: todayStr(), reference: '', description: '', status: 'posted', lines: [blankJournalLine('debit'), blankJournalLine('credit')] })
export const journalToForm = (e: any) => ({
  date: e.date, reference: e.reference || '', description: e.description || '', status: e.status || 'posted',
  lines: (e.lines || []).map((l: any) => ({ account_id: l.account_id, side: +l.credit > 0 ? 'credit' : 'debit', debit: +l.debit || 0, credit: +l.credit || 0, description: l.description || '' })),
})
export const journalPayload = (f: any) => ({ ...f, lines: f.lines.filter((l: any) => l.account_id && ((+l.debit || 0) > 0 || (+l.credit || 0) > 0)).map((l: any) => ({ account_id: l.account_id, debit: +l.debit || 0, credit: +l.credit || 0, description: l.description || null })) })
export const journalTotals = (lines: any[]) => {
  const r3 = (n: number) => Math.round(n * 1000) / 1000
  const debit = r3(lines.reduce((s, l) => s + (+l.debit || 0), 0)); const credit = r3(lines.reduce((s, l) => s + (+l.credit || 0), 0))
  return { debit, credit, diff: r3(debit - credit), balanced: Math.abs(debit - credit) < 0.0005 && debit > 0 }
}
