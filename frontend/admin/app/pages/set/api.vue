<script setup lang="ts">
import type { SettingField } from '~/utils/settings'

const page = settingPage('api')
useHead({ title: page.label })

const { conf, siteurl, loading, load } = useSettings()

const fields: SettingField[] = [
  { key: 'api_open', label: '上传API开关', type: 'switch' },
  { key: 'api_referer', label: '来源域名白名单', placeholder: '多个域名用|隔开', help: '多个域名用|隔开，不填写则不限制来源域名' }
]

const requestParams = [
  ['文件', 'file', '是', '', 'multipart格式文件'],
  ['是否首页显示', 'show', '否', '1', '默认为是'],
  ['是否设置密码', 'ispwd', '否', '0', '默认为否'],
  ['下载密码', 'pwd', '否', '123456', '默认留空'],
  ['返回格式', 'format', '否', 'json', '有json、jsonp、form三种选择，默认为json'],
  ['跳转页面url', 'backurl', '否', 'http://...', '上传成功后的跳转地址，只在form格式有效'],
  ['callback', 'callback', '否', 'callback', '只在jsonp格式有效']
]

const responseParams = [
  ['上传状态', 'code', 'Int', '0', '0为成功，其他为失败'],
  ['提示信息', 'msg', 'String', '上传成功！', '如果上传失败会有错误提示'],
  ['文件MD5', 'hash', 'String', 'f1e807cb0d6ba52d71bdb02864e6bda8', ''],
  ['文件名称', 'name', 'String', 'exapmle1.jpg', ''],
  ['文件大小', 'size', 'Int', '58937', '单位：字节'],
  ['文件格式', 'type', 'String', 'jpg', ''],
  ['下载地址', 'downurl', 'String', 'http://.....', ''],
  ['预览地址', 'viewurl', 'String', 'http://.....', '只有图片、音乐、视频文件才有']
]

const copy = useCopy()
const apiUrl = computed(() => `${siteurl.value}api.php`)
</script>

<template>
  <AdminPage id="set-api" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-4xl space-y-4">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>

      <UCard>
        <template #header>
          <h3 class="font-semibold">上传API文档</h3>
        </template>
        <div class="space-y-4 text-sm">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-(--ui-text-muted)">API接口地址：</span>
            <code class="rounded bg-(--ui-bg-elevated) px-2 py-1 break-all">{{ apiUrl }}</code>
            <UButton size="xs" color="neutral" variant="outline" icon="i-lucide-copy" label="复制" @click="copy(apiUrl)" />
          </div>
          <p>当前API支持JSON、JSONP、FORM 3种返回方式，支持Web跨域调用，也支持程序中直接调用。</p>
          <p>请求方式：<code>POST multipart/form-data</code></p>

          <div>
            <p class="mb-2 font-medium">请求参数说明：</p>
            <div class="overflow-x-auto rounded-md border border-(--ui-border)">
              <table class="w-full min-w-[560px] text-left">
                <thead class="bg-(--ui-bg-elevated)">
                  <tr><th v-for="h in ['字段名', '变量名', '是否必填', '示例值', '描述']" :key="h" class="px-3 py-2 font-medium">{{ h }}</th></tr>
                </thead>
                <tbody>
                  <tr v-for="row in requestParams" :key="row[1]" class="border-t border-(--ui-border)">
                    <td v-for="(cell, i) in row" :key="i" class="px-3 py-2" :class="{ 'font-mono': i === 1 }">{{ cell }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <p class="mb-2 font-medium">返回参数说明：</p>
            <div class="overflow-x-auto rounded-md border border-(--ui-border)">
              <table class="w-full min-w-[560px] text-left">
                <thead class="bg-(--ui-bg-elevated)">
                  <tr><th v-for="h in ['字段名', '变量名', '类型', '示例值', '描述']" :key="h" class="px-3 py-2 font-medium">{{ h }}</th></tr>
                </thead>
                <tbody>
                  <tr v-for="row in responseParams" :key="row[1]" class="border-t border-(--ui-border)">
                    <td v-for="(cell, i) in row" :key="i" class="px-3 py-2 break-all" :class="{ 'font-mono': i === 1 }">{{ cell }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </UCard>
    </div>
  </AdminPage>
</template>
