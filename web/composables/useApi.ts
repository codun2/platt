// Thin fetch wrapper: adds the Sanctum bearer token, JSON headers, and
// normalises Laravel validation errors into a readable message.
export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  /** true when the request never reached the server (no internet / server down). */
  offline: boolean
  constructor(status: number, message: string, errors: Record<string, string[]> = {}, offline = false) {
    super(message)
    this.status = status
    this.errors = errors
    this.offline = offline
  }
}

export function useApi() {
  const config = useRuntimeConfig()
  const token = useState<string | null>('token', () => null)

  if (token.value === null && import.meta.client) {
    token.value = localStorage.getItem('rakeez_token')
  }

  async function request<T = any>(path: string, opts: { method?: string; body?: any; query?: Record<string, any>; headers?: Record<string, string> } = {}): Promise<T> {
    const url = new URL(config.public.apiBase + path, window.location.origin)
    if (opts.query) {
      Object.entries(opts.query).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v))
      })
    }

    let res: Response
    try {
      res = await fetch(url.toString(), {
        method: opts.method || 'GET',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Locale': currentLocale(),
          ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
          ...(opts.headers || {}),
        },
        body: opts.body ? JSON.stringify(opts.body) : undefined,
      })
    } catch {
      // Network failure: no internet or the server is unreachable.
      markOnline(false)
      throw new ApiError(0, tt('لا يوجد اتصال بالإنترنت.', 'No internet connection.'), {}, true)
    }
    // The service worker answered from the device's copy (or had none).
    const cache = res.headers.get('X-Rk-Cache')
    if (cache) markOnline(false)
    else markOnline(true)
    if (cache === 'miss') throw new ApiError(503, tt('أنت غير متصل بالإنترنت ولا توجد نسخة محفوظة من هذه الصفحة على الجهاز.', 'You are offline and this page was not saved on this device yet.'), {}, true)

    const text = await res.text()
    let data: any = null
    try { data = text ? JSON.parse(text) : null } catch { data = null }

    if (!res.ok) {
      if (res.status === 401) {
        token.value = null
        if (import.meta.client) localStorage.removeItem('rakeez_token')
        navigateTo('/login')
      }
      const errors = data?.errors || {}
      const first = Object.values(errors)[0] as string[] | undefined
      // Never show a raw "Server Error": explain it in the user's language.
      let msg = res.status >= 500
        ? tt('تعذّر حفظ البيانات بسبب خطأ في الخادم. راجع القيم المدخلة وحاول مرة أخرى.', 'The server could not process this request. Check the values and try again.')
        : (first?.[0] || data?.message || tt('حدث خطأ غير متوقع', 'An unexpected error occurred'))
      // Framework messages ("The route … could not be found", "This action is
      // unauthorized") are English: in Arabic, explain by status instead.
      if (currentLocale() === 'ar' && !/[؀-ۿ]/.test(msg)) {
        msg = ({
          403: 'ليست لديك صلاحية لتنفيذ هذا الإجراء.',
          404: 'العنصر المطلوب غير موجود أو تم حذفه. حدّث الصفحة وحاول مرة أخرى.',
          419: 'انتهت الجلسة، حدّث الصفحة وحاول مرة أخرى.',
          429: 'محاولات كثيرة متتالية، انتظر قليلاً ثم حاول مرة أخرى.',
        } as Record<number, string>)[res.status] || 'حدث خطأ غير متوقع، حاول مرة أخرى.'
      }
      throw new ApiError(res.status, msg, errors)
    }
    return data as T
  }

  return {
    token,
    get: <T = any>(p: string, query?: Record<string, any>) => request<T>(p, { query }),
    /** Low-level call used by the offline outbox (custom method + headers). */
    raw: <T = any>(p: string, opts: { method?: string; body?: any; headers?: Record<string, string> }) => request<T>(p, opts),
    post: <T = any>(p: string, body?: any) => request<T>(p, { method: 'POST', body }),
    patch: <T = any>(p: string, body?: any) => request<T>(p, { method: 'PATCH', body }),
    del: <T = any>(p: string) => request<T>(p, { method: 'DELETE' }),
    setToken(t: string | null) {
      token.value = t
      if (import.meta.client) t ? localStorage.setItem('rakeez_token', t) : localStorage.removeItem('rakeez_token')
    },
  }
}
