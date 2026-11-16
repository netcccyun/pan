export interface SiteUser {
  uid: number
  nickname: string
  type: 'qq' | 'wx' | string
  faceimg?: string
  level: number
}

export interface SiteStorage {
  id: number
  name: string
  isdefault: number
}

export interface SiteConfig {
  title: string
  keywords: string
  description: string
  gonggao: string
  gg_file: string
  tongji: string
  userlogin: number
  login_qq: number
  login_wx: number
  forcelogin: number
  filesearch: number
  upload_size: number
  videoreview: number
  /** 是否允许上传时选择存储 */
  upload_choose_storage: number
  /** 可选择的存储（已开启） */
  storages: SiteStorage[]
  type_image: string
  type_audio: string
  type_video: string
  clientip: string
  siteurl: string
  islogin: number
  user: SiteUser | null
}

const defaults: SiteConfig = {
  title: '外链网盘',
  keywords: '',
  description: '',
  gonggao: '',
  gg_file: '',
  tongji: '',
  userlogin: 0,
  login_qq: 0,
  login_wx: 0,
  forcelogin: 0,
  filesearch: 0,
  upload_size: 0,
  videoreview: 0,
  upload_choose_storage: 0,
  storages: [],
  type_image: '',
  type_audio: '',
  type_video: '',
  clientip: '',
  siteurl: '',
  islogin: 0,
  user: null
}

export function useSite() {
  const site = useState<SiteConfig>('site', () => ({ ...defaults }))
  const loaded = useState<boolean>('site-loaded', () => false)
  const error = useState<string>('site-error', () => '')
  /** 网站未安装或需要升级时，后端返回的跳转信息 */
  const halt = useState<{ msg: string, url: string, auto: boolean } | null>('site-halt', () => null)

  const load = async () => {
    const { get } = useApi()
    try {
      const data = await get('config')
      const { code: _code, msg: _msg, ...config } = data
      site.value = { ...defaults, ...config } as SiteConfig
      error.value = ''
      halt.value = null
    } catch (e: any) {
      error.value = e?.message || '加载站点配置失败'
      if (e?.code === -10 && e.data?.url) halt.value = { msg: e.data.msg, url: e.data.url, auto: !!e.data.auto }
    } finally {
      loaded.value = true
    }
  }

  const isLogin = computed(() => site.value.islogin === 1)

  /** 站点根地址，后端未返回时退回到当前页面地址 */
  const siteUrl = computed(() => {
    if (site.value.siteurl) return site.value.siteurl
    if (import.meta.client) return location.href.replace(/[#?].*$/, '').replace(/[^/]*$/, '')
    return ''
  })

  const pageUrl = (hash: string, pwd?: string | null) =>
    `${siteUrl.value}#/detail?hash=${hash}${pwd ? `&pwd=${encodeURIComponent(pwd)}` : ''}`

  /** 图片/音乐/视频复制 view 直链，其他文件复制下载链接 */
  const fileUrl = (hash: string, ext?: string | null, pwd?: string | null) => {
    const type = (ext || 'file').toLowerCase()
    const kind = ['image', 'audio', 'video'].includes(viewType(type, site.value)) ? 'view' : 'down'
    let url = `${siteUrl.value}${kind}.php/${hash}.${type}`
    if (kind === 'down' && pwd) url += `&${pwd}`
    return url
  }

  return { site, loaded, error, halt, load, isLogin, siteUrl, pageUrl, fileUrl }
}
