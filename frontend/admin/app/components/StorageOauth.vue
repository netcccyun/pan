<script setup lang="ts">
import type { StorageOauth } from '~/utils/types'

/**
 * 存储账号授权（如 OneDrive）
 * 用表单中尚未保存的参数发起授权，授权结果填回表单，由管理员点击保存
 */
const props = defineProps<{ type: string, config: Record<string, string>, oauth: StorageOauth }>()
const emit = defineEmits<{ authorized: [values: Record<string, string>] }>()

const { post } = useApi()
const toast = useToast()
const copy = useCopy()

const isHttps = location.protocol === 'https:'
const autoUri = `${location.origin}${location.pathname.replace(/[^/]*$/, '')}oauth.php`
const mode = ref<'auto' | 'manual'>(isHttps ? 'auto' : 'manual')
const modeItems = [
  { label: '自动回调', value: 'auto', description: '授权后自动返回本站，需要网站使用 HTTPS' },
  { label: '手动粘贴', value: 'manual', description: '授权后复制浏览器地址栏中的网址粘贴回来' }
]
const redirectUri = computed(() => (mode.value === 'auto' ? autoUri : 'http://localhost'))

const starting = ref(false)
const waitingState = ref('')
const pasteUrl = ref('')
const submitting = ref(false)

let timer: ReturnType<typeof setInterval> | undefined
let deadline = 0

function stopPolling() {
  if (timer) clearInterval(timer)
  timer = undefined
}

function finish(values: Record<string, string>) {
  stopPolling()
  waitingState.value = ''
  pasteUrl.value = ''
  emit('authorized', values)
  toast.add({ title: '授权成功，请点击保存', color: 'success', icon: 'i-lucide-circle-check' })
}

function fail(message: string) {
  stopPolling()
  waitingState.value = ''
  toast.add({ title: message || '授权失败', color: 'error' })
}

/** 查询回调页面保存的授权结果（授权窗口可能与本页失去联系，不能只依赖 postMessage） */
function startPolling(state: string) {
  stopPolling()
  deadline = Date.now() + 10 * 60 * 1000
  timer = setInterval(async () => {
    if (waitingState.value !== state || Date.now() > deadline) {
      stopPolling()
      if (waitingState.value === state) waitingState.value = ''
      return
    }
    try {
      const res = await post('storageOauthResult', { state })
      if (res.code === 0 && waitingState.value === state) finish(res.config || {})
    } catch (e: any) {
      if (waitingState.value === state) fail(e?.message)
    }
  }, 2000)
}

function onMessage(e: MessageEvent) {
  if (e.origin !== location.origin) return
  const data = e.data
  if (!data || data.source !== 'pan-storage-oauth' || !waitingState.value || data.state !== waitingState.value) return
  if (data.ok) finish(data.config || {})
  else fail(data.msg)
}

onMounted(() => window.addEventListener('message', onMessage))
onBeforeUnmount(() => {
  window.removeEventListener('message', onMessage)
  stopPolling()
})

async function start() {
  // 先同步打开窗口，避免请求返回后再打开被浏览器拦截
  const win = window.open('', '_blank')
  starting.value = true
  try {
    const res = await post('storageOauthUrl', {
      type: props.type,
      config: JSON.stringify(props.config),
      redirect_uri: redirectUri.value
    })
    waitingState.value = res.state
    if (win) win.location.href = res.url
    else window.open(res.url, '_blank')
    if (mode.value === 'auto') startPolling(res.state)
  } catch (e: any) {
    win?.close()
    toast.add({ title: e?.message || '获取授权地址失败', color: 'error' })
  } finally {
    starting.value = false
  }
}

async function submitPaste() {
  if (!pasteUrl.value.trim()) {
    toast.add({ title: '请粘贴授权后浏览器地址栏中的网址', color: 'warning' })
    return
  }
  submitting.value = true
  try {
    const res = await post('storageOauthToken', { url: pasteUrl.value.trim() })
    finish(res.config || {})
  } catch (e: any) {
    toast.add({ title: e?.message || '授权失败', color: 'error' })
  } finally {
    submitting.value = false
  }
}

watch(mode, () => {
  stopPolling()
  waitingState.value = ''
})
</script>

<template>
  <div class="space-y-4 rounded-lg border border-(--ui-border) p-4">
    <div class="flex items-center gap-2 font-medium">
      <UIcon name="i-lucide-key-round" class="size-4 text-primary" />
      账号授权
    </div>

    <ol v-if="oauth.steps.length" class="list-decimal space-y-1 ps-5 text-sm text-(--ui-text-muted)">
      <li v-for="(step, i) in oauth.steps" :key="i">{{ step }}</li>
    </ol>

    <UFormField label="授权方式">
      <URadioGroup v-model="mode" :items="modeItems" orientation="horizontal" variant="table" class="w-full" :ui="{ fieldset: 'grid sm:grid-cols-2' }" />
    </UFormField>

    <UAlert
      v-if="mode === 'auto' && !isHttps"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      description="授权服务要求回调地址使用 HTTPS（localhost 除外），当前网站不是 HTTPS，请使用手动粘贴方式。"
    />

    <UFormField
      label="回调地址（重定向 URI）"
      :help="mode === 'manual' ? '授权后浏览器会跳转到一个打不开的 localhost 页面，这是正常的，复制此时地址栏中的完整网址粘贴到下方即可' : '需要和应用中添加的重定向 URI 完全一致'"
    >
      <UFieldGroup class="w-full">
        <UInput :model-value="redirectUri" readonly class="min-w-0 flex-1" :ui="{ base: 'font-mono text-sm' }" @focus="($event.target as HTMLInputElement).select()" />
        <UButton color="neutral" variant="outline" icon="i-lucide-copy" label="复制" @click="copy(redirectUri)" />
      </UFieldGroup>
    </UFormField>

    <div class="flex flex-wrap items-center gap-3">
      <UButton icon="i-lucide-log-in" :label="config.refresh_token ? '重新授权' : '授权登录'" :loading="starting" @click="start" />
      <span v-if="waitingState && mode === 'auto'" class="flex items-center gap-1.5 text-sm text-(--ui-text-muted)">
        <UIcon name="i-lucide-loader-circle" class="size-4 animate-spin" />
        请在新窗口中完成授权…
      </span>
      <UBadge v-else-if="config.refresh_token" color="success" variant="subtle" icon="i-lucide-circle-check" label="已获取令牌" />
    </div>

    <UFieldGroup v-if="mode === 'manual' && waitingState" class="w-full">
      <UInput v-model="pasteUrl" placeholder="粘贴 http://localhost/?code=… 开头的完整网址" class="min-w-0 flex-1" @keydown.enter.prevent="submitPaste" />
      <UButton label="确定" :loading="submitting" @click="submitPaste" />
    </UFieldGroup>
  </div>
</template>
