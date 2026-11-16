<script setup lang="ts">
const { site } = useSite()

const NOTICE_KEY = 'gonggao_closed_at'
const showNotice = ref(false)

onMounted(() => {
  if (!site.value.gonggao) return
  const last = Number(localStorage.getItem(NOTICE_KEY) || 0)
  showNotice.value = Date.now() - last >= 60 * 60 * 1000
})

function closeNotice() {
  showNotice.value = false
  localStorage.setItem(NOTICE_KEY, String(Date.now()))
}
</script>

<template>
  <div class="space-y-4">
    <UAlert
      v-if="showNotice"
      color="primary"
      variant="subtle"
      icon="i-lucide-megaphone"
      title="公告"
      :close="{ color: 'neutral', variant: 'link' }"
      @update:open="closeNotice"
    >
      <template #description>
        <div class="rich-text" v-html="site.gonggao" />
      </template>
    </UAlert>
    <FileList />
  </div>
</template>
