export interface PreviewFile {
  id: number
  name: string
  type: string
  view_type: string | null
  viewurl: string
  fileurl: string
}

export interface StorageField {
  key: string
  label: string
  type: 'text' | 'password' | 'select' | 'switch' | 'textarea'
  options?: { label: string, value: string }[]
  placeholder?: string
  help?: string
  required: boolean
  default: string
  /** 仅当 key 对应的参数等于 value（或在 value 列表中）时显示 */
  show?: { key: string, value: string | string[] }
}

/** 存储账号授权说明 */
export interface StorageOauth {
  /** 授权前必须填写的参数 */
  require?: string[]
  /** 配置步骤 */
  steps: string[]
}

/** 存储类型，参数定义由后端返回 */
export interface StorageType {
  type: string
  name: string
  /** i-lucide-xxx 图标名，或相对网站根目录的图片地址 */
  icon: string
  url: string | null
  cloud: boolean
  range: boolean
  available: boolean
  /** 需要账号授权时返回 */
  oauth: StorageOauth | null
  fields: StorageField[]
}

export interface StorageRow {
  id: number
  name: string
  type: string
  type_name: string
  icon: string
  available: number
  config: Record<string, string>
  status: number
  isdefault: number
  sort: number
  addtime: string
  filecount: number
}

export function isStorageFieldVisible(field: StorageField, values: Record<string, string>) {
  if (!field.show) return true
  const expect = Array.isArray(field.show.value) ? field.show.value : [field.show.value]
  return expect.map(String).includes(String(values[field.show.key] ?? ''))
}
