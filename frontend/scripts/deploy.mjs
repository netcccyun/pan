// 将 nuxt generate 的产物复制到网站目录，只替换入口页面和 assets/_nuxt 目录
// 入口页面 index.html 改名为 index.php 输出，避免被浏览器或CDN缓存
// 用法：node scripts/deploy.mjs site            → 网站根目录
//       node scripts/deploy.mjs admin [目录名]  → 后台目录（默认 admin，后台目录改名后可传入新名称）
import { cp, rm, access, readFile, writeFile } from 'node:fs/promises'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const frontendDir = resolve(here, '..')
const siteRoot = resolve(frontendDir, '..')
const assetsDir = 'assets/_nuxt'

const [target = 'site', adminDirName = 'admin'] = process.argv.slice(2)
if (!['site', 'admin'].includes(target)) {
  console.error(`未知的部署目标：${target}，可选 site 或 admin`)
  process.exit(1)
}

const publicDir = target === 'admin'
  ? resolve(frontendDir, 'admin/.output/public')
  : resolve(frontendDir, '.output/public')
const destDir = target === 'admin' ? resolve(siteRoot, adminDirName) : siteRoot

async function exists(path) {
  try {
    await access(path)
    return true
  } catch {
    return false
  }
}

if (!(await exists(resolve(publicDir, 'index.html')))) {
  console.error(`未找到构建产物：${publicDir}，请先执行 nuxt generate`)
  process.exit(1)
}
if (!(await exists(destDir))) {
  console.error(`部署目录不存在：${destDir}`)
  process.exit(1)
}

if (target === 'site') await rm(resolve(destDir, '_nuxt'), { recursive: true, force: true })
await rm(resolve(destDir, assetsDir), { recursive: true, force: true })
await cp(resolve(publicDir, assetsDir), resolve(destDir, assetsDir), { recursive: true })
// 资源路径改为相对路径，使网站（以及任意名称的后台目录）可以部署在任意子目录
const html = (await readFile(resolve(publicDir, 'index.html'), 'utf8'))
  .replaceAll(`"/${assetsDir}/`, `"./${assetsDir}/`)
  .replace(`buildAssetsDir:"./${assetsDir}/"`, `buildAssetsDir:"/${assetsDir}/"`)
  .replace('baseURL:"/"', 'baseURL:"./"')
  // 页面内容按原样输出，避免其中的 <? 被 PHP 当作代码解析
  .replaceAll('<?', '<?php echo \'<?\'; ?>')

// 入口页面保存为 index.php，避免被当作静态文件缓存
await writeFile(resolve(destDir, 'index.php'), html)
// 删除旧版的 index.html，避免 Web 服务器优先使用它
await rm(resolve(destDir, 'index.html'), { force: true })

console.log(`已部署到 ${destDir}（index.php、${assetsDir}/）`)
