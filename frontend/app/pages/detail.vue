<script setup lang="ts">
import type { TabsItem } from '@nuxt/ui'

interface FileInfo {
  id: number
  name: string
  type: string
  size: number
  hash: string
  addtime: string
  ip: string
  count: number
  block: number
  haspwd: number
  view_type: 'image' | 'audio' | 'video' | 'office' | 'text' | 'other'
  viewurl: string
  downurl: string
  pageurl: string
  playerurl: string
  office_url: string | null
  /** 文本/代码文件是否可以在线预览（不超过大小限制） */
  text_view: number
  htmlcode: string
  htmlcode2: string | null
  ubbcode: string
  is_mine: number
  /** 只有文件所有者才返回 */
  hide: number | null
  pwd: string | null
}

const route = useRoute()
const router = useRouter()
const { rawGet, post, csrf } = useApi()
const { site } = useSite()
const toast = useToast()
const confirm = useConfirm()

const hash = computed(() => String(route.query.hash || ''))
const pwd = computed(() => String(route.query.pwd || ''))

const loading = ref(true)
const info = ref<FileInfo | null>(null)
const errorMsg = ref('')
const needPwd = ref(false)
const pwdInput = ref('')
const pwdError = ref('')

useHead({ title: () => info.value?.name || '文件查看' })

async function load() {
  if (!hash.value) {
    router.replace('/')
    return
  }
  loading.value = true
  errorMsg.value = ''
  needPwd.value = false
  try {
    const res = await rawGet('fileinfo', { hash: hash.value, pwd: pwd.value || undefined })
    if (res.code === 0) {
      info.value = res.data
    } else if (res.code === -2) {
      info.value = null
      needPwd.value = true
      pwdError.value = pwd.value ? (res.msg || '文件密码错误') : ''
    } else {
      info.value = null
      errorMsg.value = res.msg || '文件不存在'
    }
  } catch (e: any) {
    errorMsg.value = e?.message || '加载失败'
  } finally {
    loading.value = false
  }
}

watch(() => [hash.value, pwd.value], load, { immediate: true })

// 文本/代码文件在线预览
const textOpen = ref(false)
watch(hash, () => { textOpen.value = false })

async function loadText() {
  const res = await rawGet('filecontent', { hash: hash.value, pwd: pwd.value || undefined })
  if (res.code !== 0) throw new Error(res.msg || '读取文件失败')
  return String(res.content ?? '')
}

function submitPwd() {
  const value = pwdInput.value.trim()
  if (!value) {
    pwdError.value = '请输入密码'
    return
  }
  router.replace({ query: { ...route.query, pwd: value } })
}

const viewMeta = computed(() => {
  switch (info.value?.view_type) {
    case 'image': return { icon: 'i-lucide-image', title: '图片查看器', linkTitle: '图片链接' }
    case 'audio': return { icon: 'i-lucide-music', title: '音乐播放器', linkTitle: '音乐链接' }
    case 'video': return { icon: 'i-lucide-video', title: '视频播放器', linkTitle: '视频链接' }
    case 'text': return textOpen.value ? { icon: 'i-lucide-file-code', title: '在线预览', linkTitle: '' } : { icon: 'i-lucide-file', title: '文件查看', linkTitle: '' }
    default: return { icon: 'i-lucide-file', title: '文件查看', linkTitle: '' }
  }
})

const isMedia = computed(() => ['image', 'audio', 'video'].includes(info.value?.view_type || ''))

const tabs = computed<TabsItem[]>(() => {
  const items: TabsItem[] = [
    { label: '文件外链', icon: 'i-lucide-link', slot: 'link' as const },
    { label: '代码调用', icon: 'i-lucide-code', slot: 'code' as const },
    { label: '文件详情', icon: 'i-lucide-info', slot: 'info' as const }
  ]
  if (info.value?.is_mine) items.push({ label: '管理', icon: 'i-lucide-settings', slot: 'manage' as const })
  return items
})

const editOpen = ref(false)
function onEdited(result: { pwd: string }) {
  // 密码变化后分享链接也会变化，带上新密码重新加载
  if (result.pwd !== (info.value?.pwd || '')) router.replace({ query: { ...route.query, pwd: result.pwd || undefined } })
  else load()
}

const deleting = ref(false)
async function deleteFile() {
  if (!info.value) return
  const ok = await confirm({ title: '删除文件', description: '删除文件后不可恢复，确定删除吗？', confirmText: '删除', color: 'error' })
  if (!ok) return
  deleting.value = true
  try {
    const token = await csrf()
    await post('deleteFile', { hash: info.value.hash, csrf_token: token })
    toast.add({ title: '删除成功', color: 'success', icon: 'i-lucide-circle-check' })
    router.replace('/mine')
  } catch (e: any) {
    toast.add({ title: e?.message || '删除失败', color: 'error' })
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-6">
    <div class="space-y-4 min-w-0">
      <!-- 加载中 -->
      <UCard v-if="loading">
        <div class="space-y-4">
          <USkeleton class="h-6 w-40" />
          <USkeleton class="h-64 w-full" />
        </div>
      </UCard>

      <!-- 需要密码 -->
      <UCard v-else-if="needPwd">
        <div class="max-w-sm mx-auto py-8 text-center">
          <div class="mx-auto mb-4 size-14 rounded-full bg-primary/10 flex items-center justify-center">
            <UIcon name="i-lucide-lock" class="size-7 text-primary" />
          </div>
          <h3 class="text-lg font-semibold">该文件已加密</h3>
          <p class="mt-1 text-sm text-(--ui-text-muted)">请输入密码后查看文件</p>
          <div class="mt-6 flex gap-2">
            <UInput
              v-model="pwdInput"
              type="text"
              name="file-access-code"
              placeholder="请输入文件密码"
              autocomplete="off"
              autocapitalize="off"
              autocorrect="off"
              spellcheck="false"
              data-1p-ignore
              data-lpignore="true"
              data-form-type="other"
              autofocus
              class="file-pwd flex-1"
              :color="pwdError ? 'error' : undefined"
              :highlight="!!pwdError"
              :ui="{ base: '[-webkit-text-security:disc]' }"
              @keydown.enter.prevent="submitPwd"
            />
            <UButton label="确定" @click="submitPwd" />
          </div>
          <p v-if="pwdError" class="mt-2 text-sm text-(--ui-error) text-left">{{ pwdError }}</p>
        </div>
      </UCard>

      <!-- 出错 -->
      <UCard v-else-if="errorMsg || !info">
        <UEmpty icon="i-lucide-file-x" :title="errorMsg || '文件不存在'" :actions="[{ label: '返回首页', to: '/', icon: 'i-lucide-house' }]" />
      </UCard>

      <template v-else>
        <!-- 预览 -->
        <UCard :ui="{ body: 'p-3 sm:p-6 min-h-60 sm:min-h-90 flex flex-col justify-center' }">
          <template #header>
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold flex items-center gap-2 min-w-0">
                <UIcon :name="viewMeta.icon" class="size-5 text-primary shrink-0" />
                <span class="shrink-0">{{ viewMeta.title }}</span>
                <span class="text-(--ui-text-muted) font-normal truncate hidden sm:inline">· {{ info.name }}</span>
              </h3>
              <div v-if="textOpen" class="flex shrink-0 gap-2">
                <UButton :href="info.downurl" size="sm" variant="soft" icon="i-lucide-download" label="下载" external />
                <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-x" label="收起" @click="textOpen = false" />
              </div>
            </div>
          </template>

          <div v-if="info.view_type === 'image'" class="text-center">
            <a :href="info.viewurl" target="_blank" title="点击查看原图">
              <img :src="info.viewurl" :alt="info.name" class="mx-auto max-h-[60vh] sm:max-h-140 max-w-full object-contain rounded-md">
            </a>
          </div>

          <div v-else-if="info.view_type === 'audio' && info.block === 0" class="w-full">
            <AudioPlayer :src="info.viewurl" :name="info.name" />
          </div>

          <div v-else-if="info.view_type === 'video' && info.block === 0">
            <VideoPlayer :src="info.viewurl" :type="info.type" />
          </div>

          <TextPreview v-else-if="textOpen && info.text_view" :type="info.type" :load="loadText" />

          <div v-else class="py-8 text-center">
            <div class="mx-auto mb-4 size-20 rounded-2xl bg-primary/10 flex items-center justify-center">
              <UIcon :name="typeIcon(info.type)" class="size-11 text-primary" />
            </div>
            <p class="font-medium break-all px-2">{{ info.name }}<span class="text-(--ui-text-muted)">（{{ sizeFormat(info.size) }}）</span></p>
            <template v-if="info.block === 2">
              <UAlert class="mt-4 max-w-md mx-auto text-left" color="warning" variant="subtle" icon="i-lucide-clock" title="文件审核中" description="视频文件需审核通过后才能在线播放和下载，请等待审核通过！" />
            </template>
            <template v-else-if="info.block === 1">
              <UAlert class="mt-4 max-w-md mx-auto text-left" color="error" variant="subtle" icon="i-lucide-shield-alert" title="文件已被冻结" description="该文件因违规已被冻结，无法下载。" />
            </template>
            <div v-else class="mt-6 flex flex-wrap justify-center gap-3">
              <UButton :href="info.downurl" size="xl" icon="i-lucide-download" label="下载文件" external />
              <UButton v-if="info.text_view" size="xl" color="info" variant="soft" icon="i-lucide-eye" label="在线预览" @click="textOpen = true" />
              <UButton v-else-if="info.office_url || info.type === 'pdf'" :href="info.office_url || info.viewurl" target="_blank" size="xl" color="info" variant="soft" icon="i-lucide-eye" label="在线预览" external />
            </div>
            <p v-if="info.view_type === 'text' && !info.text_view && info.block === 0" class="mt-3 text-xs text-(--ui-text-muted)">文件超过 1 MB，不支持在线预览，请下载后查看</p>
          </div>
        </UCard>

        <!-- 外链 / 代码 / 详情 / 管理 -->
        <UCard :ui="{ body: 'p-3 sm:p-5' }">
          <UTabs :items="tabs" variant="link" class="w-full" :ui="{ list: 'overflow-x-auto overflow-y-hidden', trigger: 'shrink-0' }">
            <template #link>
              <div class="space-y-4 pt-4">
                <CopyField v-if="isMedia" :label="viewMeta.linkTitle" :value="info.viewurl" />
                <CopyField label="下载链接" :value="info.downurl" />
                <CopyField label="分享页面" :value="info.pageurl" />
              </div>
            </template>
            <template #code>
              <div class="space-y-4 pt-4">
                <CopyField v-if="info.htmlcode2" label="播放器代码" :value="info.htmlcode2" />
                <CopyField label="HTML代码" :value="info.htmlcode" />
                <CopyField label="UBB代码" :value="info.ubbcode" />
              </div>
            </template>
            <template #info>
              <div class="pt-4 overflow-x-auto">
                <table class="w-full text-sm border border-(--ui-border) rounded-md">
                  <tbody class="divide-y divide-(--ui-border)">
                    <tr class="divide-x divide-(--ui-border)">
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap w-28">文件名称</th>
                      <td class="px-3 py-2 break-all" colspan="3">{{ info.name }}</td>
                    </tr>
                    <tr class="divide-x divide-(--ui-border)">
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap">上传者IP</th>
                      <td class="px-3 py-2">{{ info.ip }}</td>
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap w-28">上传时间</th>
                      <td class="px-3 py-2 whitespace-nowrap">{{ info.addtime }}</td>
                    </tr>
                    <tr class="divide-x divide-(--ui-border)">
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap">下载次数</th>
                      <td class="px-3 py-2">{{ info.count }}</td>
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap">文件大小</th>
                      <td class="px-3 py-2">{{ sizeFormat(info.size) }} <span class="text-(--ui-text-muted)">({{ info.size }} 字节)</span></td>
                    </tr>
                    <tr class="divide-x divide-(--ui-border)">
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap">文件格式</th>
                      <td class="px-3 py-2">{{ info.type || '未知' }}</td>
                      <th class="bg-(--ui-bg-muted) px-3 py-2 text-left font-medium whitespace-nowrap">文件哈希</th>
                      <td class="px-3 py-2 font-mono text-xs break-all">{{ info.hash }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
            <template #manage>
              <div class="pt-6 pb-2 text-center">
                <div class="flex flex-wrap justify-center gap-2">
                  <UButton color="neutral" variant="outline" icon="i-lucide-pencil" label="修改文件信息" @click="editOpen = true" />
                  <UButton color="error" icon="i-lucide-trash-2" label="删除文件" :loading="deleting" @click="deleteFile" />
                </div>
                <p class="mt-3 text-xs text-(--ui-text-muted)">删除后文件将无法恢复<template v-if="!site.islogin">，未登录用户只能管理 7 天内上传的文件</template></p>
              </div>
              <FileEditModal v-model:open="editOpen" :file="info" @saved="onEdited" />
            </template>
          </UTabs>
        </UCard>
      </template>
    </div>

    <!-- 侧栏 -->
    <aside class="space-y-4">
      <UCard v-if="site.gg_file">
        <template #header>
          <h3 class="font-semibold flex items-center gap-2">
            <UIcon name="i-lucide-circle-alert" class="size-5 text-info" /> 提示
          </h3>
        </template>
        <div class="rich-text text-sm leading-relaxed text-(--ui-text-toned)" v-html="site.gg_file" />
      </UCard>
      <UCard v-if="info" class="hidden sm:block">
        <template #header>
          <h3 class="font-semibold flex items-center gap-2">
            <UIcon name="i-lucide-qr-code" class="size-5 text-primary" /> 手机扫码下载
          </h3>
        </template>
        <QrCode :text="info.pageurl" />
      </UCard>
    </aside>
  </div>
</template>
