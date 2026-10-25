/** Purchase (supplier invoice) form helpers. */
export const purchaseStatus: Record<string, { label: string; cls: string }> = {
  draft: L('مسودّة', 'Draft', { cls: 'warn' }), received: L('مستلمة', 'Received', { cls: 'ok' }), cancelled: L('ملغاة', 'Cancelled', { cls: 'bad' }),
}
export const purchasePayMethods: Record<string, any> = { cash: L('نقدي', 'Cash'), card: L('بطاقة', 'Card'), transfer: L('تحويل بنكي', 'Bank transfer'), cheque: L('شيك', 'Cheque'), credit: L('آجل', 'Credit') }

export const blankPurchase = (warehouseId: any = '') => ({
  supplier_id: '', warehouse_id: warehouseId, date: ymd(), due_date: '',
  supplier_invoice: '', reference: '', payment_method: 'cash',
  discount: 0, tax: 0, paid: 0, note: '', receive: true,
  lines: [] as any[],
})

export const purchaseToForm = (p: any) => ({
  ...blankPurchase(p.warehouse_id),
  supplier_id: p.supplier_id || '', date: p.date, due_date: p.due_date || '',
  supplier_invoice: p.supplier_invoice || '', reference: p.reference || '', payment_method: p.payment_method || 'cash',
  discount: +p.discount || 0, tax: +p.tax || 0, paid: +p.paid || 0, note: p.note || '', receive: false,
  lines: (p.items || []).map((l: any) => ({ item_id: l.item_id, name: l.item?.name || '', sku: l.item?.sku || '', unit: l.item?.unit?.symbol || '', qty: +l.qty, unit_cost: +l.unit_cost, track_expiry: !!l.item?.track_expiry, batch_no: l.batch_no || '', expires_on: l.expires_on || '' })),
})

export const purchasePayload = (form: any) => ({
  ...form,
  supplier_id: form.supplier_id || null, warehouse_id: form.warehouse_id || null, due_date: form.due_date || null,
  discount: +form.discount || 0, tax: +form.tax || 0, paid: +form.paid || 0,
  lines: form.lines.map((l: any) => ({ item_id: l.item_id, qty: +l.qty, unit_cost: +l.unit_cost, batch_no: l.batch_no || null, expires_on: l.expires_on || null })),
})

export const calcPurchase = (form: any) => {
  const r3 = (n: number) => Math.round(n * 1000) / 1000
  const subtotal = r3(form.lines.reduce((s: number, l: any) => s + (+l.qty || 0) * (+l.unit_cost || 0), 0))
  const total = r3(Math.max(0, subtotal - (+form.discount || 0) + (+form.tax || 0)))
  return { subtotal, total, balance: r3(Math.max(0, total - (+form.paid || 0))) }
}
