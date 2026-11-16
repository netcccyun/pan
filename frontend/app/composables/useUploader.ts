import SparkMD5 from 'spark-md5'

export type UploadStatus = 'pending' | 'hashing' | 'uploading' | 'saving' | 'success' | 'exists' | 'error'

export interface UploadItem {
  id: number
  file: File
  name: string
  size: number
  status: UploadStatus
  progress: number
  speed: string
  message: string
  hash: string
  pwd: string
}

export interface UploadOptions {
  show: boolean
  ispwd: boolean
  pwd: string
  /** 上传到的存储，0 为默认存储 */
  storage_id?: number
}

const HASH_CHUNK = 2 * 1024 * 1024

function md5File(file: File, onProgress: (percent: number) => void): Promise<string> {
  return new Promise((resolve, reject) => {
    const spark = new SparkMD5.ArrayBuffer()
    const reader = new FileReader()
    const chunks = Math.max(1, Math.ceil(file.size / HASH_CHUNK))
    let current = 0

    const loadNext = () => {
      const start = current * HASH_CHUNK
      const end = Math.min(start + HASH_CHUNK, file.size)
      reader.readAsArrayBuffer(file.slice(start, end))
    }

    reader.onload = (e) => {
      spark.append(e.target!.result as ArrayBuffer)
      current++
      onProgress(Math.round((current / chunks) * 100))
      if (current < chunks) loadNext()
      else resolve(spark.end())
    }
    reader.onerror = () => reject(new Error('读取文件失败'))
    loadNext()
  })
}

interface XhrResult {
  status: number
  text: string
}

function xhrUpload(
  url: string,
  body: FormData | Blob,
  onProgress: (loaded: number) => void,
  method = 'POST',
  headers: Record<string, string> = {}
): Promise<XhrResult> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open(method, url, true)
    xhr.withCredentials = false
    for (const [k, v] of Object.entries(headers)) xhr.setRequestHeader(k, v)
    xhr.upload.addEventListener('progress', e => onProgress(e.loaded))
    xhr.onload = () => resolve({ status: xhr.status, text: xhr.responseText })
    xhr.onerror = () => reject(new Error('网络错误，上传失败'))
    xhr.ontimeout = () => reject(new Error('上传超时'))
    xhr.send(body)
  })
}

function parseJson(res: XhrResult) {
  if (res.status < 200 || res.status >= 300) throw new Error(`服务器错误（${res.status}）`)
  try {
    return JSON.parse(res.text)
  } catch {
    throw new Error('服务器返回数据格式错误')
  }
}

export function useUploader() {
  const { rawPost, csrf } = useApi()

  const items = ref<UploadItem[]>([])
  const running = ref(false)
  let seq = 0

  const pendingCount = computed(() => items.value.filter(i => i.status === 'pending').length)
  const doneCount = computed(() => items.value.filter(i => i.status === 'success' || i.status === 'exists').length)
  const errorCount = computed(() => items.value.filter(i => i.status === 'error').length)
  const totalSize = computed(() => items.value.reduce((s, i) => s + i.size, 0))

  function add(files: FileList | File[], maxSizeMb = 0) {
    for (const file of Array.from(files)) {
      const dup = items.value.some(i => i.status === 'pending' && i.name === file.name && i.size === file.size && i.file.lastModified === file.lastModified)
      if (dup) continue
      const item: UploadItem = {
        id: ++seq,
        file,
        name: file.name || `粘贴的文件-${seq}`,
        size: file.size,
        status: 'pending',
        progress: 0,
        speed: '',
        message: '',
        hash: '',
        pwd: ''
      }
      if (maxSizeMb > 0 && file.size > maxSizeMb * 1024 * 1024) {
        item.status = 'error'
        item.message = `文件大小超过限制（${maxSizeMb}MB）`
      } else if (file.size === 0) {
        item.status = 'error'
        item.message = '不能上传空文件'
      }
      items.value.push(item)
    }
  }

  function remove(id: number) {
    items.value = items.value.filter(i => i.id !== id || ['hashing', 'uploading', 'saving'].includes(i.status))
  }

  function clear(onlyFinished = false) {
    items.value = items.value.filter(i => (onlyFinished ? !['success', 'exists', 'error'].includes(i.status) : ['hashing', 'uploading', 'saving'].includes(i.status)))
  }

  function retry(id: number) {
    const item = items.value.find(i => i.id === id)
    if (item && item.status === 'error' && !item.message.startsWith('文件大小超过') && item.size > 0) {
      item.status = 'pending'
      item.progress = 0
      item.message = ''
    }
  }

  function makeSpeedMeter(item: UploadItem) {
    let lastTime = Date.now()
    let lastLoaded = 0
    return (loaded: number) => {
      const now = Date.now()
      const dt = (now - lastTime) / 1000
      if (dt >= 0.5) {
        item.speed = `${sizeFormat(Math.max(0, (loaded - lastLoaded) / dt))}/s`
        lastTime = now
        lastLoaded = loaded
      }
    }
  }

  async function processItem(item: UploadItem, options: UploadOptions) {
    const target = items.value.find(i => i.id === item.id) || item
    const file = target.file

    target.status = 'hashing'
    target.progress = 0
    target.message = ''
    const hash = await md5File(file, (p) => { target.progress = p })
    target.hash = hash

    const csrfToken = await csrf()
    const pwd = options.ispwd ? options.pwd.trim() : ''
    const pre = await rawPost('pre_upload', {
      csrf_token: csrfToken,
      name: target.name,
      hash,
      size: file.size,
      show: options.show ? '1' : '0',
      ispwd: options.ispwd ? '1' : '0',
      pwd,
      storage_id: options.storage_id || 0
    })
    if (pre.code !== 0 && pre.code !== 1) throw new Error(pre.msg || '上传失败')
    target.pwd = pwd

    if (pre.code === 1) {
      target.progress = 100
      target.status = 'exists'
      target.message = pre.msg || '本站已存在该文件，秒传成功'
      return
    }

    target.status = 'uploading'
    target.progress = 0
    let result: any

    if (pre.third) {
      const meter = makeSpeedMeter(target)
      const onProgress = (loaded: number) => {
        target.progress = Math.min(99, Math.round((loaded / file.size) * 100))
        meter(loaded)
      }
      let res: XhrResult
      if (pre.method === 'PUT') {
        // 签名链接直传（如 Azure Blob）：请求体就是文件内容
        res = await xhrUpload(pre.url, file, onProgress, 'PUT', pre.headers || {})
      } else {
        const form = new FormData()
        for (const [k, v] of Object.entries(pre.post || {})) form.append(k, String(v))
        form.append('file', file)
        res = await xhrUpload(pre.url, form, onProgress)
      }
      if (res.status < 200 || res.status >= 300) throw new Error(`上传到存储服务器失败（${res.status}）`)
      target.status = 'saving'
      target.progress = 100
      result = await rawPost('complete_upload', { hash, csrf_token: csrfToken })
      if (result.code !== 0 && result.code !== 1) throw new Error(result.msg || '上传失败')
    } else {
      const chunkSize: number = pre.chunksize
      const chunks: number = pre.chunks
      const meter = makeSpeedMeter(target)
      let uploaded = 0
      for (let chunk = 1; chunk <= chunks; chunk++) {
        const start = (chunk - 1) * chunkSize
        const end = Math.min(start + chunkSize, file.size)
        const form = new FormData()
        form.append('file', chunks === 1 ? file : file.slice(start, end), target.name)
        form.append('hash', hash)
        form.append('chunk', String(chunk))
        form.append('csrf_token', csrfToken)
        const res = await xhrUpload('./ajax.php?act=upload_part', form, (loaded) => {
          const total = uploaded + loaded
          const percent = Math.min(100, Math.round((total / file.size) * 100))
          target.progress = percent
          if (percent >= 100) target.status = 'saving'
          meter(total)
        })
        result = parseJson(res)
        if (result.code !== 0 && result.code !== 1) throw new Error(result.msg || `文件第${chunk}分块上传失败`)
        uploaded = end
      }
    }

    target.progress = 100
    target.speed = ''
    target.status = result?.exists ? 'exists' : 'success'
    target.message = result?.msg || '文件上传成功'
  }

  async function start(options: UploadOptions) {
    if (running.value) return
    running.value = true
    try {
      while (true) {
        const next = items.value.find(i => i.status === 'pending')
        if (!next) break
        try {
          await processItem(next, options)
        } catch (e: any) {
          const target = items.value.find(i => i.id === next.id)
          if (target) {
            target.status = 'error'
            target.speed = ''
            target.message = e?.message || '上传失败'
          }
        }
      }
    } finally {
      running.value = false
    }
  }

  return { items, running, pendingCount, doneCount, errorCount, totalSize, add, remove, clear, retry, start }
}
