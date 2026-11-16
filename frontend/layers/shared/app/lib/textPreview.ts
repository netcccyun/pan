/**
 * 文本/代码文件在线预览：代码高亮与 Markdown 渲染
 * 体积较大，只在打开预览时由 TextPreview 组件按需加载
 */
import hljs from 'highlight.js/lib/common'
import dos from 'highlight.js/lib/languages/dos'
import powershell from 'highlight.js/lib/languages/powershell'
import dart from 'highlight.js/lib/languages/dart'
import scala from 'highlight.js/lib/languages/scala'
import properties from 'highlight.js/lib/languages/properties'
import gradle from 'highlight.js/lib/languages/gradle'
import vbscript from 'highlight.js/lib/languages/vbscript'
import { Marked } from 'marked'
import DOMPurify from 'dompurify'

hljs.registerLanguage('dos', dos)
hljs.registerLanguage('powershell', powershell)
hljs.registerLanguage('dart', dart)
hljs.registerLanguage('scala', scala)
hljs.registerLanguage('properties', properties)
hljs.registerLanguage('gradle', gradle)
hljs.registerLanguage('vbscript', vbscript)

/** 文件后缀对应的高亮语言，未列出的按纯文本显示 */
const extLanguages: Record<string, string> = {
  md: 'markdown', markdown: 'markdown',
  json: 'json', xml: 'xml', html: 'xml', htm: 'xml', vue: 'xml', tpl: 'xml', jsp: 'xml', asp: 'xml', aspx: 'xml',
  yaml: 'yaml', yml: 'yaml', toml: 'ini', ini: 'ini', conf: 'ini', cfg: 'ini', env: 'ini', properties: 'properties',
  css: 'css', scss: 'scss', sass: 'scss', less: 'less',
  js: 'javascript', mjs: 'javascript', cjs: 'javascript', jsx: 'javascript', ts: 'typescript', tsx: 'typescript',
  php: 'php', py: 'python', java: 'java', kt: 'kotlin', go: 'go', rs: 'rust', rb: 'ruby', pl: 'perl', lua: 'lua',
  swift: 'swift', dart: 'dart', scala: 'scala', r: 'r',
  c: 'c', h: 'c', cpp: 'cpp', cc: 'cpp', cxx: 'cpp', hpp: 'cpp', cs: 'csharp', m: 'objectivec', mm: 'objectivec',
  sql: 'sql', sh: 'bash', bash: 'bash', bat: 'dos', cmd: 'dos', ps1: 'powershell', vbs: 'vbscript', reg: 'ini',
  gradle: 'gradle', diff: 'diff', patch: 'diff'
}

function escapeHtml(text: string) {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

/** 超过此长度不做高亮，避免页面卡顿 */
const HIGHLIGHT_MAX = 512 * 1024

/** 返回高亮后的 HTML（已转义，可直接 v-html） */
export function highlightCode(code: string, ext: string): string {
  const language = extLanguages[ext.toLowerCase()]
  if (!language || code.length > HIGHLIGHT_MAX || !hljs.getLanguage(language)) return escapeHtml(code)
  try {
    return hljs.highlight(code, { language, ignoreIllegals: true }).value
  } catch {
    return escapeHtml(code)
  }
}

const marked = new Marked({
  gfm: true,
  breaks: false,
  renderer: {
    code({ text, lang }) {
      const name = (lang || '').trim().split(/\s+/)[0]?.toLowerCase() || ''
      const language = hljs.getLanguage(name) ? name : extLanguages[name]
      const html = language && hljs.getLanguage(language)
        ? hljs.highlight(text, { language, ignoreIllegals: true }).value
        : escapeHtml(text)
      return `<pre><code class="hljs">${html}</code></pre>\n`
    }
  }
})

// 用户上传的文件不可信：外部链接在新窗口打开
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A' && node.getAttribute('href')) {
    node.setAttribute('target', '_blank')
    node.setAttribute('rel', 'noopener noreferrer nofollow')
  }
})

/** Markdown 渲染为安全的 HTML（经 DOMPurify 过滤，防止 XSS） */
export function renderMarkdown(source: string): string {
  const html = marked.parse(source, { async: false }) as string
  // 保留任务列表的复选框（marked 输出为 disabled 的 input），去掉其它表单元素
  return DOMPurify.sanitize(html, { FORBID_TAGS: ['style', 'form', 'button', 'textarea', 'select'] })
}
