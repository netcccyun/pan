<script setup lang="ts">
import APlayer from 'vue3-aplayer'
import 'vue3-aplayer/style.css'

const props = defineProps<{ src: string, name: string, compact?: boolean }>()

const THEME = '#6366f1'

const cover = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#818cf8"/><stop offset="1" stop-color="#4f46e5"/></linearGradient></defs><rect width="100" height="100" fill="url(#g)"/><g fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"><path d="M42 68V32l30-6v36"/><circle cx="34" cy="68" r="8"/><circle cx="64" cy="62" r="8"/></g></svg>`
).replace(/\(/g, '%28').replace(/\)/g, '%29')}`

const music = computed(() => ({
  src: props.src,
  title: props.name,
  artist: getExt(props.name).toUpperCase() || 'AUDIO',
  pic: cover
}))

const errorMsg = ref('')

const root = ref<HTMLDivElement>()

/**
 * 中断音频下载：APlayer 卸载时不会清除 audio 的地址，
 * 被移除的 audio 会继续下载，占用浏览器连接和服务器进程，导致后续请求卡住
 */
function stopAudio() {
  const audio = root.value?.querySelector('audio')
  if (!audio) return
  audio.pause()
  audio.removeAttribute('src')
  audio.load()
}

// 切换音频时 APlayer 会按 key 重建，先停止旧的音频
watch(() => props.src, stopAudio, { flush: 'pre' })
onBeforeUnmount(stopAudio)
</script>

<template>
  <div ref="root" class="audio-player w-full" :class="{ compact }">
    <APlayer
      :key="props.src"
      :music="music"
      :theme="THEME"
      preload="metadata"
      repeat="no-repeat"
      @play="errorMsg = ''"
      @error="errorMsg = '音频加载失败，文件可能不存在或当前浏览器不支持该格式'"
    />
    <p v-if="errorMsg" class="mt-1.5 text-xs text-(--ui-error)" :class="{ 'px-2': compact }">{{ errorMsg }}</p>
  </div>
</template>

<style scoped>
.audio-player :deep(.aplayer) {
  margin: 0;
  max-width: none;
  width: 100%;
  border-radius: 0.5rem;
}
.audio-player.compact :deep(.aplayer) {
  border-radius: 0;
  box-shadow: none;
}
</style>

<style>
/* APlayer 自带的样式只有浅色，夜间模式下改用 Nuxt UI 的颜色变量 */
.dark .audio-player .aplayer {
  color: var(--ui-text);
  background-color: var(--ui-bg-elevated);
  box-shadow: 0 0 0 1px var(--ui-border);
}
.dark .audio-player.compact .aplayer {
  box-shadow: none;
}
.dark .audio-player .aplayer .aplayer-body .aplayer-info .aplayer-music .aplayer-author,
.dark .audio-player .aplayer-controller .aplayer-time,
.dark .audio-player .aplayer-lrc p,
.dark .audio-player .aplayer-list ol li .aplayer-list-index,
.dark .audio-player .aplayer-list ol li .aplayer-list-author {
  color: var(--ui-text-muted);
}
.dark .audio-player .aplayer-controller .aplayer-time .aplayer-icon .aplayer-fill {
  fill: var(--ui-text-muted);
}
.dark .audio-player .aplayer-controller .aplayer-time .aplayer-icon:hover .aplayer-fill {
  fill: var(--ui-text-highlighted);
}
.dark .audio-player .aplayer-bar-wrap .aplayer-bar {
  background: var(--ui-border-accented);
}
.dark .audio-player .aplayer-bar-wrap .aplayer-bar .aplayer-loaded {
  background: var(--ui-text-dimmed);
}
.dark .audio-player .aplayer-volume-wrap .aplayer-volume-bar-wrap:after {
  background-color: var(--ui-bg-elevated);
}
.dark .audio-player .aplayer-lrc:before {
  background: linear-gradient(var(--ui-bg-elevated) 0%, transparent 100%);
}
.dark .audio-player .aplayer-lrc:after {
  background: linear-gradient(transparent 0%, color-mix(in oklab, var(--ui-bg-elevated) 80%, transparent) 100%);
}
.dark .audio-player .aplayer-list ol li {
  border-top-color: var(--ui-border);
}
.dark .audio-player .aplayer-list ol li:hover,
.dark .audio-player .aplayer-list ol li.aplayer-list-light {
  background: var(--ui-bg-accented);
}
.dark .audio-player .aplayer.aplayer-withlist .aplayer-body .aplayer-info {
  border-bottom-color: var(--ui-border);
}
</style>
