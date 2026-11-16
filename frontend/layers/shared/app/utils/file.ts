export function sizeFormat(size: number): string {
  if (!size || size < 1024) return `${size || 0} B`
  const units = ['KB', 'MB', 'GB', 'TB']
  let value = size
  let unit = 'B'
  for (const u of units) {
    if (value < 1024) break
    value /= 1024
    unit = u
  }
  return `${Math.round(value * 100) / 100} ${unit}`
}

export function getExt(name: string): string {
  const idx = name.lastIndexOf('.')
  return idx > 0 ? name.slice(idx + 1).toLowerCase() : ''
}

const typeImage = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'ico', 'svg', 'svgz', 'tif', 'tiff', 'heic', 'psd', 'exif', 'pcx', 'tga', 'fpx', 'cdr', 'pcd', 'eps', 'ai', 'wmf', 'raw', 'ufo', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'avif']
const typeAudio = ['mp3', 'wav', 'wma', 'ogg', 'm4a', 'flac', 'ape', 'aac', 'ra', 'cda', 'midi', 'mid', 'aif', 'au', 'voc']
const typeVideo = ['mp4', 'webm', 'flv', 'f4v', 'mov', '3gp', '3gpp', 'avi', 'mpg', 'mpeg', 'wmv', 'mkv', 'ts', 'dat', 'asf', 'rm', 'rmvb', 'ram', 'divx', 'vob', 'qt', 'fli', 'flc', 'mod', 'm2t', 'swf', 'mts', 'm2ts', 'mpe', 'div', 'lavf', 'm3u8', 'm4v', 'ogm', 'ogv']
const typeText = ['txt', 'text', 'log', 'md', 'yaml', 'yml', 'conf', 'config', 'ini']
const typeCode = ['c', 'cpp', 'cxx', 'rc', 'php', 'py', 'cs', 'h', 'htm', 'html', 'css', 'less', 'js', 'hdml', 'dtd', 'wml', 'xml', 'vbs', 'vb', 'rtx', 'xsd', 'dpr', 'sql', 'java', 'go', 'jsp', 'asp', 'aspx', 'asa', 'asax', 'pl', 'bat', 'cmd', 'rb', 'reg', 'sh', 'json', 'lua', 'r', 'mm', 'mak', 'swift', 'tpl']
const typeArchive = ['zip', '7z', 'rar', 'tgz', 'gz', 'xz', 'tar', 'jar', 'iso', 'z', 'zipx', 'cab', 'bz2', 'arj', 'lz', 'lzh']
const typeWord = ['doc', 'docx', 'xps', 'rtf', 'wps', 'odt']
const typeExcel = ['xls', 'xlsx', 'ods', 'csv']
const typePowerpoint = ['ppt', 'pptx', 'pptm']

const iconGroups: [string[], string][] = [
  [typeImage, 'i-lucide-file-image'],
  [typeAudio, 'i-lucide-file-music'],
  [typeVideo, 'i-lucide-file-video-camera'],
  [typeText, 'i-lucide-file-text'],
  [typeCode, 'i-lucide-file-code'],
  [typeArchive, 'i-lucide-file-archive'],
  [typeWord, 'i-lucide-file-type'],
  [typeExcel, 'i-lucide-file-spreadsheet'],
  [['pdf'], 'i-lucide-file-text'],
  [typePowerpoint, 'i-lucide-presentation'],
  [['apk'], 'i-simple-icons-android'],
  [['ipa', 'dmg'], 'i-simple-icons-apple'],
  [['exe', 'appx', 'msi'], 'i-lucide-app-window'],
  [['deb', 'rpm'], 'i-simple-icons-linux']
]

export function typeIcon(type: string | null | undefined): string {
  const ext = (type || '').toLowerCase()
  for (const [list, icon] of iconGroups) {
    if (list.includes(ext)) return icon
  }
  return 'i-lucide-file'
}

export type ViewType = 'image' | 'audio' | 'video' | 'office' | 'other'

const typeOffice = ['doc', 'docx', 'xps', 'rtf', 'wps', 'xls', 'xlsx', 'ppt', 'pptx']

/** 与后端 get_view_type 一致：图片/音频/视频以站点配置为准 */
export function viewType(type: string, site: { type_image: string, type_audio: string, type_video: string }): ViewType {
  const ext = (type || '').toLowerCase()
  const split = (s: string) => (s || '').split('|').filter(Boolean)
  if (split(site.type_image).includes(ext)) return 'image'
  if (split(site.type_audio).includes(ext)) return 'audio'
  if (split(site.type_video).includes(ext)) return 'video'
  if (typeOffice.includes(ext)) return 'office'
  return 'other'
}
