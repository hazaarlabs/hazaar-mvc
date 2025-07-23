import { hopeTheme } from "vuepress-theme-hope";
import navbar from './navbar';
import sidebar from './sidebar';

export default hopeTheme({
    logo: 'images/hazaar-logo.svg',
    changelog: true,
    navbar,
    sidebar,
    displayFooter: true,
    copyright: 'Copyright © 2012-present Hazaar Labs',
    markdown: {
        tabs: true,
        codeTabs: true,
        echarts: true,
        flowchart: true,
        markmap: true,
        mermaid: true,
        preview: true,
    },
    plugins: {
        copyright: {
            global: true,
            author: 'Hazaar Labs',
            license: 'Apache-2.0'
        }
    }
});

