<script setup lang="ts">
import { useUploader, type UploadItem, type UploadStatus } from '~/composables/useUploader'

useHead({ title: '上传文件' })

const { site, isLogin, fileUrl } = useSite()
const toast = useToast()
const copy = useCopy()
const uploader = useUploader()
const { items, running, pendingCount, doneCount, errorCount, totalSize } = uploader

const forbid = computed(() => site.value.forcelogin === 1 && !isLogin.value)

const options = reactive({ show: true, ispwd: false, pwd: '', storage_id: 0 })

/** 后台开启“允许选择存储类型”且有多个可选存储时，显示存储位置选择 */
const storageItems = computed(() => site.value.upload_choose_storage === 1 ? site.value.storages.map(s => ({ label: s.name, value: s.id })) : [])
watch(() => site.value.storages, (list) => {
  if (!list?.length) {
    options.storage_id = 0
  } else if (!list.some(s => s.id === options.storage_id)) {
    options.storage_id = (list.find(s => s.isdefault === 1) || list[0])!.id
  }
}, { immediate: true })
const pwdInvalid = computed(() => options.ispwd && options.pwd !== '' && !/^[a-zA-Z0-9]+$/.test(options.pwd))

const fileInput = ref<HTMLInputElement>()
const folderInput = ref<HTMLInputElement>()
const dragging = ref(false)
let dragDepth = 0

function addFiles(files: FileList | File[] | null | undefined) {
  if (!files || !files.length) return
  if (forbid.value) {
    toast.add({ title: '登录后才能上传文件', color: 'warning' })
    return
  }
  uploader.add(files, site.value.upload_size)
}

function pickFiles() {
  if (forbid.value) return
  fileInput.value?.click()
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  addFiles(input.files)
  input.value = ''
}

function pickFolder() {
  if (forbid.value) return
  folderInput.value?.click()
}

function onFolderChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files && !input.files.length) toast.add({ title: '所选文件夹中没有文件', color: 'warning' })
  addFiles(input.files)
  input.value = ''
}

function onDragEnter(e: DragEvent) {
  if (!e.dataTransfer?.types.includes('Files')) return
  dragDepth++
  dragging.value = true
}
function onDragLeave() {
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) dragging.value = false
}
function onDrop(e: DragEvent) {
  dragDepth = 0
  dragging.value = false
  addFiles(e.dataTransfer?.files)
}

function onPaste(e: ClipboardEvent) {
  const target = e.target as HTMLElement | null
  if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) return
  const files: File[] = []
  for (const item of Array.from(e.clipboardData?.items || [])) {
    if (item.kind === 'file') {
      const f = item.getAsFile()
      if (f) files.push(f)
    }
  }
  if (files.length) {
    e.preventDefault()
    addFiles(files)
  }
}

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (running.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => {
  document.addEventListener('paste', onPaste)
  window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => {
  document.removeEventListener('paste', onPaste)
  window.removeEventListener('beforeunload', onBeforeUnload)
})

onBeforeRouteLeave(() => {
  if (running.value) return window.confirm('文件正在上传中，离开页面将中断上传，确定离开吗？')
})

async function startUpload() {
  if (!pendingCount.value) return
  if (options.ispwd) {
    if (!options.pwd) {
      toast.add({ title: '请输入文件密码', color: 'warning' })
      return
    }
    if (pwdInvalid.value) {
      toast.add({ title: '文件密码只能为字母和数字', color: 'warning' })
      return
    }
  }
  const begin = Date.now()
  await uploader.start({ ...options })
  const seconds = ((Date.now() - begin) / 1000).toFixed(2)
  if (errorCount.value) {
    toast.add({ title: '上传结束', description: `部分文件上传失败，总用时 ${seconds} 秒`, color: 'warning' })
  } else {
    toast.add({ title: '全部上传完成', description: `总用时 ${seconds} 秒`, color: 'success', icon: 'i-lucide-circle-check' })
  }
}

const statusMeta: Record<UploadStatus, { label: string, color: 'neutral' | 'primary' | 'info' | 'success' | 'error' | 'warning' }> = {
  pending: { label: '等待上传', color: 'neutral' },
  hashing: { label: '计算校验', color: 'info' },
  uploading: { label: '上传中', color: 'primary' },
  saving: { label: '保存中', color: 'warning' },
  success: { label: '上传成功', color: 'success' },
  exists: { label: '秒传成功', color: 'success' },
  error: { label: '上传失败', color: 'error' }
}

const isActive = (item: UploadItem) => ['hashing', 'uploading', 'saving'].includes(item.status)
const isDone = (item: UploadItem) => item.status === 'success' || item.status === 'exists'
const detailTo = (item: UploadItem) => ({ path: '/detail', query: { hash: item.hash, ...(item.pwd ? { pwd: item.pwd } : {}) } })
const itemUrl = (item: UploadItem) => fileUrl(item.hash, getExt(item.name), item.pwd)

/** 上传成功的文件，按列表顺序 */
const doneItems = computed(() => items.value.filter(isDone))

/** 复制全部上传成功文件的链接，每行一个 */
async function copyAll() {
  const list = doneItems.value
  if (!list.length) return
  if (await copyText(list.map(itemUrl).join('\n'))) {
    toast.add({ title: `已复制 ${list.length} 个文件的链接`, color: 'success', icon: 'i-lucide-circle-check' })
  } else {
    toast.add({ title: '复制失败，请逐个复制链接', color: 'error', icon: 'i-lucide-circle-x' })
  }
}

const canRetry = (item: UploadItem) => item.status === 'error' && item.size > 0 && !item.message.startsWith('文件大小超过')
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-6">
    <div class="space-y-4 min-w-0">
      <!-- 选择文件区域 -->
      <UCard :ui="{ body: 'p-3 sm:p-4' }">
        <div
          class="relative rounded-xl border-2 border-dashed px-4 py-10 sm:py-14 text-center transition-colors"
          :class="dragging ? 'border-primary bg-primary/5' : 'border-(--ui-border-accented) hover:border-primary/60'"
          @dragenter.prevent="onDragEnter"
          @dragover.prevent
          @dragleave.prevent="onDragLeave"
          @drop.prevent="onDrop"
        >
          <input ref="fileInput" type="file" multiple class="hidden" @change="onFileChange">
          <input ref="folderInput" type="file" webkitdirectory multiple class="hidden" @change="onFolderChange">
          <div class="mx-auto mb-4 size-16 rounded-full bg-primary/10 flex items-center justify-center">
            <UIcon :name="dragging ? 'i-lucide-hand' : 'i-lucide-cloud-upload'" class="size-9 text-primary" />
          </div>
          <h2 class="text-lg sm:text-xl font-semibold text-(--ui-text-toned)">
            {{ dragging ? '释放鼠标添加文件' : '选择文件 / Ctrl+V 粘贴 / 拖拽到此处' }}
          </h2>
          <p class="mt-1 text-sm text-(--ui-text-muted)">支持一次选择多个文件，添加后点击“开始上传”依次上传</p>
          <div class="mt-6 flex flex-wrap justify-center gap-3">
            <UButton v-if="forbid" to="/login" size="xl" icon="i-lucide-log-in" label="请先登录" />
            <template v-else>
              <UButton size="xl" icon="i-lucide-file-up" label="选择文件" @click="pickFiles" />
              <UButton size="xl" variant="soft" icon="i-lucide-folder-up" label="选择文件夹" @click="pickFolder" />
            </template>
          </div>
        </div>

        <div class="mt-4 flex min-h-8 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-6">
          <div v-if="storageItems.length > 1" class="flex items-center gap-2">
            <span class="text-sm whitespace-nowrap text-(--ui-text-toned)">存储位置</span>
            <USelect v-model="options.storage_id" :items="storageItems" icon="i-lucide-hard-drive" :disabled="running" class="w-full sm:w-48" />
          </div>
          <UCheckbox v-model="options.show" label="在首页文件列表显示" :disabled="running" />
          <UCheckbox v-model="options.ispwd" label="设定密码" :disabled="running" />
          <div v-if="options.ispwd" class="flex w-full flex-col gap-1 sm:w-auto sm:flex-row sm:items-center sm:gap-2">
            <UInput v-model="options.pwd" placeholder="密码只能为字母或数字" autocomplete="off" :disabled="running" :color="pwdInvalid ? 'error' : undefined" :highlight="pwdInvalid" class="w-full sm:w-56" />
          </div>
        </div>
      </UCard>

      <!-- 待上传列表 -->
      <UCard v-if="items.length" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 class="font-semibold flex items-center gap-2">
                <UIcon name="i-lucide-list-checks" class="size-5 text-primary" />
                上传列表
                <UBadge color="neutral" variant="subtle">{{ items.length }}</UBadge>
              </h3>
              <p class="mt-1 text-xs text-(--ui-text-muted)">
                共 {{ sizeFormat(totalSize) }}，已完成 {{ doneCount }} 个<template v-if="errorCount">，失败 {{ errorCount }} 个</template><template v-if="pendingCount">，待上传 {{ pendingCount }} 个</template>
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <UButton v-if="doneItems.length > 1 && !running" color="neutral" variant="outline" icon="i-lucide-copy" label="复制全部链接" @click="copyAll" />
              <UButton color="neutral" variant="outline" icon="i-lucide-eraser" label="清空" :disabled="running" @click="uploader.clear()" />
              <UButton
                icon="i-lucide-upload"
                :label="running ? '正在上传' : '开始上传'"
                :loading="running"
                :disabled="!pendingCount || running"
                @click="startUpload"
              />
            </div>
          </div>
        </template>

        <TransitionGroup tag="ul" name="list" class="divide-y divide-(--ui-border)">
          <li v-for="item in items" :key="item.id" class="relative overflow-hidden px-4 py-3">
            <!-- 进度显示为条目背景：从左向右填充，底边加一条实色线；保存中（进度未知）整行闪烁
                 按状态加 key，切换阶段（计算校验 → 上传中）时从 0 重新开始，不会出现从 100% 倒退的动画 -->
            <div
              v-if="isActive(item)"
              :key="item.status"
              class="pointer-events-none absolute inset-y-0 left-0 border-b-2 transition-[width] duration-300 ease-out"
              :class="[
                item.status === 'hashing' ? 'bg-info/10 border-info' : 'bg-primary/10 border-primary',
                item.status === 'saving' && 'animate-pulse'
              ]"
              :style="{ width: `${item.status === 'saving' ? 100 : item.progress}%` }"
            />
            <div class="relative flex items-start gap-3">
              <div class="size-10 shrink-0 rounded-lg bg-primary/10 flex items-center justify-center">
                <UIcon :name="typeIcon(getExt(item.name))" class="size-6 text-primary" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <p class="font-medium break-all line-clamp-2 text-sm sm:text-base">{{ item.name }}</p>
                  <UBadge :color="statusMeta[item.status].color" variant="subtle" class="shrink-0">
                    {{ statusMeta[item.status].label }}<template v-if="item.status === 'hashing' || item.status === 'uploading'"> {{ item.progress }}%</template>
                  </UBadge>
                </div>
                <!-- 第二行固定为按钮高度（min-h-6），上传完成出现按钮时行高不变，列表不会跳动 -->
                <div class="mt-1 flex min-h-6 items-center justify-between gap-2">
                  <div class="flex min-w-0 flex-wrap items-center gap-x-3 text-xs text-(--ui-text-muted)">
                    <span>{{ sizeFormat(item.size) }}</span>
                    <span v-if="item.status === 'uploading' && item.speed">{{ item.speed }}</span>
                    <span v-if="item.message" :class="item.status === 'error' ? 'text-(--ui-error)' : 'text-(--ui-success)'">{{ item.message }}</span>
                  </div>
                  <div v-if="isDone(item)" class="flex shrink-0 gap-1">
                    <UButton :to="detailTo(item)" size="xs" variant="soft" icon="i-lucide-eye" aria-label="查看">
                      <span class="hidden sm:inline">查看</span>
                    </UButton>
                    <UButton size="xs" variant="soft" color="neutral" icon="i-lucide-copy" aria-label="复制链接" @click="copy(itemUrl(item))">
                      <span class="hidden sm:inline">复制链接</span>
                    </UButton>
                  </div>
                </div>
              </div>
              <div class="flex shrink-0 gap-1">
                <UButton v-if="canRetry(item) && !running" size="xs" color="neutral" variant="ghost" icon="i-lucide-rotate-ccw" aria-label="重试" @click="uploader.retry(item.id)" />
                <UButton v-if="!isActive(item)" size="xs" color="neutral" variant="ghost" icon="i-lucide-x" aria-label="移除" @click="uploader.remove(item.id)" />
              </div>
            </div>
          </li>
        </TransitionGroup>
      </UCard>
    </div>

    <!-- 上传提示 -->
    <aside>
      <UCard>
        <template #header>
          <h3 class="font-semibold flex items-center gap-2">
            <UIcon name="i-lucide-circle-alert" class="size-5 text-primary" /> 上传提示
          </h3>
        </template>
        <ul class="space-y-3 text-sm text-(--ui-text-toned)">
          <li class="flex gap-2">
            <UIcon name="i-lucide-shield-alert" class="size-4 mt-0.5 shrink-0 text-warning" />
            <span>您的IP是 <b>{{ site.clientip }}</b>，请不要上传违规文件！</span>
          </li>
          <li class="flex gap-2">
            <UIcon name="i-lucide-hard-drive-upload" class="size-4 mt-0.5 shrink-0 text-info" />
            <span v-if="site.upload_size > 0">上传无格式限制，当前服务器单个文件上传最大支持 <b>{{ site.upload_size }}MB</b>！</span>
            <span v-else>上传无格式限制，无大小限制！</span>
          </li>
          <li class="flex gap-2">
            <UIcon name="i-lucide-zap" class="size-4 mt-0.5 shrink-0 text-success" />
            <span>上传前会计算文件校验值，本站已存在的文件可极速秒传。</span>
          </li>
          <li v-if="site.videoreview" class="flex gap-2">
            <UIcon name="i-lucide-clock" class="size-4 mt-0.5 shrink-0 text-warning" />
            <span>当前网站已开启视频文件审核，如果上传的是视频文件，需要等待审核通过后才能下载和播放。</span>
          </li>
        </ul>
      </UCard>
    </aside>
  </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
  transition: all 0.25s ease;
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateX(-12px);
}
</style>
