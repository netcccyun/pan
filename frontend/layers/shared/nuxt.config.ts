import { fileURLToPath } from 'node:url'

// 前台与后台共用的配置：纯客户端渲染 + hash 路由，资源输出到 assets/_nuxt，部署时改为相对路径
export default defineNuxtConfig({
  compatibilityDate: '2026-01-01',
  ssr: false,
  devtools: { enabled: false },
  modules: ['@nuxt/ui'],
  css: [fileURLToPath(new URL('./app/assets/css/main.css', import.meta.url))],

  ui: {
    fonts: false
  },

  app: {
    baseURL: '/',
    buildAssetsDir: '/assets/_nuxt/',
    head: {
      htmlAttrs: { lang: 'zh-CN' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1' },
        { name: 'renderer', content: 'webkit' },
        { name: 'format-detection', content: 'telephone=no' }
      ]
    }
  },

  icon: {
    provider: 'none',
    serverBundle: false,
    clientBundle: {
      scan: true,
      includeCustomCollections: true,
      sizeLimitKb: 512,
      // typeIcon() 动态返回的图标名无法被扫描到，需要显式打包
      icons: [
        'lucide:file',
        'lucide:file-image',
        'lucide:file-music',
        'lucide:file-video-camera',
        'lucide:file-text',
        'lucide:file-code',
        'lucide:file-archive',
        'lucide:file-type',
        'lucide:file-spreadsheet',
        'lucide:presentation',
        'lucide:app-window',
        // ColorModeToggle 的图标
        'lucide:sun',
        'lucide:moon',
        'simple-icons:android',
        'simple-icons:apple',
        'simple-icons:linux'
      ]
    }
  },

  nitro: {
    prerender: {
      crawlLinks: false,
      routes: ['/']
    }
  },

  vite: {
    build: {
      chunkSizeWarningLimit: 1500
    }
  }
})
