import { hopeTheme } from "vuepress-theme-hope";
import navbar from './navbar';

export default hopeTheme({
    logo: 'images/hazaar-logo.svg',
    navbar,
    sidebar: {
        '/guide/': sidebarGuide(),
        '/example/': sidebarExample(),
        '/reference/': sidebarReference(),
        '/api/': [
            { text: 'API Reference', link: '/api/Home' },
        ],
    },
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

function sidebarGuide() {
    return [
        {
            text: 'Introduction',
            link: '/guide/introduction',
            items: [
                { text: 'What is Hazaar MVC?', link: '/guide/what-is-hazaar-mvc' },
                { text: 'Getting Started', link: '/guide/getting-started' },
                { text: 'Manual Setup', link: '/guide/manual-setup' },
                { text: 'Tooling', link: '/guide/tooling' },
                { text: 'What is MVC?', link: '/guide/what-is-mvc' },
                { text: 'Licence', link: '/guide/licence' }
            ]
        },
        {
            text: 'The Basics',
            items: [
                { text: 'Project Layout', link: '/guide/basics/layout' },
                { text: 'Configuration', link: '/guide/basics/configuration' },
                { text: 'Bootstrap', link: '/guide/basics/bootstrap' },
                { text: 'Routing', link: '/guide/basics/routing' },
                { text: 'Controllers', link: '/guide/basics/controllers' },
                { text: 'Views', link: '/guide/basics/views' },
                { text: 'Models', link: '/guide/basics/models' },
                { text: 'Security', link: '/guide/basics/security' },
                { text: 'Generating URLs', link: '/guide/basics/urls' },
                { text: 'View Helpers', link: '/guide/basics/view-helpers', },
                { text: 'Helpers', link: '/guide/basics/helper-functions' }
            ]
        },
        {
            text: 'Databases',
            items: [
                { text: 'Overview', link: '/guide/dbi/overview', },
                { text: 'Configuration', link: '/guide/dbi/configure', },
                { text: 'Schema Manager', link: '/guide/dbi/schema-manager', },
                { text: 'Data Sync', link: '/guide/dbi/data-sync', },
                { text: 'Encryption', link: '/guide/dbi/encryption', },
                { text: 'Filesystem', link: '/guide/dbi/filesystem', },
                { text: 'Parser', link: '/guide/dbi/parser', }
            ]
        },
        {
            text: 'Advanced',
            items: [
                { text: 'Caching', link: '/guide/advanced/caching/overview' },
                { text: 'PDF Generation', link: '/guide/advanced/pdf-generation' },
                { text: 'Strict Models', link: '/guide/advanced/strict-models' },
                { text: 'XML-RPC', link: '/guide/advanced/xml-rpc' }
            ]
        },
        {
            text: 'Warlock',
            items: [
                { text: 'Overview', link: '/guide/warlock/overview' },
                { text: 'Delayed Execution', link: '/guide/warlock/delayed-exec' },
                { text: 'Realtime Signals', link: '/guide/warlock/realtime-signalling' },
                { text: 'Services', link: '/guide/warlock/services' },
                { text: 'Key/Value Storage', link: '/guide/warlock/kvstore' },
                { text: 'Global Events', link: '/guide/warlock/global-events' }
            ]
        },
        {
            text: 'Deploy',
            items: [
                { text: 'Overview', link: '/guide/deploy/overview' },
                { text: 'Apache', link: '/guide/deploy/apache' },
                { text: 'Nginx', link: '/guide/deploy/nginx' },
                { text: 'Docker', link: '/guide/deploy/docker' }
            ]
        }
    ]
}

function sidebarExample() {
    return [
        { text: 'Your First App', link: '/example/your-first-app' },
        { text: 'Controller Responses', link: '/example/responses' },
        { text: 'A useful example', link: '/example/something-useful' }
    ]
}

function sidebarReference() {
    return [
        { text: 'Constants', link: '/reference/constants' },
        { text: 'The Hazaar Tool', link: '/reference/hazaar-tool' },
        { text: 'Error Codes', link: '/reference/error-codes' },
        { text: 'HTTP Status Codes', link: '/reference/http-status-codes' }
    ]
}
