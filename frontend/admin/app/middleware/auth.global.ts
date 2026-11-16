export default defineNuxtRouteMiddleware((to) => {
  const { isLogin, halt } = useAdmin()
  if (halt.value) return
  if (!isLogin.value && to.path !== '/login') {
    return navigateTo({ path: '/login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} })
  }
  if (isLogin.value && to.path === '/login') return navigateTo('/')
})
