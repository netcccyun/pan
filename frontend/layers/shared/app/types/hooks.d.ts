declare module '#app' {
  interface RuntimeNuxtHooks {
    'api:unauthorized': () => void
  }
}

export {}
