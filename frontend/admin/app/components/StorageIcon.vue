<script setup lang="ts">
/** 存储类型图标：i-lucide-xxx 为图标名，否则为相对网站根目录的图片地址（后台位于网站下一级目录） */
const props = defineProps<{ icon?: string | null }>()

const isIconName = computed(() => !props.icon || props.icon.startsWith('i-'))
const src = computed(() => /^(https?:)?\/\//.test(props.icon || '') ? props.icon! : `../${props.icon}`)
</script>

<template>
  <UIcon v-if="isIconName" :name="icon || 'i-lucide-cloud'" />
  <img v-else :src="src" alt="" class="object-contain" draggable="false">
</template>
