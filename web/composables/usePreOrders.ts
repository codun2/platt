/** Add-on «الطلبات المسبقة والتوصيل»: labels, blank form, totals (mirrors OrderController::fill). */
export const preOrderStatus: Record<string, any> = {
  new: L('جديد', 'New', { cls: 'info', icon: 'ti-inbox', color: '#2563eb' }),
  confirmed: L('مؤكَّد', 'Confirmed', { cls: 'info', icon: 'ti-circle-check', color: '#0891b2' }),
  preparing: L('قيد التحضير', 'Preparing', { cls: 'warn', icon: 'ti-chef-hat', color: '#d97706' }),
  ready: L('جاهز', 'Ready', { cls: 'ok', icon: 'ti-package', color: '#16a34a' }),
  out: L('خرج للتوصيل', 'Out for delivery', { cls: 'ok', icon: 'ti-truck-delivery', color: '#7c3aed' }),
  delivered: L('تم التسليم', 'Delivered', { cls: 'muted', icon: 'ti-checks', color: '#64748b' }),
  cancelled: L('ملغى', 'Cancelled', { cls: 'bad', icon: 'ti-x', color: '#e11d48' }),
}
export const preOrderChannel: Record<string, any> = {
  shop: L('في المحل', 'In store', { icon: 'ti-building-store' }), phone: L('اتصال', 'Phone', { icon: 'ti-phone' }),
  whatsapp: L('واتساب', 'WhatsApp', { icon: 'ti-brand-whatsapp' }), online: L('طلب إلكتروني', 'Online', { icon: 'ti-world' }),
}
/** Next step on the board for an order (delivery orders go out before delivered). */
export const preOrderNext = (o: any): string | null => {
  const flow = o.fulfilment === 'delivery' ? ['new', 'confirmed', 'preparing', 'ready', 'out', 'delivered'] : ['new', 'confirmed', 'preparing', 'ready', 'delivered']
  const i = flow.indexOf(o.status)
  return i >= 0 && i < flow.length - 1 ? flow[i + 1] : null
}

const tomorrowAt = (h = 16) => { const d = new Date(); d.setDate(d.getDate() + 1); d.setHours(h, 0, 0, 0); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16) }
export const blankPreOrder = () => ({
  customer_id: '', customer_name: '', customer_phone: '', channel: 'shop', fulfilment: 'pickup', due_at: tomorrowAt(),
  address: '', driver: '', delivery_fee: 0, discount_type: 'amount', discount_value: 0, notes: '', deposit: null as number | null, deposit_method: 'cash',
  lines: [blankLine()],
})
export const preOrderForm = (o: any) => ({
  customer_id: o.customer_id || '', customer_name: o.customer_name || '', customer_phone: o.customer_phone || '', channel: o.channel, fulfilment: o.fulfilment,
  due_at: (o.due_at || '').replace(' ', 'T').slice(0, 16), address: o.address || '', driver: o.driver || '', delivery_fee: +o.delivery_fee || 0,
  discount_type: 'amount', discount_value: +o.discount || 0, notes: o.notes || '',
  lines: (o.lines || []).map((l: any) => ({ item_id: l.item_id || '', description: l.description, qty: +l.qty, unit: l.unit || '', unit_price: +l.unit_price, discount: +l.discount || 0 })),
})
export const preOrderPayload = (f: any) => ({ ...f, due_at: f.due_at, customer_id: f.customer_id || null, lines: f.lines.filter((l: any) => l.item_id || (l.description || '').trim()) })
export const preOrderTotals = (f: any, taxRate: number) => {
  const c = calcDoc({ ...f, tax_rate: taxRate })
  const fee = f.fulfilment === 'delivery' ? +f.delivery_fee || 0 : 0
  const feeTax = Math.round(fee * taxRate / 100 * 1000) / 1000
  return { ...c, fee, tax: Math.round((c.tax + feeTax) * 1000) / 1000, total: Math.round((c.total + fee + feeTax) * 1000) / 1000 }
}
/** «اليوم 4:00 م» / «غداً 10:30 ص» / date — how the team reads a due time. */
export const dueLabel = (iso: string) => {
  if (!iso) return '—'
  const d = new Date(iso.replace(' ', 'T')); const t = d.toLocaleTimeString(currentLocale() === 'ar' ? 'ar' : 'en-GB', { hour: 'numeric', minute: '2-digit' })
  const day = new Date(d); day.setHours(0, 0, 0, 0); const today = new Date(); today.setHours(0, 0, 0, 0)
  const diff = Math.round((day.getTime() - today.getTime()) / 86400000)
  const name = diff === 0 ? tt('اليوم', 'Today') : diff === 1 ? tt('غداً', 'Tomorrow') : diff === -1 ? tt('أمس', 'Yesterday') : ymd(d)
  return `${name} ${t}`
}
export const isLate = (o: any) => !['delivered', 'cancelled'].includes(o.status) && new Date((o.due_at || '').replace(' ', 'T')).getTime() < Date.now()
