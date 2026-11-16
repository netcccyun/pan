<script setup lang="ts">
/**
 * 文本/代码文件在线预览
 * 代码高亮使用 highlight.js，Markdown 使用 marked + DOMPurify，均在打开预览时按需加载
 */
const props = defineProps<{
  /** 文件后缀 */
  type: string
  /** 读取文件内容 */
  load: () => Promise<string>
  /** 内容区域的最大高度 */
  maxHeight?: string
}>()

type Lib = typeof import('../lib/textPreview')

const copy = useCopy()
const loading = ref(true)
const error = ref('')
const content = ref('')
const lib = shallowRef<Lib | null>(null)

const isMarkdown = computed(() => ['md', 'markdown'].includes(props.type.toLowerCase()))
const mode = ref<'render' | 'source'>(isMarkdown.value ? 'render' : 'source')
const wrap = ref(false)

const modeItems = [
  { label: '预览', value: 'render', icon: 'i-lucide-eye' },
  { label: '源码', value: 'source', icon: 'i-lucide-code' }
]

async function fetchContent() {
  loading.value = true
  error.value = ''
  try {
    const [text, mod] = await Promise.all([props.load(), lib.value || import('../lib/textPreview')])
    lib.value = mod
    content.value = text.replace(/\r\n?/g, '\n')
  } catch (e: any) {
    error.value = e?.message || '读取文件失败'
  } finally {
    loading.value = false
  }
}

onMounted(fetchContent)

const lineCount = computed(() => {
  if (!content.value) return 0
  return content.value.split('\n').length - (content.value.endsWith('\n') ? 1 : 0)
})

/** 将高亮结果按行拆分：跨行的 span 在行尾闭合、下一行重新打开，这样每行可以单独显示行号 */
function splitLines(html: string): string {
  const stack: string[] = []
  const lines = html.split('\n')
  if (lines.length > 1 && lines[lines.length - 1] === '') lines.pop()
  let out = ''
  for (const line of lines) {
    let row = stack.join('')
    const re = /<span[^>]*>|<\/span>/g
    let m: RegExpExecArray | null
    while ((m = re.exec(line))) {
      if (m[0] === '</span>') stack.pop()
      else stack.push(m[0])
    }
    row += line + '</span>'.repeat(stack.length)
    out += `<div class="tp-line">${row || ' '}</div>`
  }
  return out
}

const codeHtml = computed(() => (lib.value && mode.value === 'source' ? splitLines(lib.value.highlightCode(content.value, props.type)) : ''))
const markdownHtml = computed(() => (lib.value && isMarkdown.value && mode.value === 'render' ? lib.value.renderMarkdown(content.value) : ''))
const gutter = computed(() => `${Math.max(2, String(lineCount.value).length) + 1}ch`)
</script>

<template>
  <div class="text-preview min-w-0">
    <div v-if="loading" class="space-y-2 py-2">
      <USkeleton class="h-4 w-2/3" />
      <USkeleton class="h-4 w-5/6" />
      <USkeleton class="h-4 w-1/2" />
      <USkeleton class="h-4 w-3/4" />
    </div>

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-x"
      :title="error"
      :actions="[{ label: '重试', color: 'error', variant: 'outline', onClick: fetchContent }]"
    />

    <template v-else>
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <UFieldGroup v-if="isMarkdown" size="sm">
          <UButton
            v-for="item in modeItems"
            :key="item.value"
            :label="item.label"
            :icon="item.icon"
            :color="mode === item.value ? 'primary' : 'neutral'"
            :variant="mode === item.value ? 'soft' : 'outline'"
            @click="mode = item.value as 'render' | 'source'"
          />
        </UFieldGroup>
        <span class="text-xs text-(--ui-text-muted)">共 {{ lineCount }} 行</span>
        <div class="ms-auto flex items-center gap-2">
          <UButton
            v-if="mode === 'source'"
            size="sm"
            :color="wrap ? 'primary' : 'neutral'"
            :variant="wrap ? 'soft' : 'ghost'"
            icon="i-lucide-wrap-text"
            label="自动换行"
            @click="wrap = !wrap"
          />
          <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-copy" label="复制" @click="copy(content)" />
        </div>
      </div>

      <div
        class="overflow-auto rounded-md border border-(--ui-border) bg-(--ui-bg)"
        :style="{ maxHeight: maxHeight || '70vh' }"
      >
        <!-- eslint-disable vue/no-v-html -->
        <div v-if="mode === 'render' && isMarkdown" class="tp-md p-4 sm:p-6" v-html="markdownHtml" />
        <pre
          v-else
          class="tp-code hljs"
          :class="{ 'tp-wrap': wrap }"
          :style="{ '--tp-gutter': gutter }"
        ><code v-html="codeHtml" /></pre>
        <!-- eslint-enable vue/no-v-html -->
      </div>
    </template>
  </div>
</template>

<style>
/* 代码区域 */
.text-preview .tp-code {
  margin: 0;
  padding: 0.75rem 0;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  font-size: 13px;
  line-height: 1.6;
  counter-reset: tp-line;
  width: max-content;
  min-width: 100%;
}
.text-preview .tp-code.tp-wrap {
  width: auto;
}
.text-preview .tp-line {
  position: relative;
  padding: 0 1rem 0 calc(var(--tp-gutter) + 1.75rem);
  white-space: pre;
}
.text-preview .tp-wrap .tp-line {
  white-space: pre-wrap;
  word-break: break-all;
}
.text-preview .tp-line::before {
  counter-increment: tp-line;
  content: counter(tp-line);
  position: absolute;
  left: 0;
  width: calc(var(--tp-gutter) + 0.75rem);
  text-align: right;
  color: var(--ui-text-dimmed);
  user-select: none;
}
.text-preview .tp-line:hover {
  background: var(--ui-bg-elevated);
}

/* 代码高亮配色（参考 GitHub 主题） */
.text-preview .hljs { color: #1f2328; }
.text-preview .hljs-doctag,
.text-preview .hljs-keyword,
.text-preview .hljs-meta .hljs-keyword,
.text-preview .hljs-template-tag,
.text-preview .hljs-template-variable,
.text-preview .hljs-type,
.text-preview .hljs-variable.language_ { color: #cf222e; }
.text-preview .hljs-title,
.text-preview .hljs-title.class_,
.text-preview .hljs-title.function_ { color: #8250df; }
.text-preview .hljs-attr,
.text-preview .hljs-attribute,
.text-preview .hljs-literal,
.text-preview .hljs-meta,
.text-preview .hljs-number,
.text-preview .hljs-operator,
.text-preview .hljs-variable,
.text-preview .hljs-selector-attr,
.text-preview .hljs-selector-class,
.text-preview .hljs-selector-id { color: #0550ae; }
.text-preview .hljs-regexp,
.text-preview .hljs-string,
.text-preview .hljs-meta .hljs-string { color: #0a3069; }
.text-preview .hljs-built_in,
.text-preview .hljs-symbol { color: #953800; }
.text-preview .hljs-comment,
.text-preview .hljs-code,
.text-preview .hljs-formula { color: #6e7781; }
.text-preview .hljs-name,
.text-preview .hljs-quote,
.text-preview .hljs-selector-tag,
.text-preview .hljs-selector-pseudo { color: #116329; }
.text-preview .hljs-subst { color: #1f2328; }
.text-preview .hljs-section { color: #0550ae; font-weight: bold; }
.text-preview .hljs-bullet { color: #953800; }
.text-preview .hljs-emphasis { font-style: italic; }
.text-preview .hljs-strong { font-weight: bold; }
.text-preview .hljs-addition { color: #116329; background-color: #dafbe1; }
.text-preview .hljs-deletion { color: #82071e; background-color: #ffebe9; }

.dark .text-preview .hljs { color: #e6edf3; }
.dark .text-preview .hljs-doctag,
.dark .text-preview .hljs-keyword,
.dark .text-preview .hljs-meta .hljs-keyword,
.dark .text-preview .hljs-template-tag,
.dark .text-preview .hljs-template-variable,
.dark .text-preview .hljs-type,
.dark .text-preview .hljs-variable.language_ { color: #ff7b72; }
.dark .text-preview .hljs-title,
.dark .text-preview .hljs-title.class_,
.dark .text-preview .hljs-title.function_ { color: #d2a8ff; }
.dark .text-preview .hljs-attr,
.dark .text-preview .hljs-attribute,
.dark .text-preview .hljs-literal,
.dark .text-preview .hljs-meta,
.dark .text-preview .hljs-number,
.dark .text-preview .hljs-operator,
.dark .text-preview .hljs-variable,
.dark .text-preview .hljs-selector-attr,
.dark .text-preview .hljs-selector-class,
.dark .text-preview .hljs-selector-id { color: #79c0ff; }
.dark .text-preview .hljs-regexp,
.dark .text-preview .hljs-string,
.dark .text-preview .hljs-meta .hljs-string { color: #a5d6ff; }
.dark .text-preview .hljs-built_in,
.dark .text-preview .hljs-symbol { color: #ffa657; }
.dark .text-preview .hljs-comment,
.dark .text-preview .hljs-code,
.dark .text-preview .hljs-formula { color: #8b949e; }
.dark .text-preview .hljs-name,
.dark .text-preview .hljs-quote,
.dark .text-preview .hljs-selector-tag,
.dark .text-preview .hljs-selector-pseudo { color: #7ee787; }
.dark .text-preview .hljs-subst { color: #e6edf3; }
.dark .text-preview .hljs-section { color: #1f6feb; }
.dark .text-preview .hljs-bullet { color: #f2cc60; }
.dark .text-preview .hljs-addition { color: #aff5b4; background-color: #033a16; }
.dark .text-preview .hljs-deletion { color: #ffdcd7; background-color: #67060c; }

/* Markdown 渲染 */
.text-preview .tp-md {
  font-size: 15px;
  line-height: 1.75;
  color: var(--ui-text);
  word-wrap: break-word;
}
.text-preview .tp-md > :first-child { margin-top: 0; }
.text-preview .tp-md > :last-child { margin-bottom: 0; }
.text-preview .tp-md h1,
.text-preview .tp-md h2,
.text-preview .tp-md h3,
.text-preview .tp-md h4,
.text-preview .tp-md h5,
.text-preview .tp-md h6 {
  margin: 1.5em 0 0.75em;
  font-weight: 600;
  line-height: 1.3;
  color: var(--ui-text-highlighted);
}
.text-preview .tp-md h1 { font-size: 1.75em; padding-bottom: 0.3em; border-bottom: 1px solid var(--ui-border); }
.text-preview .tp-md h2 { font-size: 1.45em; padding-bottom: 0.3em; border-bottom: 1px solid var(--ui-border); }
.text-preview .tp-md h3 { font-size: 1.2em; }
.text-preview .tp-md h4 { font-size: 1.05em; }
.text-preview .tp-md p,
.text-preview .tp-md ul,
.text-preview .tp-md ol,
.text-preview .tp-md blockquote,
.text-preview .tp-md table,
.text-preview .tp-md pre { margin: 0 0 1em; }
.text-preview .tp-md ul { list-style: disc; padding-left: 1.75em; }
.text-preview .tp-md ol { list-style: decimal; padding-left: 1.75em; }
.text-preview .tp-md li + li { margin-top: 0.25em; }
.text-preview .tp-md li > ul,
.text-preview .tp-md li > ol { margin: 0.25em 0 0; }
.text-preview .tp-md input[type="checkbox"] { margin-right: 0.4em; }
.text-preview .tp-md a { color: var(--ui-primary); text-decoration: underline; text-underline-offset: 2px; }
.text-preview .tp-md img { display: inline-block; max-width: 100%; }
.text-preview .tp-md hr { margin: 1.5em 0; border: 0; border-top: 1px solid var(--ui-border); }
.text-preview .tp-md blockquote {
  padding: 0.25em 1em;
  color: var(--ui-text-muted);
  border-left: 4px solid var(--ui-border-accented);
}
.text-preview .tp-md code {
  padding: 0.15em 0.4em;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  font-size: 0.875em;
  border-radius: 4px;
  background: var(--ui-bg-elevated);
}
.text-preview .tp-md pre {
  padding: 0.875rem 1rem;
  overflow-x: auto;
  font-size: 13px;
  line-height: 1.6;
  border-radius: 6px;
  background: var(--ui-bg-muted);
  border: 1px solid var(--ui-border);
}
.text-preview .tp-md pre code { padding: 0; font-size: inherit; background: transparent; }
.text-preview .tp-md table { display: block; width: max-content; max-width: 100%; overflow: auto; border-collapse: collapse; }
.text-preview .tp-md th,
.text-preview .tp-md td { padding: 0.4em 0.8em; border: 1px solid var(--ui-border); }
.text-preview .tp-md th { font-weight: 600; background: var(--ui-bg-muted); }
</style>
