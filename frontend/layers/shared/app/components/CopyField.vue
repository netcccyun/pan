<script setup lang="ts">
defineProps<{ label: string, value: string }>()
const copy = useCopy()
</script>

<template>
  <div class="grid gap-1.5 md:grid-cols-[7rem_1fr] md:items-center md:gap-3">
    <label class="text-sm font-medium text-(--ui-text-muted) md:text-right">{{ label }}：</label>
    <UFieldGroup class="w-full">
      <UInput :model-value="value" readonly class="flex-1 min-w-0" :ui="{ base: 'font-mono text-xs sm:text-sm' }" @focus="($event.target as HTMLInputElement).select()" />
      <UButton icon="i-lucide-copy" label="复制" @click="copy(value)" />
    </UFieldGroup>
  </div>
</template>
