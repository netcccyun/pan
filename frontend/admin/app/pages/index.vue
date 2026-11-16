<script setup lang="ts">
useHead({ title: '后台首页' })

const { get } = useApi()
const toast = useToast()

interface Count {
  count1: number
  count2: number
  count3: number
  count4: number
  server: Record<string, string>
  version: string
  checkupdate: string
}

const data = ref<Count | null>(null)
const updateHtml = ref('')
const updateError = ref(false)

const cards = computed(() => [
  { label: '文件总数', value: data.value?.count1, icon: 'i-lucide-cloud', color: 'text-primary bg-primary/10', to: '/file' },
  { label: '今日上传文件', value: data.value?.count2, icon: 'i-lucide-cloud-upload', color: 'text-success bg-success/10', to: '/file' },
  { label: '昨日上传文件', value: data.value?.count3, icon: 'i-lucide-inbox', color: 'text-warning bg-warning/10', to: '/file' },
  { label: '用户总数', value: data.value?.count4, icon: 'i-lucide-users', color: 'text-error bg-error/10', to: '/user' }
])

const serverInfo = computed(() => {
  const s = data.value?.server
  if (!s) return []
  return [
    ['PHP 版本', s.php],
    ['MySQL 版本', s.mysql],
    ['WEB 软件', s.software],
    ['服务器时间', s.time],
    ['POST 许可', s.post_max_size],
    ['文件上传许可', s.upload_max_filesize]
  ]
})

/** 版本检查接口只支持 JSONP，回调函数名固定为 callback */
function jsonp(url: string): Promise<any> {
  return new Promise((resolve, reject) => {
    const w = window as any
    const script = document.createElement('script')
    const cleanup = () => {
      delete w.callback
      script.remove()
    }
    const timer = setTimeout(() => {
      cleanup()
      reject(new Error('timeout'))
    }, 10000)
    w.callback = (res: any) => {
      clearTimeout(timer)
      cleanup()
      resolve(res)
    }
    script.onerror = () => {
      clearTimeout(timer)
      cleanup()
      reject(new Error('error'))
    }
    script.src = `${url}${url.includes('?') ? '&' : '?'}callback=callback`
    document.head.appendChild(script)
  })
}

onMounted(async () => {
  try {
    data.value = await get('getcount') as unknown as Count
  } catch (e: any) {
    toast.add({ title: e?.message || '加载失败', color: 'error' })
    return
  }
  try {
    const res = await jsonp(data.value.checkupdate)
    updateHtml.value = res?.msg || ''
  } catch {
    updateError.value = true
  }
})
</script>

<template>
  <AdminPage id="home" title="后台首页" icon="i-lucide-house">
    <div class="space-y-6">
      <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <NuxtLink v-for="card in cards" :key="card.label" :to="card.to" class="group">
          <UCard class="h-full transition group-hover:ring-(--ui-primary)/50" :ui="{ body: 'p-4 sm:p-5' }">
            <div class="flex items-center gap-3 sm:gap-4">
              <div class="flex size-11 shrink-0 items-center justify-center rounded-xl sm:size-12" :class="card.color">
                <UIcon :name="card.icon" class="size-6" />
              </div>
              <div class="min-w-0">
                <p class="truncate text-xs text-(--ui-text-muted) sm:text-sm">{{ card.label }}</p>
                <p class="text-2xl font-semibold tabular-nums">
                  <USkeleton v-if="!data" class="mt-1 h-7 w-12" />
                  <template v-else>{{ card.value }}</template>
                </p>
              </div>
            </div>
          </UCard>
        </NuxtLink>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <UCard class="lg:col-span-2" :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <h3 class="flex items-center gap-2 font-semibold"><UIcon name="i-lucide-server" class="size-5 text-primary" />服务器信息</h3>
          </template>
          <dl class="divide-y divide-(--ui-border)">
            <template v-if="data">
              <div v-for="[label, value] in serverInfo" :key="label" class="flex gap-4 px-4 py-3 text-sm sm:px-6">
                <dt class="w-28 shrink-0 text-(--ui-text-muted)">{{ label }}</dt>
                <dd class="min-w-0 break-all">{{ value }}</dd>
              </div>
            </template>
            <div v-else v-for="i in 6" :key="i" class="px-4 py-3 sm:px-6"><USkeleton class="h-5 w-2/3" /></div>
          </dl>
        </UCard>

        <UCard>
          <template #header>
            <h3 class="flex items-center gap-2 font-semibold"><UIcon name="i-lucide-badge-info" class="size-5 text-primary" />版本信息</h3>
          </template>
          <div class="space-y-3 text-sm">
            <p v-if="data && !updateHtml"><span class="text-(--ui-text-muted)">当前版本：</span>{{ data.version }}</p>
            <ul v-if="updateHtml" class="update-info" v-html="updateHtml" />
            <p v-else-if="updateError" class="text-(--ui-text-muted)">检查更新失败</p>
            <USkeleton v-else class="h-5 w-3/4" />
          </div>
        </UCard>
      </div>
    </div>
  </AdminPage>
</template>

<style scoped>
/* 远程更新检查返回的 HTML 仍使用 Bootstrap 的 list-group 结构 */
.update-info {
  margin: 0;
  padding: 0;
}
.update-info :deep(.list-group-item) {
  position: relative;
  display: block;
  padding: 0.625rem 0.875rem;
  margin-bottom: -1px;
  list-style: none;
  background-color: var(--ui-bg);
  border: 1px solid var(--ui-border);
}
.update-info :deep(.list-group-item:first-child) {
  border-top-left-radius: 0.5rem;
  border-top-right-radius: 0.5rem;
}
.update-info :deep(.list-group-item:last-child) {
  margin-bottom: 0;
  border-bottom-left-radius: 0.5rem;
  border-bottom-right-radius: 0.5rem;
}
.update-info :deep(a) {
  color: var(--ui-primary);
  text-decoration: underline;
}
</style>
