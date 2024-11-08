// https://vitepress.dev/guide/custom-theme
import Theme from 'vitepress/theme'
import { useData } from "vitepress";
import CustomLayout from "./CustomLayout.vue";
import './style.css'

export default {
  extends: Theme,
  Layout: CustomLayout,
  enhanceApp({ app, router }) {
    Object.defineProperty(app.config.globalProperties, "$dark", { get: () => useData().isDark.value });
  }
}
