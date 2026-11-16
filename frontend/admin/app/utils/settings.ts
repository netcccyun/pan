export const SETTING_PAGES = [
  { mod: 'site', label: '网站信息设置', icon: 'i-lucide-globe' },
  { mod: 'user', label: '用户登录设置', icon: 'i-lucide-log-in' },
  { mod: 'file', label: '文件上传设置', icon: 'i-lucide-upload' },
  { mod: 'green', label: '图片检测设置', icon: 'i-lucide-scan-eye' },
  { mod: 'api', label: '上传API设置', icon: 'i-lucide-plug' },
  { mod: 'iptype', label: '用户IP地址设置', icon: 'i-lucide-network' },
  { mod: 'account', label: '管理账号设置', icon: 'i-lucide-user-cog' }
] as const

export type SettingMod = typeof SETTING_PAGES[number]['mod']

export function settingPage(mod: SettingMod) {
  return SETTING_PAGES.find(item => item.mod === mod)!
}

export type Conf = Record<string, string | null | undefined>

export interface SettingOption {
  label: string
  value: string
}

export interface SettingField {
  key: string
  label: string
  type?: 'text' | 'textarea' | 'select' | 'switch' | 'checkboxes'
  options?: SettingOption[]
  placeholder?: string
  help?: string
  /** 输入框后缀，例如 MB */
  suffix?: string
  rows?: number
  required?: boolean
  /** 配置项不存在时使用的值 */
  default?: string
  /** 返回 false 时隐藏，且不会保存 */
  show?: (conf: Conf) => boolean
  /** 保存前处理值 */
  transform?: (value: string) => string
}

export const OPTIONS_SWITCH: SettingOption[] = [
  { label: '关闭', value: '0' },
  { label: '开启', value: '1' }
]

/** 取字段在表单中的初始值 */
export function fieldValue(conf: Conf, field: SettingField): string {
  const value = conf[field.key]
  if (value !== undefined && value !== null && value !== '') return String(value)
  if (field.default !== undefined) return field.default
  if (field.type === 'switch') return '0'
  if (field.type === 'select') return field.options?.[0]?.value ?? ''
  return ''
}
