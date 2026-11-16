<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'

useHead({ title: '用户管理' })

interface UserRow {
  uid: number | string
  type: string
  openid: string
  nickname: string
  faceimg: string
  regip: string
  loginip: string
  addtime: string
  lasttime: string
  level: number | string
  enable: number | string
}

const { post } = useApi()
const toast = useToast()
const confirm = useConfirm()

const { page, pagesize, query, filters, search, reset, setPage, setPagesize } = useListQuery({
  type: '1',
  kw: '',
  dstatus: '-1'
})

const TYPE_OPTIONS = [
  { label: 'UID', value: '1' },
  { label: '第三方账号UID', value: '2' },
  { label: '昵称', value: '3' },
  { label: '登录IP', value: '4' }
]
const STATUS_OPTIONS = [
  { label: '全部状态', value: '-1' },
  { label: '正常状态', value: '1' },
  { label: '封禁状态', value: '0' }
]
const LOGIN_TYPES: Record<string, string> = { qq: 'QQ', wx: '微信' }
const LEVEL_OPTIONS = [
  { label: '0_普通', value: 0 },
  { label: '1_高级', value: 1 }
]
const PAGE_SIZE_OPTIONS = PAGE_SIZES.map(n => ({ label: `${n} 条/页`, value: n }))

const loading = ref(false)
const list = ref<UserRow[]>([])
const total = ref(0)

async function fetchList() {
  loading.value = true
  try {
    const q = query.value
    const data = await post('userList', {
      type: q.type,
      kw: q.kw,
      dstatus: q.dstatus,
      offset: (page.value - 1) * pagesize.value,
      limit: pagesize.value
    })
    list.value = data.rows || []
    total.value = data.total || 0
  } catch (e: any) {
    toast.add({ title: e?.message || '加载用户列表失败', color: 'error' })
  } finally {
    loading.value = false
  }
}

watch(() => [page.value, pagesize.value, query.value], fetchList, { immediate: true, deep: true })

const isEnabled = (row: UserRow) => Number(row.enable) === 1
const isSenior = (row: UserRow) => Number(row.level) === 1

/** 正在修改状态的用户 */
const enabling = reactive<Record<string, boolean>>({})

async function setEnable(row: UserRow) {
  if (enabling[row.uid]) return
  const enable = isEnabled(row) ? 0 : 1
  enabling[row.uid] = true
  try {
    await post('setUserEnable', { uid: row.uid, enable })
    row.enable = enable
    toast.add({ title: enable ? '已解封用户' : '已封禁用户', color: 'success' })
  } catch (e: any) {
    toast.add({ title: e?.message || '修改失败', color: 'error' })
  } finally {
    delete enabling[row.uid]
  }
}

async function delUser(row: UserRow) {
  try {
    // 删除期间确认弹窗保持打开并显示加载中
    const ok = await confirm({
      title: '删除用户',
      description: `你确定要删除用户「${row.nickname || row.uid}」吗？`,
      confirmText: '删除',
      color: 'error',
      onConfirm: () => post('delUser', { uid: row.uid })
    })
    if (!ok) return
    toast.add({ title: '删除成功', color: 'success' })
    fetchList()
  } catch (e: any) {
    toast.add({ title: e?.message || '删除失败', color: 'error' })
  }
}

const levelOpen = ref(false)
const levelSaving = ref(false)
const levelForm = reactive({ uid: 0 as number | string, level: 0 })

function editLevel(row: UserRow) {
  levelForm.uid = row.uid
  levelForm.level = Number(row.level) || 0
  levelOpen.value = true
}

async function saveLevel() {
  levelSaving.value = true
  try {
    await post('saveUserInfo', { uid: levelForm.uid, level: levelForm.level })
    toast.add({ title: '修改用户成功', color: 'success' })
    levelOpen.value = false
    fetchList()
  } catch (e: any) {
    toast.add({ title: e?.message || '修改失败', color: 'error' })
  } finally {
    levelSaving.value = false
  }
}

const ipUrl = (ip: string) => `https://m.ip138.com/iplookup.asp?ip=${encodeURIComponent(ip)}`
const filesTo = (row: UserRow) => ({ path: '/file', query: { uid: String(row.uid) } })

const columnPinning = { right: ['enable', 'action'] }
const pinWidth = (px: number) => {
  const width = `${px}px`
  return { th: { width, minWidth: width }, td: { width, minWidth: width } }
}

const columns: TableColumn<UserRow>[] = [
  { accessorKey: 'uid', header: 'UID' },
  { accessorKey: 'nickname', header: '头像&昵称' },
  { accessorKey: 'openid', header: '登录方式/第三方账号UID' },
  { accessorKey: 'regip', header: '注册IP/登录IP' },
  { accessorKey: 'addtime', header: '注册时间/最后登录' },
  { accessorKey: 'level', header: '权限' },
  { accessorKey: 'enable', header: '状态', size: 80, meta: { style: pinWidth(80), class: { th: 'shadow-[-8px_0_8px_-6px_rgb(15_23_42/0.12)]', td: 'shadow-[-8px_0_8px_-6px_rgb(15_23_42/0.12)]' } } },
  { id: 'action', header: '操作', size: 128, meta: { style: pinWidth(128) } }
]
</script>

<template>
  <AdminPage id="user" title="用户管理" icon="i-lucide-users">
    <div class="space-y-4">
      <UCard :ui="{ body: 'p-3 sm:p-4' }">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="search">
          <UFieldGroup class="w-full sm:w-auto">
            <USelect v-model="filters.type" :items="TYPE_OPTIONS" class="w-36" />
            <UInput v-model="filters.kw" placeholder="搜索内容" class="flex-1 sm:w-56" />
          </UFieldGroup>
          <USelect v-model="filters.dstatus" :items="STATUS_OPTIONS" class="w-32" />
          <UButton type="submit" icon="i-lucide-search" label="搜索" />
          <UButton color="neutral" variant="outline" icon="i-lucide-rotate-ccw" label="重置" @click="reset" />
        </form>
      </UCard>

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <UTable
          class="hidden lg:block [&_[data-pinned]]:bg-(--ui-bg)"
          :data="list"
          :columns="columns"
          :column-pinning="columnPinning"
          :loading="loading"
          :ui="{ td: 'py-2.5 align-middle', th: 'whitespace-nowrap' }"
        >
          <template #uid-cell="{ row }">
            <span class="font-semibold">{{ row.original.uid }}</span>
          </template>
          <template #nickname-cell="{ row }">
            <div class="flex max-w-56 items-center gap-2">
              <UAvatar :src="row.original.faceimg || undefined" :alt="row.original.nickname" size="md" />
              <span class="truncate" :title="row.original.nickname">{{ row.original.nickname }}</span>
            </div>
          </template>
          <template #openid-cell="{ row }">
            <div class="text-xs leading-5">
              <div class="font-semibold">{{ LOGIN_TYPES[row.original.type] || row.original.type }}</div>
              <div class="whitespace-normal break-all text-(--ui-text-muted)">{{ row.original.openid }}</div>
            </div>
          </template>
          <template #regip-cell="{ row }">
            <div class="text-xs leading-5">
              <a :href="ipUrl(row.original.regip)" target="_blank" rel="noreferrer" class="block text-primary hover:underline">{{ row.original.regip }}</a>
              <a :href="ipUrl(row.original.loginip)" target="_blank" rel="noreferrer" class="block text-primary hover:underline">{{ row.original.loginip }}</a>
            </div>
          </template>
          <template #addtime-cell="{ row }">
            <div class="whitespace-nowrap text-xs leading-5 text-(--ui-text-muted)">
              <div>{{ row.original.addtime }}</div>
              <div>{{ row.original.lasttime }}</div>
            </div>
          </template>
          <template #level-cell="{ row }">
            <UButton size="xs" variant="link" :color="isSenior(row.original) ? 'warning' : 'primary'" :label="isSenior(row.original) ? '高级' : '普通'" title="修改用户权限" class="px-0" @click="editLevel(row.original)" />
          </template>
          <template #enable-cell="{ row }">
            <UButton size="xs" variant="soft" :color="isEnabled(row.original) ? 'success' : 'error'" :label="isEnabled(row.original) ? '正常' : '封禁'" :title="isEnabled(row.original) ? '点击封禁' : '点击解封'" :loading="enabling[row.original.uid]" @click="setEnable(row.original)" />
          </template>
          <template #action-cell="{ row }">
            <div class="flex items-center gap-1 whitespace-nowrap">
              <UButton size="xs" variant="soft" color="info" label="文件" :to="filesTo(row.original)" />
              <UButton size="xs" variant="soft" color="error" label="删除" @click="delUser(row.original)" />
            </div>
          </template>
          <template #empty>
            <div class="py-10 text-center text-(--ui-text-muted)">没有找到用户</div>
          </template>
        </UTable>

        <div class="divide-y divide-(--ui-border) lg:hidden">
          <template v-if="loading && !list.length">
            <div v-for="i in 5" :key="i" class="flex gap-3 p-4">
              <USkeleton class="size-10 rounded-full" />
              <div class="flex-1 space-y-2">
                <USkeleton class="h-4 w-1/2" />
                <USkeleton class="h-3 w-3/4" />
              </div>
            </div>
          </template>
          <div v-else-if="!list.length" class="py-10 text-center text-(--ui-text-muted)">没有找到用户</div>
          <div v-for="item in list" v-else :key="item.uid" class="flex items-start gap-3 p-4" :class="{ 'opacity-60': loading }">
            <UAvatar :src="item.faceimg || undefined" :alt="item.nickname" size="lg" />
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-medium">{{ item.nickname }}</span>
                <UBadge v-if="isSenior(item)" color="warning" variant="subtle" size="sm">高级</UBadge>
              </div>
              <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
                <span>UID {{ item.uid }}</span>
                <span>{{ LOGIN_TYPES[item.type] || item.type }}</span>
                <span>注册 {{ item.addtime }}</span>
                <span>登录 {{ item.lasttime }}</span>
                <span>{{ item.loginip }}</span>
              </div>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <UButton size="xs" variant="soft" :color="isEnabled(item) ? 'success' : 'error'" :label="isEnabled(item) ? '正常' : '封禁'" :loading="enabling[item.uid]" @click="setEnable(item)" />
                <UButton size="xs" variant="soft" color="neutral" label="权限" @click="editLevel(item)" />
                <UButton size="xs" variant="soft" color="info" label="文件" :to="filesTo(item)" />
                <UButton size="xs" variant="soft" color="error" label="删除" @click="delUser(item)" />
              </div>
            </div>
          </div>
        </div>

        <template #footer>
          <div class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <span class="text-(--ui-text-muted)">共 {{ total }} 个用户</span>
              <USelect :model-value="pagesize" :items="PAGE_SIZE_OPTIONS" size="sm" class="w-28" @update:model-value="(v: number) => setPagesize(v)" />
            </div>
            <UPagination
              v-if="total > pagesize"
              :page="page"
              :total="total"
              :items-per-page="pagesize"
              :sibling-count="1"
              show-edges
              size="sm"
              @update:page="setPage"
            />
          </div>
        </template>
      </UCard>
    </div>

    <UModal v-model:open="levelOpen" title="用户权限修改">
      <template #body>
        <div class="space-y-4">
          <UAlert color="info" variant="subtle" icon="i-lucide-info" description="高级权限的用户无每日上传数量、文件类型、关键词屏蔽等限制，且视频文件无需审核。" />
          <UFormField label="用户权限">
            <USelect v-model="levelForm.level" :items="LEVEL_OPTIONS" class="w-full" />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="outline" label="关闭" @click="levelOpen = false" />
          <UButton label="保存" :loading="levelSaving" @click="saveLevel" />
        </div>
      </template>
    </UModal>
  </AdminPage>
</template>
