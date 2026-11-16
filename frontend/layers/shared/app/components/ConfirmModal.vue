<script setup lang="ts">
const props = withDefaults(defineProps<{
  title?: string
  description?: string
  confirmText?: string
  color?: 'primary' | 'error' | 'warning' | 'success' | 'neutral'
  /** 点击确定后执行的操作，执行期间确定按钮显示加载中，完成后关闭弹窗 */
  onConfirm?: () => Promise<unknown>
}>(), {
  title: '提示',
  description: '',
  confirmText: '确定',
  color: 'primary',
  onConfirm: undefined
})

/** true 确定，false 取消，{ error } 表示 onConfirm 执行出错 */
const emit = defineEmits<{ close: [boolean | { error: unknown }] }>()

const loading = ref(false)

async function confirm() {
  if (!props.onConfirm) {
    emit('close', true)
    return
  }
  loading.value = true
  try {
    await props.onConfirm()
    emit('close', true)
  } catch (error) {
    emit('close', { error })
  } finally {
    loading.value = false
  }
}

function cancel() {
  if (!loading.value) emit('close', false)
}
</script>

<template>
  <UModal
    :title="title"
    :description="description"
    :dismissible="!loading"
    :close="!loading"
    :ui="{ footer: 'justify-end' }"
    @update:open="(v: boolean) => !v && cancel()"
  >
    <template #footer>
      <UButton color="neutral" variant="outline" label="取消" :disabled="loading" @click="cancel" />
      <UButton :color="color" :label="confirmText" :loading="loading" @click="confirm" />
    </template>
  </UModal>
</template>
