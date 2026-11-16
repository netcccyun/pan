<script setup lang="ts">
useHead({ title: '用户登录' })

const { site, isLogin } = useSite()
const { rawPost } = useApi()
const router = useRouter()
const toast = useToast()

const pending = ref<'' | 'qq' | 'wx'>('')

onMounted(() => {
  if (isLogin.value) router.replace('/')
})

async function connect(type: 'qq' | 'wx') {
  pending.value = type
  try {
    const res = await rawPost('connect', { type }, 'login.php')
    if (res.code === 0 && res.url) {
      window.location.href = res.url
      return
    }
    toast.add({ title: res.msg || '登录失败', color: 'error' })
  } catch (e: any) {
    toast.add({ title: e?.message || '登录失败', color: 'error' })
  }
  pending.value = ''
}
</script>

<template>
  <div class="flex justify-center py-10 sm:py-20">
    <UCard class="w-full max-w-sm">
      <div class="text-center py-4">
        <div class="mx-auto mb-4 size-14 rounded-full bg-primary/10 flex items-center justify-center">
          <UIcon name="i-lucide-circle-user-round" class="size-8 text-primary" />
        </div>
        <template v-if="!site.userlogin">
          <h2 class="text-lg font-semibold">未开启登录</h2>
          <p class="mt-2 text-sm text-(--ui-text-muted)">当前站点未开启用户登录功能</p>
          <UButton class="mt-6" to="/" label="返回首页" variant="soft" />
        </template>
        <template v-else>
          <h2 class="text-lg font-semibold">请选择登录方式</h2>
          <div class="mt-6 flex flex-col gap-3">
            <UButton
              v-if="site.login_qq"
              block
              size="lg"
              color="info"
              icon="i-simple-icons-qq"
              label="QQ 快捷登录"
              :loading="pending === 'qq'"
              :disabled="!!pending"
              @click="connect('qq')"
            />
            <UButton
              v-if="site.login_wx"
              block
              size="lg"
              color="success"
              icon="i-simple-icons-wechat"
              label="微信快捷登录"
              :loading="pending === 'wx'"
              :disabled="!!pending"
              @click="connect('wx')"
            />
            <p v-if="!site.login_qq && !site.login_wx" class="text-sm text-(--ui-text-muted)">暂无可用的登录方式</p>
          </div>
          <p class="mt-6 text-xs text-(--ui-text-muted)">新用户快捷登录后会自动注册账号</p>
        </template>
      </div>
    </UCard>
  </div>
</template>
