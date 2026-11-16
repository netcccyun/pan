<script setup lang="ts">
defineProps<{ id: string, title: string, icon?: string }>()
</script>

<template>
  <UDashboardPanel :id="id">
    <template #header>
      <UDashboardNavbar :title="title" :icon="icon">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <slot name="right" />
          <ColorModeToggle />
        </template>
      </UDashboardNavbar>
      <slot name="toolbar" />
    </template>

    <template #body>
      <slot />
    </template>
  </UDashboardPanel>
</template>
