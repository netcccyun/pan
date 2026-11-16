<script setup lang="ts">
import type { StorageRow, StorageType } from '~/utils/types'

useHead({ title: '存储管理' })

const { get, post } = useApi()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const types = ref<StorageType[]>([])
const list = ref<StorageRow[]>([])

async function fetchList() {
  try {
    const res = await get('storageList')
    list.value = res.data || []
  } catch (e: any) {
    toast.add({ title: e?.message || '加载存储列表失败', color: 'error' })
  }
}

async function load() {
  loading.value = true
  try {
    const [typeRes] = await Promise.all([get('storageTypes'), fetchList()])
    types.value = typeRes.data || []
  } catch (e: any) {
    toast.add({ title: e?.message || '加载存储类型失败', color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(load)

const editOpen = ref(false)
const editing = ref<StorageRow | null>(null)

function add() {
  editing.value = null
  editOpen.value = true
}

function edit(row: StorageRow) {
  editing.value = row
  editOpen.value = true
}

async function setDefault(row: StorageRow) {
  try {
    let res: { msg?: string } | undefined
    const ok = await confirm({
      title: '设为默认存储',
      description: `确定将「${row.name}」设为默认存储吗？之后新上传的文件将默认保存到此存储，已上传的文件不受影响。`,
      confirmText: '确定',
      onConfirm: async () => { res = await post('setStorageDefault', { id: row.id }) }
    })
    if (!ok) return
    toast.add({ title: res?.msg || '设置成功', color: 'success' })
    fetchList()
  } catch (e: any) {
    toast.add({ title: e?.message || '设置失败', color: 'error' })
  }
}

/** 正在切换开启状态的存储 */
const switching = reactive<Record<number, boolean>>({})

async function setStatus(row: StorageRow, value: boolean) {
  if (switching[row.id]) return
  switching[row.id] = true
  try {
    await post('setStorageStatus', { id: row.id, status: value ? 1 : 0 })
    row.status = value ? 1 : 0
    toast.add({ title: value ? '已开启' : '已关闭', color: 'success' })
  } catch (e: any) {
    toast.add({ title: e?.message || '修改失败', color: 'error' })
  } finally {
    delete switching[row.id]
  }
}

async function del(row: StorageRow) {
  try {
    // 删除期间确认弹窗保持打开并显示加载中
    const ok = await confirm({
      title: '删除存储',
      description: `你确定要删除存储「${row.name}」吗？`,
      confirmText: '删除',
      color: 'error',
      onConfirm: () => post('delStorage', { id: row.id })
    })
    if (!ok) return
    toast.add({ title: '删除成功', color: 'success' })
    fetchList()
  } catch (e: any) {
    toast.add({ title: e?.message || '删除失败', color: 'error' })
  }
}

const filesTo = (row: StorageRow) => ({ path: '/file', query: { storage_id: String(row.id) } })
const deleteTip = (row: StorageRow) => row.isdefault ? '默认存储不能删除' : row.filecount > 0 ? '存储下还有文件，不能删除' : '删除'
</script>

<template>
  <AdminPage id="storage" title="存储管理" icon="i-lucide-hard-drive">
    <template #right>
      <UButton icon="i-lucide-plus" label="添加存储" :disabled="loading" @click="add" />
    </template>

    <div class="space-y-4">
      <UAlert color="info" variant="subtle" icon="i-lucide-info">
        <template #description>
          <p>可以同时添加多个存储，新上传的文件保存到<b>默认存储</b>。切换默认存储后，之前上传的文件仍可正常下载。</p>
        </template>
      </UAlert>

      <div v-if="loading" class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <UCard v-for="i in 3" :key="i">
          <div class="flex gap-3">
            <USkeleton class="size-11 rounded-lg" />
            <div class="flex-1 space-y-2">
              <USkeleton class="h-4 w-1/2" />
              <USkeleton class="h-3 w-3/4" />
            </div>
          </div>
        </UCard>
      </div>

      <UCard v-else-if="!list.length">
        <div class="py-10 text-center text-(--ui-text-muted)">
          <UIcon name="i-lucide-hard-drive" class="mx-auto mb-3 size-10" />
          <p>还没有添加存储</p>
          <UButton class="mt-4" icon="i-lucide-plus" label="添加存储" @click="add" />
        </div>
      </UCard>

      <div v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <UCard v-for="row in list" :key="row.id" :class="{ 'ring-2 ring-primary/60': row.isdefault }" :ui="{ body: 'p-4 sm:p-5' }">
          <div class="flex items-start gap-3">
            <div
              class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-(--ui-border)"
              :class="[row.status ? 'bg-primary/10 text-primary' : 'bg-(--ui-bg-accented) text-(--ui-text-muted)', { 'opacity-50 grayscale': !row.status }]"
              :title="row.type_name"
            >
              <StorageIcon :icon="row.icon" class="size-6 rounded-sm" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="truncate font-semibold" :title="row.name">{{ row.name }}</span>
                <UBadge v-if="row.isdefault" color="primary" variant="solid" size="sm">默认</UBadge>
                <UBadge v-if="!row.available" color="error" variant="subtle" size="sm">不可用</UBadge>
              </div>
              <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-(--ui-text-muted)">
                <span>ID {{ row.id }}</span>
                <span>{{ row.type_name }}</span>
                <span>排序 {{ row.sort }}</span>
              </div>
            </div>
            <USwitch
              :model-value="row.status === 1"
              :disabled="row.isdefault === 1 || switching[row.id]"
              :loading="switching[row.id]"
              :title="row.isdefault ? '默认存储不能关闭' : row.status ? '点击关闭' : '点击开启'"
              @update:model-value="(v: boolean) => setStatus(row, v)"
            />
          </div>

          <div class="mt-4 flex items-center justify-between gap-2 border-t border-(--ui-border) pt-3">
            <NuxtLink :to="filesTo(row)" class="text-sm text-(--ui-text-muted) hover:text-primary">
              <UIcon name="i-lucide-files" class="mr-1 size-4 align-[-3px]" />{{ row.filecount }} 个文件
            </NuxtLink>
            <div class="flex flex-wrap justify-end gap-1">
              <UButton v-if="!row.isdefault" size="xs" variant="soft" color="primary" label="设为默认" :disabled="!row.available" @click="setDefault(row)" />
              <UButton size="xs" variant="soft" color="info" label="编辑" @click="edit(row)" />
              <UButton size="xs" variant="soft" color="error" label="删除" :title="deleteTip(row)" :disabled="row.isdefault === 1 || row.filecount > 0" @click="del(row)" />
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <StorageEditModal v-model:open="editOpen" :storage="editing" :types="types" @saved="fetchList" />
  </AdminPage>
</template>
