<script setup lang="ts">
import type { Conf, SettingField } from '~/utils/settings'

const props = defineProps<{ fields: SettingField[], conf: Conf, loading?: boolean }>()
const emit = defineEmits<{ saved: [] }>()

const { post } = useApi()
const toast = useToast()

const form = reactive<Record<string, string>>({})
const saving = ref(false)

watch(() => [props.conf, props.fields] as const, () => {
  for (const field of props.fields) form[field.key] = fieldValue(props.conf, field)
}, { immediate: true, deep: true })

const visibleFields = computed(() => props.fields.filter(field => !field.show || field.show(form)))

// 同一配置项可能在不同条件下对应不同的选项列表，值不在当前选项中时改为第一项
watch(visibleFields, (list) => {
  for (const field of list) {
    if (field.type !== 'select' || !field.options?.length) continue
    if (!field.options.some(o => o.value === form[field.key])) form[field.key] = field.options[0]!.value
  }
}, { immediate: true })

const splitList = (value: string | undefined) => (value || '').split(',').filter(Boolean)

async function onSubmit() {
  for (const field of visibleFields.value) {
    if (field.required && !form[field.key]?.trim()) {
      toast.add({ title: `请填写${field.label}`, color: 'warning' })
      return
    }
  }
  const data: Record<string, string> = {}
  for (const field of visibleFields.value) {
    const value = form[field.key] ?? ''
    data[field.key] = field.transform ? field.transform(value) : value
  }
  saving.value = true
  try {
    await post('set', data)
    toast.add({ title: '设置保存成功', color: 'success', icon: 'i-lucide-circle-check' })
    emit('saved')
  } catch (e: any) {
    toast.add({ title: e?.message || '保存失败', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="space-y-5">
    <div v-for="i in 4" :key="i" class="space-y-2">
      <USkeleton class="h-4 w-28" />
      <USkeleton class="h-9 w-full" />
    </div>
  </div>
  <form v-else class="space-y-5" @submit.prevent="onSubmit">
    <template v-for="field in visibleFields" :key="field.key">
      <!-- 开关：标签和开关同一行，帮助信息显示在下方 -->
      <div v-if="field.type === 'switch'">
        <UFormField
          :label="field.label"
          orientation="horizontal"
          :ui="{ container: 'flex items-center' }"
        >
          <USwitch :model-value="form[field.key] === '1'" @update:model-value="(v: boolean) => (form[field.key] = v ? '1' : '0')" />
        </UFormField>
        <p v-if="field.help" class="mt-2 text-sm text-(--ui-text-muted)">{{ field.help }}</p>
      </div>

      <UFormField v-else :label="field.label" :help="field.help" :required="field.required">
        <USelect
          v-if="field.type === 'select'"
          v-model="form[field.key]"
          :items="field.options"
          class="w-full"
        />
        <UTextarea
          v-else-if="field.type === 'textarea'"
          v-model="form[field.key]"
          :rows="field.rows || 3"
          :placeholder="field.placeholder"
          autoresize
          class="w-full"
        />
        <UCheckboxGroup
          v-else-if="field.type === 'checkboxes'"
          :model-value="splitList(form[field.key])"
          :items="field.options"
          orientation="horizontal"
          :ui="{ fieldset: 'flex-wrap gap-x-4 gap-y-2' }"
          @update:model-value="(v: any[]) => (form[field.key] = v.join(','))"
        />
        <UInput
          v-else
          v-model="form[field.key]"
          :placeholder="field.placeholder"
          class="w-full"
          :ui="field.suffix ? { trailing: 'pointer-events-none' } : undefined"
        >
          <template v-if="field.suffix" #trailing>
            <span class="text-xs text-(--ui-text-muted)">{{ field.suffix }}</span>
          </template>
        </UInput>
      </UFormField>
    </template>

    <slot name="extra" :form="form" />

    <div class="pt-2">
      <UButton type="submit" label="保存设置" icon="i-lucide-save" :loading="saving" />
    </div>
  </form>
</template>
