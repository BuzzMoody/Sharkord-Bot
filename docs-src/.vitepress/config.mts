import { defineConfig } from 'vitepress'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

/**
 * Loads the auto-generated API sidebar produced by scripts/generate-api-docs.php.
 * Returns an empty array when the file hasn't been generated yet (i.e. on a
 * fresh checkout before the PHP script has been run).
 */
function loadApiSidebar(): unknown[] {

	const sidebarPath = resolve(__dirname, '../api/sidebar.json')

	if (!existsSync(sidebarPath)) {
		return []
	}

	return JSON.parse(readFileSync(sidebarPath, 'utf-8')) as unknown[]

}

const apiSidebar = loadApiSidebar()

export default defineConfig({

	base: process.env.VITEPRESS_BASE ?? '/',

	title:       'SharkordPHP',
	description: 'A ReactPHP Chatbot Framework for Sharkord',

	// Default to dark mode; users can still toggle via the built-in switcher.
	appearance: 'dark',

	head: [
		['meta', { name: 'theme-color', content: '#58a6ff' }],
		['meta', { name: 'og:type', content: 'website' }],
	],

	themeConfig: {

		nav: [
			{ text: 'Guide',         link: '/guide/getting-started' },
			{ text: 'API Reference', link: '/api/'                  },
			{
				text: 'GitHub',
				link: 'https://github.com/BuzzMoody/SharkordPHP',
			},
		],

		sidebar: {

			'/guide/': [
				{
					text: 'Guide',
					items: [
						{ text: 'Getting Started', link: '/guide/getting-started' },
						{ text: 'Examples',        link: '/guide/examples'        },
					],
				},
			],

			'/api/': [
				{
					text:  'API Reference',
					link:  '/api/',
					items: apiSidebar as never,
				},
			],

		},

		socialLinks: [
			{ icon: 'github', link: 'https://github.com/BuzzMoody/SharkordPHP' },
		],

		footer: {
			message:   'Released under the MIT License.',
			copyright: 'Copyright © 2024 BuzzMoody',
		},

		search: {
			provider: 'local',
		},

		editLink: {
			pattern: 'https://github.com/BuzzMoody/SharkordPHP/edit/main/docs-src/:path',
			text:    'Edit this page on GitHub',
		},

	},

	markdown: {
		theme: {
			light: 'github-light',
			dark:  'github-dark',
		},
	},

})
