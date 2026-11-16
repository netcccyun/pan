export interface ApiResult {
  code: number
  msg?: string
  [key: string]: any
}

export class ApiError extends Error {
  code: number
  data?: ApiResult

  constructor(message: string, code = -1, data?: ApiResult) {
    super(message)
    this.code = code
    this.data = data
  }
}

type Scalar = string | number | boolean | null | undefined
type Params = Record<string, Scalar | Scalar[]>

/** 接口返回此 code 表示未登录（后台使用） */
export const CODE_UNAUTHORIZED = -3

function toForm(data: Params) {
  const body = new URLSearchParams()
  const append = (key: string, value: Scalar) => {
    if (value === undefined || value === null) return
    body.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  for (const [key, value] of Object.entries(data)) {
    if (Array.isArray(value)) value.forEach(v => append(`${key}[]`, v))
    else append(key, value)
  }
  return body
}

async function request(url: string, init: RequestInit, onUnauthorized?: () => void): Promise<ApiResult> {
  let res: Response
  try {
    res = await fetch(url, { credentials: 'same-origin', ...init })
  } catch {
    throw new ApiError('网络错误，请检查网络连接后重试')
  }
  if (!res.ok) throw new ApiError(`服务器错误（${res.status}）`)
  let data: ApiResult
  try {
    data = await res.json()
  } catch {
    throw new ApiError('服务器返回数据格式错误')
  }
  if (data.code === 403) throw new ApiError('请求来源校验失败，请刷新页面重试', 403, data)
  if (data.code === CODE_UNAUTHORIZED) {
    onUnauthorized?.()
    throw new ApiError(data.msg || '请先登录', CODE_UNAUTHORIZED, data)
  }
  return data
}

function buildUrl(file: string, act?: string, query?: Params) {
  const params = toForm({ act, ...query }).toString()
  return `./${file}${params ? `?${params}` : ''}`
}

/** 请求结果 code 为 0 或 1 视为成功，其它情况抛出 ApiError */
function ensureOk(data: ApiResult) {
  if (data.code === 0 || data.code === 1) return data
  throw new ApiError(data.msg || '请求失败', data.code, data)
}

export function useApi() {
  const nuxtApp = tryUseNuxtApp()
  const onUnauthorized = () => { nuxtApp?.callHook('api:unauthorized') }

  const get = async (act: string, query?: Params, file = 'ajax.php') =>
    request(buildUrl(file, act, query), { method: 'GET' }, onUnauthorized)

  const post = async (act: string, data: Params = {}, file = 'ajax.php') =>
    request(buildUrl(file, act), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: toForm(data)
    }, onUnauthorized)

  const csrf = async () => {
    const data = ensureOk(await get('csrf'))
    return data.csrf_token as string
  }

  return {
    get: async (act: string, query?: Params, file?: string) => ensureOk(await get(act, query, file)),
    post: async (act: string, data?: Params, file?: string) => ensureOk(await post(act, data, file)),
    rawGet: get,
    rawPost: post,
    csrf
  }
}
