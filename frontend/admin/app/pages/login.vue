<script setup lang="ts">
definePageMeta({ layout: 'blank' })
useHead({ title: '管理员登录' })

const route = useRoute()
const { status, load } = useAdmin()
const { get, post } = useApi()
const toast = useToast()

const form = reactive({ user: '', pass: '', captcha: '' })
const loading = ref(false)

// 图形验证码：服务器没有 GD 库时后端返回 enabled=false，不显示验证码
const captcha = reactive({ enabled: false, image: '', loading: false })
const captchaInput = useTemplateRef('captchaInput')

async function loadCaptcha() {
  captcha.loading = true
  try {
    const res = await get('captcha')
    captcha.enabled = !!res.enabled
    captcha.image = res.image || ''
  } catch (e: any) {
    toast.add({ title: e?.message || '验证码加载失败', color: 'error' })
  } finally {
    captcha.loading = false
  }
}

onMounted(loadCaptcha)

async function onSubmit() {
  if (!form.user || !form.pass) {
    toast.add({ title: '请输入用户名和密码', color: 'warning' })
    return
  }
  if (captcha.enabled && !form.captcha.trim()) {
    toast.add({ title: '请输入验证码', color: 'warning' })
    captchaInput.value?.inputRef?.focus()
    return
  }
  loading.value = true
  try {
    await post('login', { ...form })
    await load()
    toast.add({ title: '登录成功', color: 'success', icon: 'i-lucide-circle-check' })
    const redirect = String(route.query.redirect || '/')
    await navigateTo(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/')
  } catch (e: any) {
    toast.add({ title: e?.message || '登录失败', color: 'error' })
    // 验证码提交一次就会失效，无论哪种错误都要换一张
    form.captcha = ''
    if (captcha.enabled) {
      loadCaptcha()
      if (e?.data?.captcha_error) captchaInput.value?.inputRef?.focus()
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center bg-linear-to-br from-primary-500/15 via-(--ui-bg) to-sky-500/15 p-4">
    <div class="absolute top-4 right-4">
      <ColorModeToggle />
    </div>
    <UCard class="w-full max-w-sm shadow-lg" :ui="{ body: 'p-6 sm:p-8' }">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-2xl bg-primary/10">
          <UIcon name="i-lucide-shield-check" class="size-8 text-primary" />
        </div>
        <h1 class="text-xl font-semibold">管理员登录</h1>
        <p v-if="status.title" class="mt-1 text-sm text-(--ui-text-muted)">{{ status.title }}管理中心</p>
      </div>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <UFormField label="用户名">
          <UInput v-model="form.user" icon="i-lucide-user" size="lg" class="w-full" placeholder="用户名" autocomplete="username" autofocus />
        </UFormField>
        <UFormField label="密码">
          <UInput v-model="form.pass" type="password" icon="i-lucide-lock" size="lg" class="w-full" placeholder="密码" autocomplete="current-password" />
        </UFormField>
        <UFormField v-if="captcha.enabled" label="验证码">
          <div class="flex gap-2">
            <UInput
              ref="captchaInput"
              v-model="form.captcha"
              icon="i-lucide-shield-check"
              size="lg"
              class="min-w-0 flex-1"
              :ui="{ base: 'h-full' }"
              placeholder="验证码"
              autocomplete="off"
              :maxlength="4"
            />
            <UTooltip text="看不清？点击换一张">
              <button
                type="button"
                class="relative h-[50px] w-[130px] shrink-0 overflow-hidden rounded-md ring ring-(--ui-border)"
                aria-label="换一张验证码"
                :disabled="captcha.loading"
                @click="loadCaptcha"
              >
                <img v-if="captcha.image" :src="captcha.image" alt="验证码" class="size-full">
                <span v-if="captcha.loading" class="absolute inset-0 flex items-center justify-center bg-(--ui-bg)/60">
                  <UIcon name="i-lucide-loader-circle" class="size-5 animate-spin text-(--ui-text-muted)" />
                </span>
              </button>
            </UTooltip>
          </div>
        </UFormField>
        <UButton type="submit" block size="lg" label="登录" :loading="loading" class="mt-2" />
      </form>
      <div class="mt-6 text-center">
        <UButton to="../" external variant="link" color="neutral" size="sm" icon="i-lucide-arrow-left" label="返回网站首页" />
      </div>
    </UCard>
  </div>
</template>
