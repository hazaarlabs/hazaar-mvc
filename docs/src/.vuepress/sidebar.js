import api from './api_sidebar';
export default {
    '/docs/': sidebarGuide(),
    '/examples/': sidebarExamples(),
    '/api/': api
}

function sidebarGuide() {
    return [
        {
            text: 'Getting Started',
            collapsible: true,
            icon: 'ic:baseline-add-location',
            children: [
                {
                    text: 'Installation', link: '/docs/start/installoverview',
                    "children": [
                        { text: 'Composer', link: '/docs/start/install/composer' },
                        { text: 'Manual', link: '/docs/start/install/manual' },
                        { text: 'Dev Container', link: '/docs/start/install/devcontainer' }
                    ]
                },
                { text: 'Configuration', link: '/docs/start/configuration' },
                { text: 'Directory Structure', link: '/docs/start/structure' },
                {
                    text: 'Deployment',
                    collapsible: true,
                    children: [
                        { text: 'Overview', link: '/docs/deploy/overview' },
                        { text: 'Apache', link: '/docs/deploy/apache' },
                        { text: 'Nginx', link: '/docs/deploy/nginx' },
                        { text: 'Docker', link: '/docs/deploy/docker' },
                        { text: 'FrankenPHP', link: '/docs/deploy/frankenphp' }
                    ]
                }
            ]
        },
        {
            text: 'Concepts',
            collapsible: true,
            children: [
                { text: 'Request Lifecycle', link: '/docs/concepts/lifecycle' },
                { text: 'The Application', link: '/docs/concepts/application' },
                { text: 'Performance', link: '/docs/concepts/performance' },
                { text: 'What is MVC?', link: '/docs/concepts/what-is-mvc' },

            ]
        },
        {
            text: 'The Basics',
            collapsible: true,
            children: [
                { text: 'Routing', link: '/docs/basics/routing' },
                { text: 'Requests', link: '/docs/basics/requests' },
                { text: 'Controllers', link: '/docs/basics/controllers' },
                { text: 'Models', link: '/docs/basics/models' },
                {
                    text: 'Views',
                    collapsible: true,
                    children: [
                        { text: 'Overview', link: '/docs/basics/views/overview' },
                        { text: 'Helpers', link: '/docs/basics/views/helpers' }
                    ]
                },
                { text: 'Security', link: '/docs/basics/security' },
                { text: 'Generating URLs', link: '/docs/basics/urls' },
                { text: 'Helper Functions', link: '/docs/basics/helpers' },
            ]
        },
        {
            text: 'Databases',
            collapsible: true,
            children: [
                { text: 'Overview', link: '/docs/dbi/overview', },
                { text: 'Configuration', link: '/docs/dbi/configure', },
                { text: 'CRUD', link: '/docs/dbi/crud', },
                { text: 'Schema Manager', link: '/docs/dbi/schema-manager', },
                { text: 'Data Sync', link: '/docs/dbi/data-sync', },
                { text: 'Encryption', link: '/docs/dbi/encryption', },
                { text: 'Filesystem', link: '/docs/dbi/filesystem', },
                { text: 'Parser', link: '/docs/dbi/parser', }
            ]
        },
        {
            text: 'Advanced Stuff',
            collapsible: true,
            children: [
                {
                    text: 'Caching',
                    collapsible: true,
                    children: [
                        { text: 'Overview', link: '/docs/advanced/caching/overview' },
                        { text: 'Frontends', link: '/docs/advanced/caching/frontends' },
                        { text: 'Backends', link: '/docs/advanced/caching/backends' }
                    ]
                },
                { text: 'Strict Models', link: '/docs/advanced/strict-models' },
                { text: 'Generating PDFs', link: '/docs/advanced/pdf' },
                { text: 'XML-RPC', link: '/docs/advanced/xml-rpc' },
                { text: 'Streaming', link: '/docs/advanced/streams' },
                { text: 'Money', link: '/docs/advanced/money' },
                { text: 'Multiple Class Inheritance', link: '/docs/advanced/multiple-class-inheritance' },
                { text: 'Logging', link: '/docs/advanced/logging' },
                { text: 'WebDAV & CalDAV', link: '/docs/advanced/dav' },
                { text: 'Filesystem Browser', link: '/docs/advanced/filesystem-browser' },
            ]
        },
        {
            text: 'Components',
            collapsible: true,
            children: [
                {
                    text: 'Warlock',
                    collapsible: true,
                    children: [
                        { text: 'Overview', link: '/docs/components/warlock/overview' },
                        { text: 'Delayed Execution', link: '/docs/components/warlock/delayed-exec' },
                        { text: 'Realtime Signals', link: '/docs/components/warlock/realtime-signalling' },
                        { text: 'Services', link: '/docs/components/warlock/services' },
                        { text: 'Key/Value Storage', link: '/docs/components/warlock/kvstore' },
                        { text: 'Global Events', link: '/docs/components/warlock/global-events' }
                    ]
                }
            ]
        },
        {
            text: 'Reference',
            collapsible: true,
            children: [
                { text: 'Constants', link: '/docs/reference/constants' },
                { text: 'CLI Tools', link: '/docs/reference/cli-tools' },
                { text: 'Error Codes', link: '/docs/reference/error-codes' },
                { text: 'HTTP Status Codes', link: '/docs/reference/http-status-codes' }
            ]
        },
        { text: 'Licence', link: '/docs/licence' }
    ]
}

function sidebarExamples() {
    return [
        {
            "text": "Overview",
            "link": "/examples/"
        },
        {
            "text": "Your First Application",
            "link": "/examples/your-first-app"
        },
        {
            "text": "Routing",
            "link": "/examples/routing"
        },
        {
            "text": "Using Templates",
            "link": "/examples/templates"
        },
        {
            "text": "Using Databases",
            "link": "/examples/databases"
        },
        {
            "text": "Controller Responses",
            "link": "/examples/responses"
        },
        {
            "text": "Applications",
            "collapsible": true,
            "children": [
                {
                    "text": "REST API",
                    "link": "/examples/apps/rest-api"
                },
                {
                    "text": "Web Application",
                    "link": "/examples/apps/web"
                },
                {
                    "text": "CLI Application",
                    "link": "/examples/apps/cli"
                }
            ]
        }
    ]
}