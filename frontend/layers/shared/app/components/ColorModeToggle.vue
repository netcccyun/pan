<script setup lang="ts">
// 夜间模式切换按钮：默认跟随系统，点击后在浅色/深色之间切换，选择会保存在浏览器本地（nuxt-color-mode）
const colorMode = useColorMode()

const isDark = computed(() => colorMode.value === 'dark')
const label = computed(() => (isDark.value ? '切换到浅色模式' : '切换到夜间模式'))

function toggle() {
  colorMode.preference = isDark.value ? 'light' : 'dark'
}
</script>

<template>
  <UTooltip :text="label">
    <UButton
      :icon="isDark ? 'i-lucide-moon' : 'i-lucide-sun'"
      color="neutral"
      variant="ghost"
      :aria-label="label"
      @click="toggle"
    />
  </UTooltip>
</template>
