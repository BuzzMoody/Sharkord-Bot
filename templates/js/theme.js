/**
 * Sharkord Dark Theme Toggle
 *
 * Reads and persists the user's preferred colour scheme in localStorage.
 * Falls back to the OS/browser `prefers-color-scheme` media query when no
 * manual preference has been saved.  The active theme is applied by setting
 * `data-theme="dark"` or `data-theme="light"` on <html>; the CSS file reacts
 * to those attributes via attribute selectors and media query overrides.
 *
 * A small toggle button is injected into the DOM automatically — no manual
 * template changes are required beyond including this script.
 *
 * @module sharkord-theme
 */

(function () {

	'use strict';

	/** @type {string} localStorage key for the saved theme preference. */
	const STORAGE_KEY = 'sd-theme';

	/** @type {HTMLElement} */
	const html = document.documentElement;

	/**
	 * Determines whether the OS currently prefers a dark colour scheme.
	 *
	 * @returns {boolean}
	 */
	function systemPrefersDark() {
		return window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	/**
	 * Returns the resolved theme to apply: the saved preference, or the OS
	 * preference when no manual choice has been stored.
	 *
	 * @returns {'dark'|'light'}
	 */
	function resolveTheme() {
		const saved = localStorage.getItem(STORAGE_KEY);
		if (saved === 'dark' || saved === 'light') {
			return saved;
		}
		return systemPrefersDark() ? 'dark' : 'light';
	}

	/**
	 * Applies a theme by setting the data-theme attribute on <html> and
	 * updating the toggle button icon.
	 *
	 * @param {'dark'|'light'} theme
	 * @returns {void}
	 */
	function applyTheme(theme) {
		html.setAttribute('data-theme', theme);

		const btn = document.getElementById('sd-theme-toggle');
		if (btn) {
			btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
			btn.textContent = theme === 'dark' ? '☀' : '☾';
			btn.title       = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
		}
	}

	/**
	 * Toggles between dark and light, persists the choice, and applies it.
	 *
	 * @returns {void}
	 */
	function toggleTheme() {
		const current = html.getAttribute('data-theme') ?? resolveTheme();
		const next    = current === 'dark' ? 'light' : 'dark';
		localStorage.setItem(STORAGE_KEY, next);
		applyTheme(next);
	}

	/**
	 * Injects the floating theme-toggle button into the document body.
	 *
	 * @returns {void}
	 */
	function injectToggleButton() {
		const btn           = document.createElement('button');
		btn.id              = 'sd-theme-toggle';
		btn.type            = 'button';
		btn.setAttribute('aria-live', 'polite');
		btn.addEventListener('click', toggleTheme);
		document.body.appendChild(btn);
	}

	// ── Bootstrap ──────────────────────────────────────────────────────────────

	// Apply the saved / OS-preferred theme immediately to avoid flash of
	// light content before the stylesheet cascade resolves.
	applyTheme(resolveTheme());

	// Inject the button once the DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', injectToggleButton);
	} else {
		injectToggleButton();
	}

	// React to changes in the OS colour scheme while the page is open,
	// but only when the user has not made a manual choice.
	window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
		if (!localStorage.getItem(STORAGE_KEY)) {
			applyTheme(event.matches ? 'dark' : 'light');
		}
	});

}());
