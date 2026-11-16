<script setup lang="ts">
import type { NavigationMenuItem, DropdownMenuItem } from '@nuxt/ui'

const route = useRoute()
const { status, logout } = useAdmin()
const confirm = useConfirm()
const toast = useToast()

const open = ref(false)

const links = computed<NavigationMenuItem[][]>(() => [[
  { label: '后台首页', icon: 'i-lucide-house', to: '/', onSelect: () => { open.value = false } },
  { label: '文件管理', icon: 'i-lucide-folder-open', to: '/file', onSelect: () => { open.value = false } },
  { label: '用户管理', icon: 'i-lucide-users', to: '/user', onSelect: () => { open.value = false } },
  { label: '存储管理', icon: 'i-lucide-hard-drive', to: '/storage', onSelect: () => { open.value = false } },
  {
    label: '系统设置',
    icon: 'i-lucide-settings',
    type: 'trigger',
    defaultOpen: route.path.startsWith('/set'),
    active: route.path.startsWith('/set'),
    children: SETTING_PAGES.map(item => ({
      label: item.label,
      to: `/set/${item.mod}`,
      onSelect: () => { open.value = false }
    }))
  }
], [
  { label: '访问网站', icon: 'i-lucide-external-link', to: '../', target: '_blank', external: true }
]])

async function onLogout() {
  if (!(await confirm({ title: '退出登录', description: '是否确定退出登录？', confirmText: '退出' }))) return
  try {
    await logout()
  } catch (e: any) {
    toast.add({ title: e?.message || '退出失败', color: 'error' })
  }
}

const userMenu = computed<DropdownMenuItem[][]>(() => [[
  { label: '管理账号设置', icon: 'i-lucide-user-cog', to: '/set/account' }
], [
  { label: '退出登录', icon: 'i-lucide-log-out', color: 'error', onSelect: onLogout }
]])
</script>

<template>
  <UDashboardGroup unit="rem" storage="local" storage-key="pan-admin">
    <UDashboardSidebar
      id="default"
      v-model:open="open"
      collapsible
      resizable
      class="bg-(--ui-bg-elevated)/25"
      :ui="{ footer: 'lg:border-t lg:border-(--ui-border)' }"
    >
      <template #header="{ collapsed }">
        <NuxtLink to="/" class="flex min-w-0 items-center gap-2 font-semibold">
          <UIcon name="i-lucide-cloud" class="size-6 shrink-0 text-primary" />
          <span v-if="!collapsed" class="truncate">{{ status.title || '外链网盘' }}管理中心</span>
        </NuxtLink>
      </template>

      <template #default="{ collapsed }">
        <UNavigationMenu :collapsed="collapsed" :items="links[0]" orientation="vertical" tooltip popover />
        <UNavigationMenu :collapsed="collapsed" :items="links[1]" orientation="vertical" tooltip class="mt-auto" />
      </template>

      <template #footer="{ collapsed }">
        <UDropdownMenu :items="userMenu" :content="{ align: 'center', collisionPadding: 12 }" :ui="{ content: collapsed ? 'w-48' : 'w-(--reka-dropdown-menu-trigger-width)' }">
          <UButton
            :label="collapsed ? undefined : (status.admin_user || '管理员')"
            icon="i-lucide-circle-user"
            color="neutral"
            variant="ghost"
            block
            :square="collapsed"
            trailing-icon="i-lucide-chevrons-up-down"
            :ui="{ trailingIcon: collapsed ? 'hidden' : 'text-(--ui-text-dimmed)' }"
          />
        </UDropdownMenu>
      </template>
    </UDashboardSidebar>

    <slot />
  </UDashboardGroup>
</template>
