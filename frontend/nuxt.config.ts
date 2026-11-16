const phpTarget = process.env.NUXT_PHP_TARGET || 'http://localhost'

const proxy = (path: string) => ({ target: `${phpTarget}${path}`, changeOrigin: true })

// 公共配置在 layers/shared（Nuxt 会自动加载 layers 目录）
export default defineNuxtConfig({
  app: {
    head: {
      link: [{ rel: 'icon', href: 'favicon.ico' }]
    }
  },

  icon: {
    clientBundle: {
      icons: [
        'lucide:file-x',
        'lucide:house',
        'simple-icons:qq',
        'simple-icons:wechat'
      ]
    }
  },

  nitro: {
    devProxy: {
      '/ajax.php': proxy('/ajax.php'),
      '/login.php': proxy('/login.php'),
      '/down.php': proxy('/down.php'),
      '/view.php': proxy('/view.php'),
      '/assets/img': proxy('/assets/img'),
      '/favicon.ico': proxy('/favicon.ico')
    }
  }
})
