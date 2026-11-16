/**
 * 前端编译菜单
 *
 * 用法：node scripts/build-menu.mjs [1|2|3]，带序号参数时直接编译，不显示菜单
 */
import { spawnSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createInterface } from 'node:readline'

const frontendDir = resolve(dirname(fileURLToPath(import.meta.url)), '..')

const TASKS = {
  1: { script: 'build', label: '前台 + 后台' },
  2: { script: 'build:site', label: '前台' },
  3: { script: 'build:admin', label: '后台' }
}

const line = ' ======================================'

/**
 * 运行 pnpm 命令，输出直接显示在当前窗口，返回是否成功
 * Windows 下 pnpm 是 .cmd 文件，需要通过 shell 运行；命令都是固定的，直接拼成字符串
 */
function pnpm(command, stdio = 'inherit') {
  const result = spawnSync(`pnpm ${command}`, { cwd: frontendDir, stdio, shell: true })
  return result.status === 0
}

function hasPnpm() {
  return pnpm('--version', 'ignore')
}

function run(task) {
  if (!existsSync(resolve(frontendDir, 'node_modules'))) {
    console.log('\n首次编译，正在安装依赖...')
    if (!pnpm('install')) return false
  }
  console.log(`\n开始编译${task.label}...\n`)
  if (!pnpm(`run ${task.script}`)) return false
  console.log(`\n${line}\n    编译完成：${task.label}\n    入口页面已部署为 index.php\n${line}`)
  return true
}

function showMenu() {
  console.log(`\n${line}\n    彩虹外链网盘 前端编译\n${line}`)
  console.log('    1. 编译前台 + 后台')
  console.log('    2. 只编译前台')
  console.log('    3. 只编译后台')
  console.log('    0. 退出')
  console.log(`${line}\n`)
}

/** 读取菜单选择；输入流结束（例如通过管道输入）时返回 null */
async function askChoice() {
  const rl = createInterface({ input: process.stdin, output: process.stdout })
  const lines = rl[Symbol.asyncIterator]()
  try {
    while (true) {
      process.stdout.write('请输入序号后按回车：')
      const { value, done } = await lines.next()
      if (done) return null
      const choice = value.trim()
      if (choice === '0' || TASKS[choice]) return choice
      if (choice !== '') console.log('输入有误，请输入 1、2、3 或 0')
    }
  } finally {
    rl.close()
  }
}

if (!hasPnpm()) {
  console.log('未找到 pnpm，请先执行 npm install -g pnpm 安装')
  process.exit(1)
}

let choice = process.argv[2]
if (choice !== undefined && !TASKS[choice]) {
  console.log(`参数有误：${choice}，请使用 1（前台 + 后台）、2（前台）或 3（后台）`)
  process.exit(1)
}
if (choice === undefined) {
  showMenu()
  choice = await askChoice()
  if (choice === null || choice === '0') process.exit(0)
}

if (!run(TASKS[choice])) {
  console.log('\n 编译失败，请查看上面的错误信息')
  process.exit(1)
}
