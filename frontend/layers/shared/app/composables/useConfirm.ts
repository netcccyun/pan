import ConfirmModal from '../components/ConfirmModal.vue'

interface ConfirmOptions {
  title?: string
  description?: string
  confirmText?: string
  color?: 'primary' | 'error' | 'warning' | 'success' | 'neutral'
  /**
   * 点击确定后执行的耗时操作（如删除），执行期间弹窗保持打开并显示加载中；
   * 操作出错时弹窗关闭，错误由 confirm() 抛出
   */
  onConfirm?: () => Promise<unknown>
}

export function useConfirm() {
  const overlay = useOverlay()
  const modal = overlay.create(ConfirmModal)
  return async (options: ConfirmOptions): Promise<boolean> => {
    const instance: any = modal.open(options)
    const result = await (instance?.result ?? instance)
    if (result && typeof result === 'object' && 'error' in result) throw result.error
    return result === true
  }
}
