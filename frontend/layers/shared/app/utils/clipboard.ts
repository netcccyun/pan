/** 复制文本，非 HTTPS 环境下 navigator.clipboard 不可用时退回 execCommand */
export async function copyText(text: string): Promise<boolean> {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text)
      return true
    } catch {
      // fall through
    }
  }
  const active = document.activeElement as HTMLElement | null
  // 下拉菜单、弹窗会把焦点锁定在内部，文本框放在外面时无法获得焦点，复制到的是空内容，
  // 所以放进当前获得焦点的菜单或弹窗里
  const container = active?.closest<HTMLElement>('[role=menu],[role=dialog],[role=alertdialog]') ?? document.body
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', '')
  textarea.setAttribute('aria-hidden', 'true')
  textarea.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;padding:0;border:0;opacity:0;pointer-events:none'
  container.appendChild(textarea)
  textarea.focus({ preventScroll: true })
  textarea.select()
  textarea.setSelectionRange(0, text.length)
  let ok = false
  // 没能选中文本框时 execCommand 仍会返回 true，但什么也没复制，此时按失败处理
  if (document.activeElement === textarea) {
    try {
      ok = document.execCommand('copy')
    } catch {
      ok = false
    }
  }
  textarea.remove()
  if (active && document.contains(active)) active.focus({ preventScroll: true })
  return ok
}

export function useCopy() {
  const toast = useToast()
  return async (text: string) => {
    if (await copyText(text)) {
      toast.add({ title: '复制成功', color: 'success', icon: 'i-lucide-circle-check' })
    } else {
      toast.add({ title: '复制失败，请长按链接后手动复制', color: 'error', icon: 'i-lucide-circle-x' })
    }
  }
}
