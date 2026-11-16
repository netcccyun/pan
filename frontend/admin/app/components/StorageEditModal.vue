<script setup lang="ts">
import type { StorageRow, StorageType } from '~/utils/types'
import { isStorageFieldVisible } from '~/utils/types'

const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ storage: StorageRow | null, types: StorageType[] }>()
const emit = defineEmits<{ saved: [] }>()

const { post } = useApi()
const toast = useToast()

const saving = ref(false)
const form = reactive({ id: 0, name: '', type: 'local', status: true, sort: 0 })
const config = reactive<Record<string, string>>({})
/** 密码类型字段是否显示明文 */
const reveal = reactive<Record<string, boolean>>({})

const isEdit = computed(() => !!props.storage)
const fileCount = computed(() => props.storage?.filecount || 0)
const typeLocked = computed(() => isEdit.value && fileCount.value > 0)
const typeInfo = computed(() => props.types.find(t => t.type === form.type))

const typeOptions = computed(() => props.types
  .filter(t => t.available || t.type === form.type)
  .map(t => ({ label: t.available ? t.name : `${t.name}（当前环境不可用）`, value: t.type, storageIcon: t.icon })))

const visibleFields = computed(() => (typeInfo.value?.fields || []).filter(f => isStorageFieldVisible(f, config)))
const hasCloudMode = computed(() => typeInfo.value?.fields.some(f => f.key === 'uploadfile_type' || f.key === 'downfile_type'))

/** 切换类型时按新类型的参数定义初始化，同名参数保留已填写的值 */
function initConfig(source: Record<string, string> = {}) {
  const fields = typeInfo.value?.fields || []
  const values: Record<string, string> = {}
  for (const field of fields) {
    const value = source[field.key] ?? config[field.key]
    values[field.key] = value !== undefined && value !== null && value !== '' ? String(value) : field.default
  }
  for (const key of Object.keys(config)) delete config[key]
  Object.assign(config, values)
}

watch(open, (isOpen) => {
  if (!isOpen) return
  const s = props.storage
  for (const key of Object.keys(config)) delete config[key]
  for (const key of Object.keys(reveal)) delete reveal[key]
  if (s) {
    Object.assign(form, { id: s.id, name: s.name, type: s.type, status: s.status === 1, sort: s.sort })
    initConfig(s.config || {})
  } else {
    const first = props.types.find(t => t.available)
    Object.assign(form, { id: 0, name: first?.name || '', type: first?.type || 'local', status: true, sort: 0 })
    initConfig()
  }
}, { immediate: true })

function onTypeChange(type: string) {
  const oldName = typeInfo.value?.name
  form.type = type
  // 名称还是默认的类型名称时，跟随类型变化
  if (!form.name || form.name === oldName) form.name = typeInfo.value?.name || ''
  initConfig()
}

// 下拉框的值不在选项中时改为默认值
watch(visibleFields, (list) => {
  for (const field of list) {
    if (field.type !== 'select' || !field.options?.length) continue
    if (!field.options.some(o => o.value === config[field.key])) config[field.key] = field.default || field.options[0]!.value
  }
}, { immediate: true })

async function onSubmit() {
  if (!form.name.trim()) {
    toast.add({ title: '请填写存储名称', color: 'warning' })
    return
  }
  for (const field of visibleFields.value) {
    if (field.required && !config[field.key]?.trim()) {
      toast.add({ title: `请填写${field.label}`, color: 'warning' })
      return
    }
  }
  saving.value = true
  try {
    const res = await post('saveStorage', {
      id: form.id,
      name: form.name.trim(),
      type: form.type,
      status: form.status ? 1 : 0,
      sort: form.sort,
      config: JSON.stringify(config)
    })
    toast.add({ title: res.msg || '保存成功', color: 'success', icon: 'i-lucide-circle-check' })
    open.value = false
    emit('saved')
  } catch (e: any) {
    toast.add({ title: e?.message || '保存失败', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!-- 表单内容较多，点击弹窗外侧不关闭，避免误操作丢失已填写的内容 -->
  <UModal v-model:open="open" :title="isEdit ? '编辑存储' : '添加存储'" :dismissible="false" :ui="{ content: 'sm:max-w-xl' }">
    <template #body>
      <form id="storage-edit-form" class="space-y-4" @submit.prevent="onSubmit">
        <UAlert
          v-if="isEdit && fileCount > 0"
          color="warning"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          :description="`该存储下已有 ${fileCount} 个文件，不能修改存储类型。修改存储桶、路径等参数会导致这些文件无法下载，请谨慎操作。`"
        />

        <UFormField label="存储类型" required>
          <USelect :model-value="form.type" :items="typeOptions" :disabled="typeLocked" class="w-full" @update:model-value="onTypeChange">
            <template #leading>
              <StorageIcon :icon="typeInfo?.icon" class="size-5 shrink-0 rounded-sm text-primary" />
            </template>
            <template #item-leading="{ item }">
              <StorageIcon :icon="(item as any).storageIcon" class="size-5 shrink-0 rounded-sm text-primary" />
            </template>
          </USelect>
        </UFormField>
        <p v-if="typeInfo?.url" class="-mt-2 text-sm text-(--ui-text-muted)">
          还没有{{ typeInfo.name }}账号？
          <a :href="typeInfo.url" target="_blank" rel="noreferrer" class="text-primary underline">点此开通</a>
        </p>

        <UFormField label="存储名称" required help="前台选择存储位置时显示此名称">
          <UInput v-model="form.name" placeholder="例如：阿里云OSS-华东" class="w-full" />
        </UFormField>

        <template v-for="field in visibleFields" :key="`${form.type}-${field.key}`">
          <!-- 开关：标签和开关同一行，帮助信息显示在下方 -->
          <div v-if="field.type === 'switch'">
            <UFormField
              :label="field.label"
              orientation="horizontal"
              :ui="{ container: 'flex items-center' }"
            >
              <USwitch :model-value="config[field.key] === '1'" @update:model-value="(v: boolean) => (config[field.key] = v ? '1' : '0')" />
            </UFormField>
            <p v-if="field.help" class="mt-2 text-sm text-(--ui-text-muted)">{{ field.help }}</p>
          </div>
          <UFormField v-else :label="field.label" :help="field.help" :required="field.required">
            <USelect v-if="field.type === 'select'" v-model="config[field.key]" :items="field.options" class="w-full" />
            <UTextarea v-else-if="field.type === 'textarea'" v-model="config[field.key]" :placeholder="field.placeholder" :rows="3" autoresize class="w-full" />
            <UInput
              v-else-if="field.type === 'password'"
              v-model="config[field.key]"
              :type="reveal[field.key] ? 'text' : 'password'"
              :placeholder="field.placeholder"
              autocomplete="new-password"
              class="w-full"
              :ui="{ trailing: 'pe-1' }"
            >
              <template #trailing>
                <UButton
                  color="neutral"
                  variant="link"
                  size="sm"
                  :icon="reveal[field.key] ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                  :aria-label="reveal[field.key] ? '隐藏' : '显示'"
                  @click="reveal[field.key] = !reveal[field.key]"
                />
              </template>
            </UInput>
            <UInput v-else v-model="config[field.key]" :placeholder="field.placeholder" class="w-full" />
          </UFormField>
        </template>

        <StorageOauth
          v-if="typeInfo?.oauth"
          :key="form.type"
          :type="form.type"
          :config="config"
          :oauth="typeInfo.oauth"
          @authorized="(values) => Object.assign(config, values)"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField label="排序" help="数字越小越靠前">
            <UInputNumber v-model="form.sort" :min="0" class="w-full" />
          </UFormField>
          <UFormField label="开启状态" help="开启后才能上传文件到此存储">
            <USwitch v-model="form.status" :disabled="storage?.isdefault === 1" :label="form.status ? '已开启' : '已关闭'" />
          </UFormField>
        </div>

        <UAlert v-if="hasCloudMode" color="info" variant="subtle" icon="i-lucide-info">
          <template #description>
            <p><b>文件上传方式：</b>网站中转是先上传到本站服务器再转存到云存储，速度较慢；直接链接是文件直接上传到云存储，速度更快，支持更大文件，需要先在云存储设置跨域。</p>
            <p class="mt-1"><b>文件下载方式：</b>网站中转是经过本站服务器中转，如果本机与云存储是内网连接则不消耗流量；直接链接下载速度更快，需支付流量费用。</p>
          </template>
        </UAlert>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="outline" label="取消" @click="open = false" />
        <UButton type="submit" form="storage-edit-form" label="保存" icon="i-lucide-save" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>
