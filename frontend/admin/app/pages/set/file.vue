<script setup lang="ts">
import type { SettingField } from '~/utils/settings'

const page = settingPage('file')
useHead({ title: page.label })

const { conf, loading, load } = useSettings()

const fields: SettingField[] = [
  { key: 'type_image', label: '图片文件类型', placeholder: '多个文件类型用|隔开', help: '在文件预览页面，以上文件类型将以图片的形式展示' },
  { key: 'type_audio', label: '音频文件类型', placeholder: '多个文件类型用|隔开', help: '在文件预览页面，以上文件类型将以音频的形式展示' },
  { key: 'type_video', label: '视频文件类型', placeholder: '多个文件类型用|隔开', help: '在文件预览页面，以上文件类型将以视频的形式展示' },
  { key: 'type_block', label: '禁止上传的文件类型', placeholder: '多个文件类型用|隔开' },
  { key: 'name_block', label: '文件名屏蔽关键词', placeholder: '多个关键词用|隔开' },
  { key: 'upload_limit', label: '每IP每天限制上传数量', placeholder: '0或留空为不限制' },
  { key: 'upload_size', label: '上传大小限制', placeholder: '不填写则不限制大小', suffix: 'MB' },
  { key: 'videoreview', label: '视频文件需要审核', type: 'switch' },
  { key: 'forcelogin', label: '仅限登录用户上传', type: 'switch' },
  { key: 'upload_choose_storage', label: '允许选择存储类型', type: 'switch', help: '开启后，用户在上传页面可以选择所有已开启的存储位置' }
]
</script>

<template>
  <AdminPage id="set-file" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>
    </div>
  </AdminPage>
</template>
