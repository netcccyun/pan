<script setup lang="ts">
const { status, halt } = useAdmin()

useHead({
  titleTemplate: (title?: string) => {
    const site = status.value.title ? `${status.value.title}管理中心` : '管理中心'
    return title ? `${title} - ${site}` : site
  }
})
</script>

<template>
  <UApp :toaster="{ position: 'top-center' }">
    <HaltNotice v-if="halt" v-bind="halt" />
    <NuxtLayout v-else>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
