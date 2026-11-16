<script setup lang="ts">
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'

interface FileItem {
  id: number
  name: string
  type: string
  size: number
  hash: string
  addtime: string
  ip: string
  block: number
  /** 以下字段只在“我的文件”中返回 */
  hide?: number
  pwd?: string
  count?: number
  /** 当前用户能否修改、删除该文件 */
  manage?: number
}

type BatchOp = 'delete' | 'show' | 'hide'

const props = defineProps<{ mine?: boolean }>()

const route = useRoute()
const router = useRouter()
const { get, rawPost, csrf } = useApi()
const { site, fileUrl } = useSite()
const toast = useToast()
const confirm = useConfirm()

const pagesize = 15
const page = computed(() => Math.max(1, Number.parseInt(String(route.query.page || '1')) || 1))
const kw = computed(() => String(route.query.kw || ''))
const keyword = ref(kw.value)
watch(kw, (v) => { keyword.value = v })

const searchable = computed(() => props.mine || site.value.filesearch === 1)

const loading = ref(false)
const list = ref<FileItem[]>([])
const total = ref(0)
const pages = ref(0)

async function fetchList() {
  loading.value = true
  try {
    const data = await get('filelist', {
      m: props.mine ? 'mine' : undefined,
      kw: kw.value || undefined,
      page: page.value,
      pagesize
    })
    list.value = data.list || []
    total.value = data.total || 0
    pages.value = data.pages || 0
    // 删除后当前页没有文件了，回到上一页
    if (!list.value.length && page.value > 1 && total.value > 0) changePage(Math.min(page.value - 1, pages.value))
  } catch (e: any) {
    toast.add({ title: e?.message || '加载文件列表失败', color: 'error' })
  } finally {
    loading.value = false
  }
}

watch(() => [page.value, kw.value, props.mine], fetchList, { immediate: true })

function search() {
  router.push({ query: { ...route.query, kw: keyword.value.trim() || undefined, page: undefined } })
}

function clearSearch() {
  keyword.value = ''
  router.push({ query: { ...route.query, kw: undefined, page: undefined } })
}

function changePage(p: number) {
  router.push({ query: { ...route.query, page: p > 1 ? p : undefined } })
  if (import.meta.client) window.scrollTo({ top: 0, behavior: 'smooth' })
}

const downUrl = (row: FileItem) => `./down.php/${row.hash}.${row.type || 'file'}${row.pwd ? `&${row.pwd}` : ''}`
const detailTo = (row: FileItem) => ({ path: '/detail', query: { hash: row.hash, pwd: row.pwd || undefined } })

// ---------- 我的文件：选择与批量操作 ----------
const selected = ref<string[]>([])
watch(list, () => { selected.value = [] })

const allSelected = computed(() => list.value.length > 0 && selected.value.length === list.value.length)
const someSelected = computed(() => selected.value.length > 0 && !allSelected.value)
const selectedItems = computed(() => list.value.filter(i => selected.value.includes(i.hash)))

function toggleAll(value: boolean | 'indeterminate') {
  selected.value = value === true ? list.value.map(i => i.hash) : []
}

function toggleRow(hash: string, value: boolean | 'indeterminate') {
  if (value === true) {
    if (!selected.value.includes(hash)) selected.value.push(hash)
  } else {
    selected.value = selected.value.filter(h => h !== hash)
  }
}

async function copyLinks(items: FileItem[]) {
  if (!items.length) return
  const text = items.map(i => fileUrl(i.hash, i.type, i.pwd)).join('\n')
  if (await copyText(text)) {
    toast.add({ title: items.length > 1 ? `已复制 ${items.length} 个文件的链接` : '链接已复制', color: 'success', icon: 'i-lucide-circle-check' })
  } else {
    toast.add({ title: '复制失败，请手动复制', color: 'error', icon: 'i-lucide-circle-x' })
  }
}

const batching = ref(false)
const opLabels: Record<BatchOp, string> = { delete: '删除', show: '在首页显示', hide: '在首页隐藏' }

async function runBatch(op: BatchOp, items: FileItem[]) {
  if (!items.length || batching.value) return
  if (op === 'delete') {
    const ok = await confirm({
      title: '删除文件',
      description: items.length > 1
        ? `确定删除选中的 ${items.length} 个文件吗？删除后不可恢复。`
        : `确定删除“${items[0]!.name}”吗？删除后不可恢复。`,
      confirmText: '删除',
      color: 'error'
    })
    if (!ok) return
  }
  batching.value = true
  try {
    const res = await rawPost('fileBatch', { op, hashes: items.map(i => i.hash), csrf_token: await csrf() })
    const failed: { name: string, msg: string }[] = res.failed || []
    if (res.code !== 0 && !failed.length) throw new Error(res.msg || `${opLabels[op]}失败`)
    toast.add({
      title: res.msg || `${opLabels[op]}成功`,
      description: failed.slice(0, 3).map(f => `${f.name || '未知文件'}：${f.msg}`).join('\n') + (failed.length > 3 ? `\n……等 ${failed.length} 个` : '') || undefined,
      color: failed.length ? (res.success ? 'warning' : 'error') : 'success',
      icon: failed.length ? 'i-lucide-triangle-alert' : 'i-lucide-circle-check'
    })
    await fetchList()
  } catch (e: any) {
    toast.add({ title: e?.message || `${opLabels[op]}失败`, color: 'error' })
  } finally {
    batching.value = false
  }
}

// ---------- 修改文件信息 ----------
const editOpen = ref(false)
const editing = ref<FileItem | null>(null)

function openEdit(item: FileItem) {
  editing.value = item
  editOpen.value = true
}

function onSaved(result: { name: string, hide: number, pwd: string }) {
  if (editing.value) Object.assign(editing.value, result)
}

function rowMenu(item: FileItem): DropdownMenuItem[][] {
  const locked = !item.manage
  return [
    [{ label: '复制链接', icon: 'i-lucide-link', onSelect: () => copyLinks([item]) }],
    [{ label: '修改信息', icon: 'i-lucide-pencil', disabled: locked, onSelect: () => openEdit(item) }],
    [{ label: '删除', icon: 'i-lucide-trash-2', color: 'error', disabled: locked, onSelect: () => runBatch('delete', [item]) }]
  ]
}

const batchItems = computed<DropdownMenuItem[][]>(() => [
  [
    { label: '在首页显示', icon: 'i-lucide-eye', onSelect: () => runBatch('show', selectedItems.value) },
    { label: '在首页隐藏', icon: 'i-lucide-eye-off', onSelect: () => runBatch('hide', selectedItems.value) }
  ],
  [{ label: '删除', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => runBatch('delete', selectedItems.value) }]
])

const columns = computed<TableColumn<FileItem>[]>(() => props.mine
  ? [
      { id: 'select' },
      { id: 'action', header: '操作' },
      { accessorKey: 'name', header: '文件名' },
      { accessorKey: 'size', header: '文件大小' },
      { accessorKey: 'type', header: '文件格式' },
      { accessorKey: 'addtime', header: '上传时间' },
      { accessorKey: 'count', header: '下载次数' }
    ]
  : [
      { id: 'index', header: '#' },
      { id: 'action', header: '操作' },
      { accessorKey: 'name', header: '文件名' },
      { accessorKey: 'size', header: '文件大小' },
      { accessorKey: 'type', header: '文件格式' },
      { accessorKey: 'addtime', header: '上传时间' },
      { accessorKey: 'ip', header: '上传者IP' }
    ])
</script>

<template>
  <UCard :ui="{ body: 'p-0 sm:p-0' }">
    <template #header>
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
          <h2 class="text-xl font-semibold flex items-center gap-2">
            <UIcon :name="mine ? 'i-lucide-folder-open' : 'i-lucide-list'" class="size-5 text-primary shrink-0" />
            {{ mine ? '我上传的文件' : '文件列表' }}
          </h2>
          <p v-if="mine && !site.islogin" class="mt-1 text-sm text-(--ui-text-muted)">
            根据浏览器缓存记录，只能管理 7 天内上传的文件<template v-if="site.userlogin">，<NuxtLink to="/login" class="text-primary hover:underline">登录</NuxtLink>后可永久保留记录</template>
          </p>
        </div>
        <form v-if="searchable" class="flex gap-2 w-full md:w-auto" @submit.prevent="search">
          <UInput
            v-model="keyword"
            type="search"
            icon="i-lucide-search"
            placeholder="请输入搜索关键字"
            class="flex-1 md:w-64"
          />
          <UButton type="submit" label="搜索" />
        </form>
      </div>
      <div v-if="kw" class="mt-3 flex items-center gap-2 text-sm">
        <span class="text-(--ui-text-muted)">搜索：</span>
        <UBadge color="primary" variant="subtle">{{ kw }}</UBadge>
        <UButton size="xs" color="neutral" variant="link" label="清除" @click="clearSearch" />
      </div>
    </template>

    <!-- 我的文件：批量操作栏，常驻显示，避免勾选时页面跳动 -->
    <div v-if="mine && list.length" class="flex min-h-12 flex-wrap items-center gap-x-3 gap-y-2 border-b border-(--ui-border) px-4 py-2">
      <UCheckbox
        class="md:hidden"
        :model-value="someSelected ? 'indeterminate' : allSelected"
        label="全选"
        @update:model-value="toggleAll"
      />
      <span v-if="selected.length" class="text-sm font-medium">已选 {{ selected.length }} 个<span class="hidden sm:inline">文件</span></span>
      <span v-else class="hidden text-sm text-(--ui-text-muted) sm:inline">勾选文件后可批量操作</span>
      <div class="ms-auto flex flex-wrap gap-1.5">
        <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-copy" aria-label="复制链接" :disabled="!selected.length || batching" @click="copyLinks(selectedItems)">
          <span class="hidden sm:inline">复制链接</span>
        </UButton>
        <UDropdownMenu :items="batchItems" :disabled="!selected.length || batching" :content="{ align: 'end' }">
          <UButton
            size="sm"
            color="neutral"
            variant="outline"
            trailing-icon="i-lucide-chevron-down"
            :label="batching ? '处理中…' : '批量操作'"
            :loading="batching"
            :disabled="!selected.length || batching"
          />
        </UDropdownMenu>
      </div>
    </div>

    <!-- 电脑端表格 -->
    <UTable
      class="hidden md:block"
      :data="list"
      :columns="columns"
      :loading="loading"
      :ui="{ td: 'py-2.5', th: 'whitespace-nowrap' }"
    >
      <template #select-header>
        <UCheckbox :model-value="someSelected ? 'indeterminate' : allSelected" aria-label="全选" @update:model-value="toggleAll" />
      </template>
      <template #select-cell="{ row }">
        <UCheckbox :model-value="selected.includes(row.original.hash)" aria-label="选择" @update:model-value="(v: boolean | 'indeterminate') => toggleRow(row.original.hash, v)" />
      </template>
      <template #index-cell="{ row }">
        <span class="font-semibold">{{ (page - 1) * pagesize + row.index + 1 }}</span>
      </template>
      <template #action-cell="{ row }">
        <div class="flex items-center gap-1 whitespace-nowrap">
          <UButton :href="downUrl(row.original)" size="xs" variant="soft" icon="i-lucide-download" label="下载" external />
          <UButton :to="detailTo(row.original)" size="xs" variant="soft" color="neutral" icon="i-lucide-eye" label="查看" />
          <UDropdownMenu v-if="mine" :items="rowMenu(row.original)" :content="{ align: 'start' }">
            <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-ellipsis" aria-label="更多操作" />
          </UDropdownMenu>
        </div>
      </template>
      <template #name-cell="{ row }">
        <NuxtLink :to="detailTo(row.original)" class="flex items-center gap-2 hover:text-primary max-w-56 lg:max-w-72 xl:max-w-md">
          <UIcon :name="typeIcon(row.original.type)" class="size-5 shrink-0 text-primary/80" />
          <span class="truncate" :title="row.original.name">{{ row.original.name }}</span>
          <UBadge v-if="row.original.block === 1" color="error" variant="subtle" size="sm">已冻结</UBadge>
          <UBadge v-else-if="row.original.block === 2" color="warning" variant="subtle" size="sm">审核中</UBadge>
          <UTooltip v-if="mine && row.original.hide" text="不在首页文件列表显示">
            <UIcon name="i-lucide-eye-off" class="size-4 shrink-0 text-(--ui-text-muted)" />
          </UTooltip>
          <UTooltip v-if="mine && row.original.pwd" :text="`密码：${row.original.pwd}`">
            <UIcon name="i-lucide-lock" class="size-4 shrink-0 text-(--ui-text-muted)" />
          </UTooltip>
        </NuxtLink>
      </template>
      <template #size-cell="{ row }">
        <span class="whitespace-nowrap">{{ sizeFormat(row.original.size) }}</span>
      </template>
      <template #type-cell="{ row }">
        <UBadge color="info" variant="subtle">{{ row.original.type || '未知' }}</UBadge>
      </template>
      <template #addtime-cell="{ row }">
        <span class="whitespace-nowrap text-(--ui-text-muted)">{{ row.original.addtime }}</span>
      </template>
      <template #count-cell="{ row }">
        <span class="text-(--ui-text-muted)">{{ row.original.count ?? 0 }}</span>
      </template>
      <template #ip-cell="{ row }">
        <span class="text-(--ui-text-muted)">{{ row.original.ip }}</span>
      </template>
      <template #empty>
        <div class="py-10 text-center text-(--ui-text-muted)">还没上传过任何文件</div>
      </template>
    </UTable>

    <!-- 手机端卡片 -->
    <div class="md:hidden divide-y divide-(--ui-border)">
      <template v-if="loading && !list.length">
        <div v-for="i in 5" :key="i" class="p-4 flex gap-3">
          <USkeleton class="size-10 rounded-lg" />
          <div class="flex-1 space-y-2">
            <USkeleton class="h-4 w-3/4" />
            <USkeleton class="h-3 w-1/2" />
          </div>
        </div>
      </template>
      <div v-else-if="!list.length" class="py-10 text-center text-(--ui-text-muted)">还没上传过任何文件</div>
      <div
        v-for="item in list"
        v-else
        :key="item.id"
        class="p-4 flex items-start gap-3 transition-colors"
        :class="{ 'opacity-60': loading, 'bg-primary/5': selected.includes(item.hash) }"
      >
        <UCheckbox
          v-if="mine"
          class="mt-2.5"
          :model-value="selected.includes(item.hash)"
          aria-label="选择"
          @update:model-value="(v: boolean | 'indeterminate') => toggleRow(item.hash, v)"
        />
        <div class="size-10 shrink-0 rounded-lg bg-primary/10 flex items-center justify-center">
          <UIcon :name="typeIcon(item.type)" class="size-6 text-primary" />
        </div>
        <div class="min-w-0 flex-1">
          <NuxtLink :to="detailTo(item)" class="block font-medium break-all line-clamp-2">{{ item.name }}</NuxtLink>
          <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
            <span>{{ sizeFormat(item.size) }}</span>
            <span class="uppercase">{{ item.type || '未知' }}</span>
            <span>{{ item.addtime }}</span>
            <span v-if="mine">下载 {{ item.count ?? 0 }} 次</span>
            <span v-else>{{ item.ip }}</span>
            <span v-if="mine && item.hide" class="inline-flex items-center gap-0.5"><UIcon name="i-lucide-eye-off" class="size-3.5" />首页隐藏</span>
            <span v-if="mine && item.pwd" class="inline-flex items-center gap-0.5"><UIcon name="i-lucide-lock" class="size-3.5" />有密码</span>
            <UBadge v-if="item.block === 1" color="error" variant="subtle" size="sm">已冻结</UBadge>
            <UBadge v-else-if="item.block === 2" color="warning" variant="subtle" size="sm">审核中</UBadge>
          </div>
          <div class="mt-2 flex gap-2">
            <UButton :href="downUrl(item)" size="xs" variant="soft" icon="i-lucide-download" label="下载" external />
            <UButton :to="detailTo(item)" size="xs" variant="soft" color="neutral" icon="i-lucide-eye" label="查看" />
            <UDropdownMenu v-if="mine" :items="rowMenu(item)" :content="{ align: 'start' }">
              <UButton size="xs" variant="soft" color="neutral" icon="i-lucide-ellipsis" aria-label="更多操作" />
            </UDropdownMenu>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-sm">
        <span class="text-(--ui-text-muted)">
          共有 {{ total }} 个文件，当前第 {{ page }} 页，共 {{ pages }} 页
        </span>
        <UPagination
          v-if="total > pagesize"
          :page="page"
          :total="total"
          :items-per-page="pagesize"
          :sibling-count="1"
          show-edges
          size="sm"
          @update:page="changePage"
        />
      </div>
    </template>

    <FileEditModal v-if="mine" v-model:open="editOpen" :file="editing" @saved="onSaved" />
  </UCard>
</template>
