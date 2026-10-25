/** Local calendar date as YYYY-MM-DD (toISOString() is UTC: near midnight it gives the wrong day). */
export const ymd = (d: Date = new Date()) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
// Shared formatters: money (3 decimals, Omani style), qty, dates.
export function useFmt() {
  const auth = useAuth()
  // Currency code → label shown next to amounts (Arabic symbol / Latin code). Default is "ريال"/"SAR".
  const labelsAr: Record<string, string> = { SAR: 'ريال', YER: 'ريال', QAR: 'ر.ق', OMR: 'ر.ع', AED: 'د.إ', KWD: 'د.ك', BHD: 'د.ب', EGP: 'ج.م', JOD: 'د.أ', USD: '$', EUR: '€' }
  const labelsEn: Record<string, string> = { SAR: 'SAR', YER: 'YER', QAR: 'QAR', OMR: 'OMR', AED: 'AED', KWD: 'KWD', BHD: 'BHD', EGP: 'EGP', JOD: 'JOD', USD: '$', EUR: '€' }
  const cur = computed(() => { const c = String(auth.state.value.tenant?.currency || 'SAR').toUpperCase(); return tt(labelsAr[c] || 'ريال', labelsEn[c] || 'SAR') })
  const money = (v: any, withCur = false) => {
    const n = Number(v || 0)
    const s = n.toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
    return withCur ? `${s} ${cur.value}` : s
  }
  const qty = (v: any) => { const n = Number(v || 0); return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/\.?0+$/, '') }
  const dt = (v: any) => v ? String(v).slice(0, 16).replace('T', ' ') : '—'
  const d = (v: any) => v ? String(v).slice(0, 10) : '—'
  const today = () => ymd()
  const methodLabel = (m: string) => (tt(({ cash: 'نقدي', card: 'بطاقة', transfer: 'تحويل', credit: 'آجل', wallet: 'من المحفظة', advance: 'من العربون' } as any)[m], ({ cash: 'Cash', card: 'Card', transfer: 'Transfer', credit: 'Credit', wallet: 'Wallet', advance: 'Deposit' } as any)[m]) || m)
  return { money, qty, dt, d, today, cur, methodLabel }
}
