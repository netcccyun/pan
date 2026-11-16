<script setup lang="ts">
const props = defineProps<{ msg: string, url: string, auto?: boolean }>()

const countdown = ref(3)
let timer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  if (!props.auto) return
  timer = setInterval(() => {
    if (--countdown.value <= 0) {
      clearInterval(timer)
      location.href = props.url
    }
  }, 1000)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-(--ui-bg-muted) p-4">
    <UCard class="w-full max-w-md">
      <div class="py-4 text-center">
        <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-warning/10">
          <UIcon name="i-lucide-triangle-alert" class="size-9 text-warning" />
        </div>
        <h2 class="text-lg font-semibold">站点提示信息</h2>
        <p class="mt-3 text-sm leading-relaxed text-(--ui-text-muted)">{{ msg }}</p>
        <UButton class="mt-6" :href="url" size="lg" trailing-icon="i-lucide-arrow-right" external>
          {{ url.includes('update') ? '前往升级' : '前往安装' }}{{ auto && countdown > 0 ? `（${countdown}s）` : '' }}
        </UButton>
      </div>
    </UCard>
  </div>
</template>
