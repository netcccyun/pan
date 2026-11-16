<script setup lang="ts">
import QRCode from 'qrcode'

const props = withDefaults(defineProps<{ text: string, size?: number }>(), { size: 180 })
const src = ref('')

watch(() => props.text, async (text) => {
  if (!text) {
    src.value = ''
    return
  }
  try {
    src.value = await QRCode.toDataURL(text, { width: props.size, margin: 2 })
  } catch {
    src.value = ''
  }
}, { immediate: true })
</script>

<template>
  <img v-if="src" :src="src" :width="size" :height="size" alt="二维码" class="mx-auto rounded-md">
  <USkeleton v-else class="mx-auto" :style="{ width: `${size}px`, height: `${size}px` }" />
</template>
