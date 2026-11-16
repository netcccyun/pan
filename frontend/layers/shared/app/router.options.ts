import type { RouterConfig } from '@nuxt/schema'
import { createWebHashHistory } from 'vue-router'

// 不传 base，hash 前的路径取当前页面地址，网站部署在子目录时也能正常工作
export default {
  hashMode: true,
  history: () => createWebHashHistory(),
  scrollBehavior: (_to, _from, savedPosition) => savedPosition || { top: 0 }
} satisfies RouterConfig
