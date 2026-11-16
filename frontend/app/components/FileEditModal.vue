<script setup lang="ts">
/** 文件所有者修改文件信息：文件名（扩展名不可改）、是否在首页显示、提取密码 */
interface EditableFile {
  hash: string
  name: string
  type: string
  hide?: number | null
  pwd?: string | null
}

interface FileEditResult {
  name: string
  hide: number
  pwd: string
}

const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ file: EditableFile | null }>()
const emit = defineEmits<{ saved: [result: FileEditResult] }>()

const { post, csrf } = useApi()
const toast = useToast()

const saving = ref(false)
const form = reactive({ base: '', show: true, ispwd: false, pwd: '' })
const ext = computed(() => (props.file?.type ? `.${props.file.type}` : ''))

watch(() => [open.value, props.file] as const, ([isOpen, file]) => {
  if (!isOpen || !file) return
  const name = file.name || ''
  form.base = ext.value && name.toLowerCase().endsWith(ext.value.toLowerCase()) ? name.slice(0, -ext.value.length) : name
  form.show = !file.hide
  form.ispwd = !!file.pwd
  form.pwd = file.pwd || ''
}, { immediate: true })

function randomPwd() {
  const chars = 'abcdefghjkmnpqrstuvwxyz23456789'
  const bytes = crypto.getRandomValues(new Uint8Array(4))
  form.pwd = Array.from(bytes, b => chars[b % chars.length]).join('')
}

watch(() => form.ispwd, (v) => {
  if (v && !form.pwd) randomPwd()
})

async function onSubmit() {
  if (!props.file) return
  const base = form.base.trim()
  if (!base) {
    toast.add({ title: '文件名不能为空', color: 'warning' })
    return
  }
  if (form.ispwd && !/^[a-zA-Z0-9]{1,32}$/.test(form.pwd.trim())) {
    toast.add({ title: '密码只能为字母和数字，最多32位', color: 'warning' })
    return
  }
  saving.value = true
  try {
    const res = await post('fileEdit', {
      hash: props.file.hash,
      name: base + ext.value,
      show: form.show,
      ispwd: form.ispwd,
      pwd: form.ispwd ? form.pwd.trim() : '',
      csrf_token: await csrf()
    })
    toast.add({ title: res.msg || '修改成功', color: 'success', icon: 'i-lucide-circle-check' })
    open.value = false
    emit('saved', res.data as FileEditResult)
  } catch (e: any) {
    toast.add({ title: e?.message || '保存失败', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!-- 点击弹窗外侧不关闭，避免误操作丢失已修改的内容 -->
  <UModal v-model:open="open" title="修改文件信息" :dismissible="false">
    <template #body>
      <form id="site-file-edit-form" class="space-y-4" @submit.prevent="onSubmit">
        <UFormField label="文件名" :help="ext ? '扩展名不能修改' : undefined">
          <UFieldGroup class="w-full">
            <UInput v-model="form.base" class="w-full" placeholder="文件名" autofocus />
            <UBadge v-if="ext" color="neutral" variant="outline" size="lg" class="max-w-32 truncate">{{ ext }}</UBadge>
          </UFieldGroup>
        </UFormField>
        <USwitch v-model="form.show" label="在首页文件列表显示" description="关闭后只有拿到链接的人才能看到这个文件" />
        <USwitch v-model="form.ispwd" label="设定密码" description="查看和下载文件需要输入密码，修改密码后旧的分享链接会失效" />
        <UFormField v-if="form.ispwd" label="文件密码" help="只能为字母和数字">
          <UFieldGroup class="w-full">
            <UInput v-model="form.pwd" class="w-full font-mono" maxlength="32" autocomplete="off" />
            <UButton color="neutral" variant="outline" icon="i-lucide-refresh-cw" label="随机" @click="randomPwd" />
          </UFieldGroup>
        </UFormField>
      </form>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton color="neutral" variant="outline" label="取消" @click="open = false" />
        <UButton type="submit" form="site-file-edit-form" label="保存" :loading="saving" />
      </div>
    </template>
  </UModal>
</template>
