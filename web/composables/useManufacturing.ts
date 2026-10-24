/** Manufacturing engine UI helpers. */
export const prodStatus: Record<string, { label: string; cls: string; icon: string }> = {
  planned: L('مخطط', 'Planned', { cls: 'info', icon: 'ti-calendar' }), in_progress: L('قيد الإنتاج', 'In production', { cls: 'warn', icon: 'ti-settings-automation' }),
  completed: L('مكتمل', 'Completed', { cls: 'ok', icon: 'ti-circle-check' }), cancelled: L('ملغى', 'Cancelled', { cls: 'muted', icon: 'ti-ban' }),
}
export const blankBom = () => ({ item_id: '', name: '', output_qty: 1, extra_cost: 0, instructions: '', is_active: true, lines: [] as any[] })
export const bomToForm = (b: any) => ({ item_id: b.item_id, name: b.name, output_qty: +b.output_qty, extra_cost: +b.extra_cost, instructions: b.instructions || '', is_active: !!b.is_active, lines: b.lines.map((l: any) => ({ item_id: l.item_id, qty: +l.qty, waste_pct: +l.waste_pct })) })
export const bomCost = (f: any, items: any[]) => {
  const cost = (id: any) => +(items.find((i: any) => String(i.id) === String(id))?.cost_price || 0)
  return Math.round(((f.lines || []).reduce((n: number, l: any) => n + (+l.qty || 0) * (1 + (+l.waste_pct || 0) / 100) * cost(l.item_id), 0) + (+f.extra_cost || 0)) * 1000) / 1000
}
