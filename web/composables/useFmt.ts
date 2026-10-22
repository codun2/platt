// Shared formatters: money (3 decimals, Omani style), qty, dates.
export function useFmt() {
  const auth = useAuth()
  // Currency code → Arabic label shown next to amounts. Default is "ريال".
  const labels: Record<string, string> = { SAR: 'ريال', YER: 'ريال', QAR: 'ر.ق', OMR: 'ر.ع', AED: 'د.إ', KWD: 'د.ك', BHD: 'د.ب', EGP: 'ج.م', JOD: 'د.أ', USD: '$', EUR: '€' }
  const cur = computed(() => { const c = String(auth.state.value.tenant?.currency || 'SAR').toUpperCase(); return labels[c] || 'ريال' })
  const money = (v: any, withCur = false) => {
    const n = Number(v || 0)
    const s = n.toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
    return withCur ? `${s} ${cur.value}` : s
  }
  const qty = (v: any) => { const n = Number(v || 0); return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/\.?0+$/, '') }
  const dt = (v: any) => v ? String(v).slice(0, 16).replace('T', ' ') : '—'
  const d = (v: any) => v ? String(v).slice(0, 10) : '—'
  const today = () => new Date().toISOString().slice(0, 10)
  const methodLabel = (m: string) => ({ cash: 'نقدي', card: 'بطاقة', transfer: 'تحويل', credit: 'آجل' } as any)[m] || m
  return { money, qty, dt, d, today, cur, methodLabel }
}
