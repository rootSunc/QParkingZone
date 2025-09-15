/// <reference types="vite/client" />

declare module 'leaflet' {
  const leaflet: any
  export default leaflet
}

declare module '*.vue' {
  import type {DefineComponent} from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}
