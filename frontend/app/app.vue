<script setup lang="ts">
const { site, halt } = useSite()

useHead({
  titleTemplate: (title?: string) => (title ? `${title} - ${site.value.title}` : site.value.title),
  meta: [
    { name: 'keywords', content: () => site.value.keywords },
    { name: 'description', content: () => site.value.description }
  ]
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
