<script setup lang="ts">
import type { SettingField } from '~/utils/settings'

const page = settingPage('site')
useHead({ title: page.label })

const { conf, loading, load } = useSettings()

const fields: SettingField[] = [
  { key: 'title', label: '网站标题', required: true },
  { key: 'keywords', label: '关键字' },
  { key: 'description', label: '网站描述' },
  { key: 'blackip', label: '禁止访问IP', type: 'textarea', rows: 2, placeholder: '多个IP用|隔开' },
  { key: 'gonggao', label: '首页公告', type: 'textarea', placeholder: '不填写则不显示首页公告', help: '支持 HTML 代码' },
  { key: 'gg_file', label: '文件查看页公告', type: 'textarea', placeholder: '不填写则不显示', help: '支持 HTML 代码' },
  { key: 'tongji', label: '统计代码', type: 'textarea', placeholder: '不填写则不显示统计代码' },
  { key: 'filesearch', label: '文件搜索功能', type: 'switch' }
]
</script>

<template>
  <AdminPage id="set-site" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>
    </div>
  </AdminPage>
</template>
