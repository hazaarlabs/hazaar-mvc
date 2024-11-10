import { defineUserConfig } from 'vuepress/cli'
import { viteBundler } from '@vuepress/bundler-vite'
import theme from './theme.js'

export default defineUserConfig({
    lang: 'en-US',
    title: 'Hazaar MVC',
    description: 'A lightweight, high performance MVC framework for PHP',
    head: [
        ['meta', { name: 'theme-color', content: '#3c8772' }]
    ],
    theme,
    bundler: viteBundler(),
});
