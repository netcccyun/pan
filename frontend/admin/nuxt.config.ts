const phpTarget = process.env.NUXT_PHP_TARGET || 'http://localhost'
// 开发时代理到的后台目录
const adminPath = process.env.NUXT_ADMIN_PATH || '/admin'

const proxy = (path: string) => ({ target: `${phpTarget}${path}`, changeOrigin: true })

// 后台管理：所有请求和资源都使用相对路径，后台目录可以任意改名
export default defineNuxtConfig({
  extends: ['../layers/shared'],

  app: {
    head: {
      title: '管理中心',
      link: [{ rel: 'icon', href: '../favicon.ico' }]
    }
  },

  icon: {
    clientBundle: {
      icons: [
        'lucide:circle-check',
        'lucide:circle-x',
        'lucide:clock',
        // 存储类型图标由后端返回，需要显式打包
        'lucide:hard-drive',
        'lucide:cloud',
        'lucide:server',
        'lucide:folder-up',
        'lucide:terminal',
        'lucide:circle-help'
      ]
    }
  },

  nitro: {
    devProxy: {
      '/ajax.php': proxy(`${adminPath}/ajax.php`),
      '/down.php': proxy(`${adminPath}/down.php`),
      '/view.php': proxy(`${adminPath}/view.php`),
      '/favicon.ico': proxy('/favicon.ico')
    }
  }
})
