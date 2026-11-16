export default defineNuxtPlugin(async (nuxtApp) => {
  const { status, load } = useAdmin()
  await load()

  // 任意接口返回未登录（例如登录已过期）时回到登录页
  nuxtApp.hook('api:unauthorized', () => {
    if (!status.value.islogin) return
    status.value = { ...status.value, islogin: 0, admin_user: null }
    navigateTo('/login')
  })
})
