/** Add-on «العروض والكوبونات»: labels + blank form. */
export const promoType: Record<string, any> = {
  percent: L('خصم نسبة على الأصناف', '% off items', { icon: 'ti-percentage' }),
  amount: L('خصم مبلغ على كل قطعة', 'Amount off each unit', { icon: 'ti-coin' }),
  bxgy: L('اشترِ X واحصل على Y مجاناً', 'Buy X get Y free', { icon: 'ti-gift' }),
  bill_percent: L('خصم نسبة على الفاتورة', '% off the bill', { icon: 'ti-receipt-2' }),
  bill_amount: L('خصم مبلغ على الفاتورة', 'Amount off the bill', { icon: 'ti-receipt' }),
}
export const promoState: Record<string, any> = {
  live: L('فعّال الآن', 'Live now', { cls: 'ok' }), waiting: L('فعّال (خارج أوقاته الآن)', 'On (outside its hours now)', { cls: 'info' }),
  scheduled: L('مجدول', 'Scheduled', { cls: 'info' }), expired: L('منتهٍ', 'Expired', { cls: 'muted' }),
  used_up: L('استُنفد', 'Used up', { cls: 'warn' }), off: L('موقوف', 'Off', { cls: 'muted' }),
}
export const weekDays = () => [tt('الأحد', 'Sun'), tt('الإثنين', 'Mon'), tt('الثلاثاء', 'Tue'), tt('الأربعاء', 'Wed'), tt('الخميس', 'Thu'), tt('الجمعة', 'Fri'), tt('السبت', 'Sat')]
export const blankPromo = () => ({
  name: '', type: 'percent', applies_to: 'all', category_ids: [] as number[], item_ids: [] as number[], value: null as number | null,
  buy_qty: 2, get_qty: 1, min_total: 0, max_discount: null as number | null, code: '', starts_on: '', ends_on: '',
  days: [] as number[], time_from: '', time_to: '', usage_limit: null as number | null, is_active: true, notes: '',
})
export const promoForm = (p: any) => ({
  ...blankPromo(), ...p, category_ids: p.category_ids || [], item_ids: p.item_ids || [], days: p.days || [],
  code: p.code || '', starts_on: p.starts_on || '', ends_on: p.ends_on || '', time_from: (p.time_from || '').slice(0, 5), time_to: (p.time_to || '').slice(0, 5),
  value: p.value !== null ? +p.value : null, min_total: +p.min_total || 0, max_discount: p.max_discount !== null ? +p.max_discount : null,
})
export const promoPayload = (f: any) => ({
  ...f, code: f.code || null, starts_on: f.starts_on || null, ends_on: f.ends_on || null, time_from: f.time_from || null, time_to: f.time_to || null,
  max_discount: f.max_discount || null, usage_limit: f.usage_limit || null,
})
/** One line describing the offer, e.g. «اشترِ 2 واحصل على 1 مجاناً — عصير». */
export const promoSummary = (p: any, f: ReturnType<typeof useFmt>) => {
  const v = +p.value || 0
  const what = ({ percent: tt(`خصم ${v}%`, `${v}% off`), amount: tt(`خصم ${f.money(v)} للقطعة`, `${f.money(v)} off each`), bxgy: tt(`اشترِ ${p.buy_qty} واحصل على ${p.get_qty} مجاناً`, `Buy ${p.buy_qty} get ${p.get_qty} free`), bill_percent: tt(`خصم ${v}% على الفاتورة`, `${v}% off the bill`), bill_amount: tt(`خصم ${f.money(v)} على الفاتورة`, `${f.money(v)} off the bill`) } as any)[p.type]
  const min = +p.min_total > 0 ? tt(` عند شراء ${f.money(p.min_total)} فأكثر`, ` on ${f.money(p.min_total)}+`) : ''
  return what + min
}
