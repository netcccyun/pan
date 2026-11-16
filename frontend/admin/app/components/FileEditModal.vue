<script setup lang="ts">
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ id: number | null }>()
const emit = defineEmits<{ saved: [] }>()

const { get, post } = useApi()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const form = reactive({ id: 0, name: '', type: '', size: 0, hash: '', hide: false, ispwd: false, pwd: '' })

watch(() => [open.value, props.id] as const, async ([isOpen, id]) => {
  if (!isOpen || !id) return
  loading.value = true
  try {
    const { data } = await get('getFileInfo', { id })
    Object.assign(form, {
      id: data.id,
      name: data.name,
      type: data.type,
      size: data.size,
      hash: data.hash,
      hide: data.hide === 1,
      ispwd: !!data.pwd,
      pwd: data.pwd || ''
    })
  } catch (e: any) {
    toast.add({ title: e?.message || '加载文件信息失败', color: 'error' })
    open.value = false
  } finally {
    loading.value = false
  }
}, { immediate: true })

async function onSubmit() {
  if (!form.name.trim()) {
    toast.add({ title: '文件名称不能为空', color: 'warning' })
    return
  }
  saving.value = true
  try {
    const res = await post('saveFileInfo', {
      id: form.id,
      name: form.name,
      type: form.type,
      hide: form.hide ? 1 : 0,
      ispwd: form.ispwd ? 1 : 0,
      pwd: form.ispwd ? form.pwd : ''
    })
    toast.add({ title: res.msg || '修改成功', color: 'success', icon: 'i-lucide-circle-check' })
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
  <!-- 点击弹窗外侧不关闭，避免误操作丢失已修改的内容 -->
  <UModal v-model:open="open" title="文件信息修改" :dismissible="false">
    <template #body>
      <div v-if="loading" class="space-y-4">
        <USkeleton v-for="i in 5" :key="i" class="h-9 w-full" />
      </div>
      <form v-else id="file-edit-form" class="space-y-4" @submit.prevent="onSubmit">
        <UFormField label="文件名称" required>
          <UInput v-model="form.name" class="w-full" />
        </UFormField>
        <UFormField label="文件类型">
          <UInput v-model="form.type" class="w-full" />
        </UFormField>
        <UFormField label="文件大小">
          <UInput :model-value="`${sizeFormat(form.size)}（${form.size} 字节）`" class="w-full" disabled />
        </UFormField>
        <UFormField label="文件MD5">
          <UInput :model-value="form.hash" class="w-full font-mono" disabled />
        </UFormField>
        <div class="flex flex-wrap gap-x-8 gap-y-3">
          <USwitch v-model="form.hide" label="在首页隐藏" />
          <USwitch v-model="form.ispwd" label="开启下载密码" />
        </div>
        <UFormField v-if="form.ispwd" label="下载密码" help="只能为字母和数字">
          <UInput v-model="form.pwd" class="w-full" />
        </UFormField>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="outline" label="关闭" @click="open = false" />
        <UButton type="submit" form="file-edit-form" label="保存" :loading="saving" :disabled="loading" />
      </div>
    </template>
  </UModal>
</template>
