import { defineConfig } from 'vitepress'

const repo = 'https://github.com/hpbxxtr/composer-upgrade-interactive'

export default defineConfig({
    title: 'composer upgrade-interactive',
    description:
        'Interactive TUI for selectively upgrading Composer dependencies (yarn upgrade-interactive for PHP)',

    base: '/composer-upgrade-interactive/',
    cleanUrls: true,
    lastUpdated: true,

    head: [
        ['link', { rel: 'icon', type: 'image/svg+xml', href: '/composer-upgrade-interactive/favicon.svg' }],
        ['meta', { name: 'theme-color', content: '#ff00ff' }],
    ],

    sitemap: {
        hostname: 'https://hpbxxtr.github.io/composer-upgrade-interactive/',
    },

    themeConfig: {
        nav: [
            { text: 'Guide', link: '/guide/installation', activeMatch: '/guide/' },
            { text: 'Reference', link: '/reference/cli', activeMatch: '/reference/' },
            {
                text: 'Links',
                items: [
                    { text: 'Packagist', link: 'https://packagist.org/packages/hpbxxtr/composer-upgrade-interactive' },
                    { text: 'Releases', link: `${repo}/releases` },
                    { text: 'Issues', link: `${repo}/issues` },
                ],
            },
        ],

        sidebar: [
            {
                text: 'Guide',
                items: [
                    { text: 'Installation', link: '/guide/installation' },
                    { text: 'Usage', link: '/guide/usage' },
                    { text: 'Minimum release age', link: '/guide/minimum-release-age' },
                    { text: 'Key bindings', link: '/guide/key-bindings' },
                    { text: 'How it works', link: '/guide/how-it-works' },
                ],
            },
            {
                text: 'Reference',
                items: [
                    { text: 'CLI', link: '/reference/cli' },
                    { text: 'Configuration', link: '/reference/configuration' },
                ],
            },
        ],

        socialLinks: [{ icon: 'github', link: repo }],

        editLink: {
            // Maintenance branch, not `main` — this repo versions its develop branches.
            pattern: `${repo}/edit/dev/0.4/docs/:path`,
            text: 'Edit this page on GitHub',
        },

        search: {
            provider: 'local',
        },

        footer: {
            message: `Released under the <a href="${repo}/blob/dev/0.4/LICENSE">MIT License</a>.`,
            copyright: `<a href="${repo}">hpbxxtr/composer-upgrade-interactive</a>`,
        },
    },
})
