<script setup lang="ts">
import type { PreviewFile } from '~/utils/types'

const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ file: PreviewFile | null }>()

const { get } = useApi()

const width = computed(() => {
  if (props.file?.view_type === 'audio') return 'sm:max-w-2xl'
  if (props.file?.view_type === 'text') return 'sm:max-w-5xl'
  return 'sm:max-w-4xl'
})

const image = ref<HTMLImageElement>()

/** 关闭窗口或切换文件时中断还没加载完的图片，避免大图在后台继续下载占用连接 */
function stopImage() {
  const img = image.value
  if (img && !img.complete) img.removeAttribute('src')
}
watch(open, (value) => { if (!value) stopImage() })
watch(() => props.file, stopImage, { flush: 'pre' })

async function loadText() {
  const data = await get('fileContent', { id: props.file?.id })
  return String(data.content ?? '')
}
</script>

<template>
  <UModal v-model:open="open" :title="file?.name || '文件预览'" :ui="{ content: width, body: 'p-3 sm:p-5' }">
    <template #body>
      <template v-if="file">
        <div v-if="file.view_type === 'image'" class="text-center">
          <a :href="file.viewurl" target="_blank" title="点击查看原图">
            <img ref="image" :src="file.viewurl" :alt="file.name" class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain">
          </a>
        </div>
        <AudioPlayer v-else-if="file.view_type === 'audio'" :src="file.viewurl" :name="file.name" />
        <VideoPlayer v-else-if="file.view_type === 'video'" :src="file.viewurl" :type="file.type" />
        <TextPreview v-else-if="file.view_type === 'text'" :key="file.id" :type="file.type" :load="loadText" max-height="60vh" />
        <div v-else class="py-8 text-center text-sm text-(--ui-text-muted)">该文件类型不支持在线预览</div>
      </template>
    </template>
    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton v-if="file" :href="file.fileurl" icon="i-lucide-download" label="下载文件" variant="soft" external />
        <UButton color="neutral" variant="outline" label="关闭" @click="open = false" />
      </div>
    </template>
  </UModal>
</template>
