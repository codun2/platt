/** Inventory item form helpers (shared by new + edit pages). */
export const blankItem = () => ({
  name: '', name_en: '', sku: '', barcode: '', brand: '', model: '', description: '',
  category_id: '', unit_id: '', supplier_id: '',
  cost_price: 0, sale_price: 0,
  track_stock: true, min_stock: 0, reorder_qty: '', max_stock: '', location: '', weight: '',
  opening_qty: 0, is_active: true, notes: '',
})

export const itemToForm = (i: any) => {
  const f: any = blankItem()
  for (const k of Object.keys(f)) f[k] = i[k] ?? (typeof f[k] === 'boolean' ? false : '')
  f.track_stock = !!i.track_stock; f.is_active = !!i.is_active
  f.cost_price = +i.cost_price || 0; f.sale_price = +i.sale_price || 0; f.min_stock = +i.min_stock || 0
  f.category_id = i.category_id || ''; f.unit_id = i.unit_id || ''; f.supplier_id = i.supplier_id || ''
  f.opening_qty = 0
  return f
}

export const itemPayload = (form: any) => ({
  ...form,
  category_id: form.category_id || null, unit_id: form.unit_id || null, supplier_id: form.supplier_id || null,
  reorder_qty: form.reorder_qty === '' ? null : +form.reorder_qty,
  max_stock: form.max_stock === '' ? null : +form.max_stock,
  weight: form.weight === '' ? null : +form.weight,
  cost_price: +form.cost_price || 0, sale_price: +form.sale_price || 0, min_stock: +form.min_stock || 0, opening_qty: +form.opening_qty || 0,
})

export const movementLabel = (t: string) => (tt(({ opening: 'رصيد افتتاحي', purchase: 'شراء', purchase_cancel: 'إلغاء شراء', sale: 'بيع', sale_cancel: 'إلغاء بيع', adjustment: 'جرد/تسوية', transfer_in: 'تحويل وارد', transfer_out: 'تحويل صادر' } as Record<string, string>)[t], ({ opening: 'Opening', purchase: 'Purchase', purchase_cancel: 'Purchase cancel', sale: 'Sale', sale_cancel: 'Sale cancel', adjustment: 'Adjustment', transfer_in: 'Transfer in', transfer_out: 'Transfer out' } as Record<string, string>)[t]) || t)
