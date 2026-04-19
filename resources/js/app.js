import './bootstrap';
import './bid-events';
import './bid-ui';
import './toast';
import './filepond-setup';

import Alpine from 'alpinejs';

import TomSelect from 'tom-select';
window.TomSelect = TomSelect;

window.Alpine = Alpine;

if (typeof window !== 'undefined' && typeof window.fetch === 'function' && !window.__fetchProgressPatched) {
	const originalFetch = window.fetch.bind(window);
	let activeFetches = 0;

	const showProgress = () => {
		const bar = document.getElementById('page-progress');
		if (!bar) {
			return;
		}

		bar.style.opacity = '1';
		bar.style.width = '30%';
	};

	const hideProgress = () => {
		const bar = document.getElementById('page-progress');
		if (!bar) {
			return;
		}

		bar.style.width = '100%';
		window.setTimeout(() => {
			bar.style.opacity = '0';
			bar.style.width = '0';
		}, 300);
	};

	window.fetch = (...args) => {
		activeFetches += 1;
		if (activeFetches === 1) {
			showProgress();
		}

		return originalFetch(...args).finally(() => {
			activeFetches = Math.max(0, activeFetches - 1);
			if (activeFetches === 0) {
				hideProgress();
			}
		});
	};

	window.__fetchProgressPatched = true;
}

document.addEventListener('keydown', (e) => {
	if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

	if (e.key === 'b' && !e.metaKey && !e.ctrlKey) {
		const bidInput = document.getElementById('bid-amount');
		if (bidInput) {
			bidInput.focus();
			bidInput.select();
		}
	}

	if (e.key === 'w' && !e.metaKey && !e.ctrlKey) {
		const watchBtn = document.getElementById('watch-btn');
		if (watchBtn) {
			watchBtn.click();
		}
	}
});

Alpine.start();
