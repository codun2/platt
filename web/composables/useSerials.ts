/** Add-on «الأرقام التسلسلية والضمان»: status labels + warranty helpers. */
export const serialStatus: Record<string, any> = {
  in_stock: L('في المخزن', 'In stock', { cls: 'info', icon: 'ti-building-warehouse' }),
  sold: L('مباع', 'Sold', { cls: 'ok', icon: 'ti-shopping-cart' }),
  installed: L('مُركَّب', 'Installed', { cls: 'ok', icon: 'ti-tool' }),
  returned: L('مرتجع', 'Returned', { cls: 'warn', icon: 'ti-arrow-back-up' }),
  faulty: L('عطل', 'Faulty', { cls: 'bad', icon: 'ti-alert-triangle' }),
  scrapped: L('متلف', 'Scrapped', { cls: 'muted', icon: 'ti-trash' }),
}
/** Warranty state of a delivered unit from its end date. */
export const warrantyState = (u: any) => {
  if (!['sold', 'installed'].includes(u.status) || !u.warranty_until) return null
  const days = Math.round((new Date(u.warranty_until + 'T00:00:00').getTime() - new Date(new Date().toDateString()).getTime()) / 86400000)
  if (days < 0) return { cls: 'bad', label: tt(`انتهى الضمان منذ ${-days} يوم`, `expired ${-days}d ago`), days }
  if (days <= 60) return { cls: 'warn', label: tt(`الضمان ينتهي خلال ${days} يوم`, `${days}d of warranty left`), days }
  const months = Math.round(days / 30)
  return { cls: 'ok', label: tt(`ضمان سارٍ (${months >= 12 ? Math.round(months / 12) + ' سنة' : months + ' شهر'})`, `${months}mo warranty left`), days }
}
