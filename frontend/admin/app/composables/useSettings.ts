import type { Conf } from '~/utils/settings'

export function useSettings() {
  const { get } = useApi()
  const toast = useToast()

  const conf = ref<Conf>({})
  const siteurl = ref('')
  const loading = ref(true)

  const load = async () => {
    loading.value = true
    try {
      const res = await get('getSetting')
      conf.value = res.data || {}
      siteurl.value = res.siteurl || ''
    } catch (e: any) {
      toast.add({ title: e?.message || '加载设置失败', color: 'error' })
    } finally {
      loading.value = false
    }
  }

  onMounted(load)

  return { conf, siteurl, loading, load }
}
