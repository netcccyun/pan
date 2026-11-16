<script setup lang="ts">
import type { Conf, SettingField } from '~/utils/settings'

const page = settingPage('green')
useHead({ title: page.label })

const { conf, loading, load } = useSettings()

const isAliyun = (c: Conf) => c.green_check === '1'
const isQcloud = (c: Conf) => c.green_check === '2'

const fields: SettingField[] = [
  {
    key: 'green_check',
    label: '图片违规检测',
    type: 'select',
    options: [
      { label: '关闭', value: '0' },
      { label: '阿里云内容安全接口', value: '1' },
      { label: '腾讯云内容安全接口', value: '2' }
    ]
  },

  { key: 'aliyun_ak', label: '阿里云AccessKey Id', show: isAliyun },
  { key: 'aliyun_sk', label: '阿里云AccessKey Secret', show: isAliyun },
  {
    key: 'green_check_region',
    label: '图片检测接入区域',
    type: 'select',
    help: '你可以选择一个离本站服务器最近的以提升检测速度',
    show: isAliyun,
    options: [
      { label: '华北2（北京）', value: 'cn-beijing' },
      { label: '华东2（上海）', value: 'cn-shanghai' },
      { label: '华南1（深圳）', value: 'cn-shenzhen' },
      { label: '新加坡', value: 'ap-southeast-1' },
      { label: '美西', value: 'us-west-1' }
    ]
  },
  { key: 'green_check_porn', label: '图片智能鉴黄', type: 'switch', show: isAliyun },
  {
    key: 'green_label_porn',
    label: '图片智能鉴黄屏蔽类型',
    type: 'checkboxes',
    show: c => isAliyun(c) && c.green_check_porn === '1',
    options: [
      { label: '色情图片（porn）', value: 'porn' },
      { label: '性感图片（sexy）', value: 'sexy' }
    ]
  },
  { key: 'green_check_terrorism', label: '图片暴恐涉政识别', type: 'switch', show: isAliyun },
  {
    key: 'green_label_terrorism',
    label: '图片暴恐涉政识别屏蔽类型',
    type: 'checkboxes',
    show: c => isAliyun(c) && c.green_check_terrorism === '1',
    options: [
      { label: '血腥（bloody）', value: 'bloody' },
      { label: '爆炸烟光（explosion）', value: 'explosion' },
      { label: '特殊装束（outfit）', value: 'outfit' },
      { label: '特殊标识（logo）', value: 'logo' },
      { label: '武器（weapon）', value: 'weapon' },
      { label: '涉政（politics）', value: 'politics' },
      { label: '打斗（violence）', value: 'violence' },
      { label: '聚众（crowd）', value: 'crowd' },
      { label: '游行（parade）', value: 'parade' },
      { label: '车祸现场（carcrash）', value: 'carcrash' },
      { label: '旗帜（flag）', value: 'flag' },
      { label: '地标（location）', value: 'location' },
      { label: '涉毒（drug）', value: 'drug' },
      { label: '赌博（gamble）', value: 'gamble' }
    ]
  },

  { key: 'qcloud_green_id', label: '腾讯云SecretId', show: isQcloud },
  { key: 'qcloud_green_key', label: '腾讯云SecretKey', show: isQcloud },
  {
    key: 'green_check_region',
    label: '图片检测接入区域',
    type: 'select',
    help: '你可以选择一个离本站服务器最近的以提升检测速度',
    show: isQcloud,
    options: [
      { label: '华北地区(北京)', value: 'ap-beijing' },
      { label: '华东地区(上海)', value: 'ap-shanghai' },
      { label: '华南地区(广州)', value: 'ap-guangzhou' },
      { label: '亚太南部(孟买)', value: 'ap-mumbai' },
      { label: '亚太东南(新加坡)', value: 'ap-singapore' },
      { label: '欧洲地区(法兰克福)', value: 'eu-frankfurt' },
      { label: '美国东部(弗吉尼亚)', value: 'na-ashburn' },
      { label: '美国西部(硅谷)', value: 'na-siliconvalley' }
    ]
  },

  { key: 'apiurl', label: '图片检测访问网址', placeholder: '不填写则默认使用当前网址', help: '此处是图片检测的时候云服务访问本站的网址，不填写则默认使用当前网址，如果填写必需以http://开头，以/结尾' }
]
</script>

<template>
  <AdminPage id="set-green" :title="page.label" :icon="page.icon">
    <div class="mx-auto w-full max-w-3xl space-y-4">
      <UCard>
        <SettingForm :fields="fields" :conf="conf" :loading="loading" @saved="load" />
      </UCard>
      <UAlert color="info" variant="subtle" icon="i-lucide-info">
        <template #description>
          阿里云内容安全接口：<a href="https://yundun.console.aliyun.com/?p=cts#/api/statistics" target="_blank" rel="noreferrer" class="underline">点此进入</a>｜<a href="https://usercenter.console.aliyun.com/#/manage/ak" target="_blank" rel="noreferrer" class="underline">获取密钥</a><br>
          腾讯云内容安全接口：<a href="https://cloud.tencent.com/product/ims" target="_blank" rel="noreferrer" class="underline">点此进入</a>｜<a href="https://console.cloud.tencent.com/cam/capi" target="_blank" rel="noreferrer" class="underline">获取密钥</a><br>
          屏蔽类型选不选都可以，会同时根据返回的建议结果进行屏蔽
        </template>
      </UAlert>
    </div>
  </AdminPage>
</template>
