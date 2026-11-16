<script setup lang="ts">
import type { NavigationMenuItem, DropdownMenuItem } from '@nuxt/ui'

const { site, isLogin, load, error } = useSite()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { rawPost } = useApi()

const navItems = computed<NavigationMenuItem[]>(() => {
  const items: NavigationMenuItem[] = [
    { label: '文件列表', icon: 'i-lucide-list', to: '/', active: route.path === '/' },
    { label: '上传文件', icon: 'i-lucide-upload', to: '/upload', active: route.path === '/upload' },
    { label: '我的文件', icon: 'i-lucide-folder-open', to: '/mine', active: route.path === '/mine' }
  ]
  if (route.path === '/detail') {
    items.push({ label: '文件查看', icon: 'i-lucide-file', to: route.fullPath, active: true })
  }
  return items
})

const userIcon = computed(() => (site.value.user?.type === 'qq' ? 'i-simple-icons-qq' : 'i-simple-icons-wechat'))

async function logout() {
  if (!(await confirm({ title: '退出登录', description: '是否确定退出登录？', confirmText: '退出' }))) return
  try {
    const res = await rawPost('logout', {}, 'login.php')
    if (res.code !== 0) throw new Error(res.msg || '退出失败')
    await load()
    toast.add({ title: '您已成功注销本次登录', color: 'success', icon: 'i-lucide-circle-check' })
    router.push('/login')
  } catch (e: any) {
    toast.add({ title: e?.message || '退出失败', color: 'error' })
  }
}

const userMenu = computed<DropdownMenuItem[][]>(() => [
  [{ label: site.value.user?.nickname || '用户', type: 'label', avatar: site.value.user?.faceimg ? { src: site.value.user.faceimg } : undefined }],
  [{ label: '退出登录', icon: 'i-lucide-log-out', color: 'error', onSelect: logout }]
])

const year = new Date().getFullYear()

const tongjiInjected = useState('tongji-injected', () => false)
onMounted(() => {
  if (tongjiInjected.value || !site.value.tongji) return
  tongjiInjected.value = true
  const tpl = document.createElement('div')
  tpl.innerHTML = site.value.tongji
  tpl.querySelectorAll('script').forEach((old) => {
    const script = document.createElement('script')
    for (const attr of Array.from(old.attributes)) script.setAttribute(attr.name, attr.value)
    script.text = old.text
    document.body.appendChild(script)
    old.remove()
  })
  const holder = document.getElementById('tongji-holder')
  if (holder && tpl.innerHTML.trim()) holder.innerHTML = tpl.innerHTML
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-(--ui-bg-muted)">
    <UHeader :to="'/'" mode="slideover" :ui="{ root: 'bg-(--ui-bg)/85' }">
      <template #title>
        <span class="flex items-center gap-2 font-bold text-lg">
          <UIcon name="i-lucide-cloud" class="size-6 text-primary" />
          <span class="truncate max-w-[60vw]">{{ site.title }}</span>
        </span>
      </template>

      <UNavigationMenu :items="navItems" variant="link" />

      <template #right>
        <ColorModeToggle />
        <template v-if="site.userlogin">
          <UDropdownMenu v-if="isLogin" :items="userMenu" :content="{ align: 'end' }">
            <UButton color="neutral" variant="ghost" :icon="userIcon" :label="site.user?.nickname" trailing-icon="i-lucide-chevron-down" class="hidden sm:inline-flex" />
            <UButton color="neutral" variant="ghost" :icon="userIcon" class="sm:hidden" aria-label="用户菜单" />
          </UDropdownMenu>
          <UButton v-else to="/login" color="neutral" variant="ghost" icon="i-lucide-circle-user-round" :active="route.path === '/login'">
            <span class="hidden sm:inline">未登录</span>
          </UButton>
        </template>
      </template>

      <template #body>
        <UNavigationMenu :items="navItems" orientation="vertical" class="-mx-2.5" />
      </template>
    </UHeader>

    <UMain class="flex-1">
      <UContainer class="py-4 sm:py-6">
        <UAlert
          v-if="error"
          class="mb-4"
          color="error"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          title="站点配置加载失败"
          :description="error"
        />
        <slot />
      </UContainer>
    </UMain>

    <footer class="border-t border-(--ui-border) bg-(--ui-bg) py-5 text-center text-sm text-(--ui-text-muted)">
      <UContainer>
        Copyright &copy; {{ year }} <NuxtLink to="/" class="hover:text-primary">{{ site.title }}</NuxtLink>
        <span id="tongji-holder" />
      </UContainer>
    </footer>
  </div>
</template>
