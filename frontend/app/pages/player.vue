<script setup lang="ts">
definePageMeta({ layout: 'blank' })

const route = useRoute()
const { rawGet } = useApi()

const hash = computed(() => String(route.query.hash || ''))
const data = ref<{ name: string, type: string, view_type: string, viewurl: string } | null>(null)
const errorMsg = ref('')

useHead({
  title: () => (data.value ? (data.value.view_type === 'audio' ? '音乐播放器' : '视频播放器') : '播放器'),
  htmlAttrs: { style: 'overflow:hidden' },
  bodyAttrs: { style: 'margin:0;background:transparent' }
})

onMounted(async () => {
  try {
    const res = await rawGet('fileinfo', { hash: hash.value, player: 1 })
    if (res.code === 0) data.value = res.data
    else errorMsg.value = res.msg || '404 Not Found'
  } catch (e: any) {
    errorMsg.value = e?.message || '加载失败'
  }
})
</script>

<template>
  <div v-if="data?.view_type === 'audio'">
    <AudioPlayer :src="data.viewurl" :name="data.name" compact />
  </div>
  <div v-else-if="data?.view_type === 'video'" class="h-screen bg-black">
    <VideoPlayer :src="data.viewurl" :type="data.type" fill />
  </div>
  <div v-else-if="errorMsg" class="p-4 text-sm text-(--ui-text-muted)">{{ errorMsg }}</div>
</template>
