<script setup lang="ts">
import type Artplayer from 'artplayer'

const props = defineProps<{ src: string, type: string, fill?: boolean }>()

const container = ref<HTMLDivElement>()
const failed = ref(false)
let art: Artplayer | null = null
/** 组件已卸载（播放器异步加载期间关闭了窗口或切换了页面） */
let unmounted = false

onMounted(async () => {
  if (!container.value) return
  const { default: Artplayer } = await import('artplayer')
  // 加载完成前已卸载则不再创建播放器，否则视频会在后台继续下载且无法销毁
  if (unmounted || !container.value) return
  const type = (props.type || '').toLowerCase()

  art = new Artplayer({
    container: container.value,
    url: props.src,
    type: type === 'm3u8' ? 'm3u8' : (type === 'flv' || type === 'f4v') ? 'flv' : '',
    lang: 'zh-cn',
    theme: '#6366f1',
    volume: 0.8,
    setting: true,
    playbackRate: true,
    aspectRatio: true,
    flip: true,
    screenshot: true,
    pip: true,
    hotkey: true,
    mutex: true,
    fullscreen: true,
    fullscreenWeb: true,
    miniProgressBar: true,
    playsInline: true,
    autoPlayback: true,
    autoOrientation: true,
    fastForward: true,
    lock: true,
    airplay: true,
    moreVideoAttr: { preload: 'metadata' },
    customType: {
      async m3u8(video: HTMLVideoElement, url: string, player: Artplayer) {
        const { default: Hls } = await import('hls.js')
        if (player.isDestroy) return
        if (!Hls.isSupported()) {
          if (video.canPlayType('application/vnd.apple.mpegurl')) video.src = url
          else player.notice.show = '当前浏览器不支持播放 m3u8 视频'
          return
        }
        const hls = new Hls()
        hls.loadSource(url)
        hls.attachMedia(video)
        hls.on(Hls.Events.ERROR, (_e, data) => { if (data.fatal) failed.value = true })
        player.on('destroy', () => hls.destroy())
      },
      async flv(video: HTMLVideoElement, url: string, player: Artplayer) {
        const { default: mpegts } = await import('mpegts.js')
        if (player.isDestroy) return
        if (!mpegts.isSupported()) {
          player.notice.show = '当前浏览器不支持播放 flv 视频'
          return
        }
        const flv = mpegts.createPlayer({ type: 'flv', url })
        flv.attachMediaElement(video)
        flv.load()
        flv.on(mpegts.Events.ERROR, () => { failed.value = true })
        player.on('destroy', () => flv.destroy())
      }
    }
  })
  art.on('video:error', () => { failed.value = true })
})

onBeforeUnmount(() => {
  unmounted = true
  // destroy 会清除 video 的地址并中断下载
  art?.destroy(false)
  art = null
})
</script>

<template>
  <div class="relative w-full bg-black overflow-hidden" :class="fill ? 'h-full' : 'rounded-lg aspect-video max-h-[70vh]'">
    <div ref="container" class="size-full" />
    <div v-if="failed" class="pointer-events-none absolute inset-x-0 top-4 mx-auto w-fit max-w-[90%] rounded-md bg-black/70 px-3 py-2 text-center text-sm text-white">
      当前浏览器可能不支持在线播放该格式视频，请下载后观看
    </div>
  </div>
</template>
