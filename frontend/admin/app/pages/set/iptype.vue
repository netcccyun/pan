<script setup lang="ts">
import type { SettingField, SettingOption } from '~/utils/settings'

const page = settingPage('iptype')
useHead({ title: page.label })

const { conf, loading, load } = useSettings()
const { get } = useApi()

const options = ref<SettingOption[]>([
  { label: '0_X_FORWARDED_FOR', value: '0' },
  { label: '1_X_REAL_IP', value: '1' },
  { label: '2_REMOTE_ADDR', value: '2' }
])

onMounted(async () => {
  try {
    const res = await get('iptype')
    options.value = (res.data as { value: number, name: string, ip: string, city: string }[])
      .map(item => ({ value: String(item.value), label: `${item.name} - ${item.ip} ${item.city || ''}`.trim() }))
  } catch {}
})

const fields = computed<SettingField[]>(() => [
  { key: 'ip_type', label: '用户IP地址获取方式', type: 'select', options: options.value }
])
</script>

<template>
  <AdminPage id="set-iptype" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl space-y-4">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>
      <UAlert color="info" variant="subtle" icon="i-lucide-info">
        <template #description>
          此功能设置用于防止用户伪造IP请求。<br>
          X_FORWARDED_FOR：之前的获取真实IP方式，极易被伪造IP<br>
          X_REAL_IP：在网站使用CDN的情况下选择此项，在不使用CDN的情况下也会被伪造<br>
          REMOTE_ADDR：直接获取真实请求IP，无法被伪造，但可能获取到的是CDN节点IP<br>
          <b>你可以从中选择一个能显示你真实地址的IP，优先选下方的选项。</b>
        </template>
      </UAlert>
    </div>
  </AdminPage>
</template>
