export default defineNuxtPlugin(async () => {
  await useSite().load()
})
