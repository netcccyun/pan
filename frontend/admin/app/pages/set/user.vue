<script setup lang="ts">
import type { SettingField } from '~/utils/settings'

const page = settingPage('user')
useHead({ title: page.label })

const { conf, loading, load } = useSettings()

const fields: SettingField[] = [
  { key: 'userlogin', label: '用户登录开关', type: 'switch' },
  { key: 'login_apiurl', label: '聚合登录接口地址', placeholder: '接口地址要以http://或https://开头，以/结尾' },
  { key: 'login_appid', label: '应用APPID' },
  { key: 'login_appkey', label: '应用APPKEY' },
  { key: 'login_qq', label: 'QQ快捷登录', type: 'switch' },
  { key: 'login_wx', label: '微信快捷登录', type: 'switch' }
]
</script>

<template>
  <AdminPage id="set-user" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl space-y-4">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>
      <UAlert color="info" variant="subtle" icon="i-lucide-info">
        <template #description>
          聚合登录接口是使用<a href="https://www.clogin.cc/recommend.php" target="_blank" rel="noreferrer" class="underline">彩虹聚合登录系统搭建的站点</a>。<br>
          开启后请勿随意更换登录接口站点，否则会导致之前注册的用户全部无法登录。
        </template>
      </UAlert>
    </div>
  </AdminPage>
</template>
