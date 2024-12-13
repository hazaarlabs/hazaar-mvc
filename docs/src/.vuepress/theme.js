import { hopeTheme } from "vuepress-theme-hope";
import navbar from './navbar';
import sidebar from './sidebar';

export default hopeTheme({
    logo: 'images/hazaar-logo.svg',
    navbar,
    sidebar,
    displayFooter: true,
    copyright: 'Copyright © 2012-present Hazaar Labs',
    plugins: {
        markdownTab: {
            tabs: true,
            codeTabs: true,
        },
        mdEnhance: {
            echarts: true,
            flowchart: true,
            markmap: true,
            mermaid: true,
        },
        copyright: {
            global: true,
            author: 'Hazaar Labs',
            license: 'Apache-2.0'
        }
    }
});

