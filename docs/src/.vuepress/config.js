import { defineUserConfig } from 'vuepress/cli'
import { viteBundler } from '@vuepress/bundler-vite'
import theme from './theme.js'

export default defineUserConfig({
    lang: 'en-US',
    title: 'Hazaar',
    description: 'The Simple, Fast, Reliable framework for PHP',
    head: [
        [
            'link',
            {
                rel: 'icon',
                href: '/hazaar.png'
            }
        ],
        ['meta', { name: 'theme-color', content: '#3c8772' }]
    ],
    theme,
    bundler: viteBundler()
});
