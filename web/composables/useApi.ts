// Thin fetch wrapper: adds the Sanctum bearer token, JSON headers, and
// normalises Laravel validation errors into a readable message.
export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

export function useApi() {
  const config = useRuntimeConfig()
  const token = useState<string | null>('token', () => null)

  if (token.value === null && import.meta.client) {
    token.value = localStorage.getItem('rakeez_token')
  }

  async function request<T = any>(path: string, opts: { method?: string; body?: any; query?: Record<string, any> } = {}): Promise<T> {
    const url = new URL(config.public.apiBase + path, window.location.origin)
    if (opts.query) {
      Object.entries(opts.query).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v))
      })
    }

    const res = await fetch(url.toString(), {
      method: opts.method || 'GET',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
      },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
    })

    const text = await res.text()
    const data = text ? JSON.parse(text) : null

    if (!res.ok) {
      if (res.status === 401) {
        token.value = null
        if (import.meta.client) localStorage.removeItem('rakeez_token')
        navigateTo('/login')
      }
      const errors = data?.errors || {}
      const first = Object.values(errors)[0] as string[] | undefined
      throw new ApiError(res.status, first?.[0] || data?.message || 'حدث خطأ غير متوقع', errors)
    }
    return data as T
  }

  return {
    token,
    get: <T = any>(p: string, query?: Record<string, any>) => request<T>(p, { query }),
    post: <T = any>(p: string, body?: any) => request<T>(p, { method: 'POST', body }),
    patch: <T = any>(p: string, body?: any) => request<T>(p, { method: 'PATCH', body }),
    del: <T = any>(p: string) => request<T>(p, { method: 'DELETE' }),
    setToken(t: string | null) {
      token.value = t
      if (import.meta.client) t ? localStorage.setItem('rakeez_token', t) : localStorage.removeItem('rakeez_token')
    },
  }
}
