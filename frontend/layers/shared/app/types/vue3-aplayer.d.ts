declare module 'vue3-aplayer' {
  import type { DefineComponent, Plugin } from 'vue'
  const APlayer: DefineComponent<Record<string, any>> & Plugin
  export default APlayer
}
