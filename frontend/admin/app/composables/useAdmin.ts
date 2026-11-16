export interface AdminStatus {
  islogin: number
  title: string
  admin_user: string | null
}

export function useAdmin() {
  const status = useState<AdminStatus>('admin-status', () => ({ islogin: 0, title: '', admin_user: null }))
  const loaded = useState<boolean>('admin-loaded', () => false)
  const error = useState<string>('admin-error', () => '')
  /** 网站未安装或需要升级时，后端返回的跳转信息 */
  const halt = useState<{ msg: string, url: string, auto: boolean } | null>('admin-halt', () => null)

  const load = async () => {
    const { get } = useApi()
    try {
      const { code: _code, msg: _msg, ...data } = await get('status')
      status.value = data as AdminStatus
      error.value = ''
      halt.value = null
    } catch (e: any) {
      error.value = e?.message || '加载失败'
      if (e?.code === -10 && e.data?.url) {
        // 接口返回的是相对网站根目录的地址，后台位于下一级目录
        const url = String(e.data.url).replace(/^\.\//, '../')
        halt.value = { msg: e.data.msg, url, auto: !!e.data.auto }
      }
    } finally {
      loaded.value = true
    }
  }

  const isLogin = computed(() => status.value.islogin === 1)

  const logout = async () => {
    await useApi().post('logout')
    status.value = { ...status.value, islogin: 0, admin_user: null }
    await navigateTo('/login')
  }

  return { status, loaded, error, halt, load, isLogin, logout }
}
