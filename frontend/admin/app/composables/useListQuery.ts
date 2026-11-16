export const PAGE_SIZES = [15, 30, 50, 100]

/** 列表的筛选条件与分页参数，与地址栏 query 同步，刷新页面后保持 */
export function useListQuery<T extends Record<string, string>>(defaults: T) {
  const route = useRoute()
  const router = useRouter()

  const page = computed(() => Math.max(1, Number.parseInt(String(route.query.page || '1')) || 1))
  const pagesize = computed(() => {
    const size = Number.parseInt(String(route.query.pagesize || '')) || PAGE_SIZES[0]!
    return PAGE_SIZES.includes(size) ? size : PAGE_SIZES[0]!
  })
  const query = computed(() => {
    const result = { ...defaults }
    for (const key of Object.keys(defaults) as (keyof T)[]) {
      const value = route.query[key as string]
      if (value !== undefined && value !== null) result[key] = String(value) as T[keyof T]
    }
    return result
  })

  /** 搜索表单中正在编辑的条件，点击搜索后才生效 */
  const filters = reactive({ ...query.value }) as T
  watch(query, value => Object.assign(filters, value))

  function buildQuery(values: T, extra: Record<string, string | number | undefined> = {}) {
    const result: Record<string, string> = {}
    for (const [key, value] of Object.entries(values)) {
      const v = String(value ?? '').trim()
      if (v !== '' && v !== defaults[key]) result[key] = v
    }
    if (pagesize.value !== PAGE_SIZES[0]) result.pagesize = String(pagesize.value)
    for (const [key, value] of Object.entries(extra)) {
      if (value === undefined) delete result[key]
      else result[key] = String(value)
    }
    return result
  }

  const search = () => router.push({ query: buildQuery(filters) })
  const reset = () => router.push({ query: {} })
  const setPage = (p: number) => router.push({ query: buildQuery(query.value, { page: p > 1 ? p : undefined }) })
  const setPagesize = (size: number) => router.push({
    query: buildQuery(query.value, { pagesize: size !== PAGE_SIZES[0] ? size : undefined })
  })

  return { page, pagesize, query, filters, search, reset, setPage, setPagesize }
}
