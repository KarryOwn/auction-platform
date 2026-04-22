const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

window.autoSave = ({ url, fieldSelector = '[data-autosave]', delay = 2000 }) => ({
	status: 'idle',
	savedAt: null,
	dirty: false,
	disabled: false,
	timer: null,
	async init() {
		const fields = Array.from(document.querySelectorAll(fieldSelector));

		fields.forEach((field) => {
			const eventName = field.matches('input[type="checkbox"], input[type="radio"], select') ? 'change' : 'input';
			field.addEventListener(eventName, () => this.schedule());
		});

		window.addEventListener('beforeunload', () => {
			if (this.dirty && !this.disabled) {
				this.flush({ keepalive: true });
			}
		});
	},
	schedule() {
		if (this.disabled) {
			return;
		}

		this.dirty = true;
		this.status = 'dirty';

		if (this.timer) {
			window.clearTimeout(this.timer);
		}

		this.timer = window.setTimeout(() => this.flush(), delay);
	},
	payload() {
		const data = {};

		document.querySelectorAll(fieldSelector).forEach((field) => {
			if (!field.name) {
				return;
			}

			if (field.type === 'checkbox') {
				data[field.name] = field.checked ? 1 : 0;
				return;
			}

			data[field.name] = field.value;
		});

		return data;
	},
	async flush(options = {}) {
		if (this.disabled || !this.dirty) {
			return;
		}

		if (this.timer) {
			window.clearTimeout(this.timer);
			this.timer = null;
		}

		this.status = 'saving';

		try {
			const response = await fetch(url, {
				method: 'PATCH',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken(),
				},
				body: JSON.stringify(this.payload()),
				keepalive: options.keepalive ?? false,
			});

			if (response.status === 429) {
				this.status = 'idle';
				this.dirty = false;
				return;
			}

			const data = await response.json().catch(() => ({}));

			if (response.status === 422) {
				this.status = 'disabled';
				this.disabled = true;
				this.dirty = false;
				return;
			}

			if (!response.ok) {
				throw new Error(data.error ?? 'Auto-save failed.');
			}

			this.dirty = false;
			this.savedAt = data.auto_saved_at;
			this.status = 'saved';

			window.setTimeout(() => {
				if (this.status === 'saved') {
					this.status = 'idle';
				}
			}, 5000);
		} catch (_) {
			this.status = 'error';
		}
	},
	statusLabel() {
		if (this.status === 'saving') return 'Saving...';
		if (this.status === 'saved' && this.savedAt) {
			const time = new Date(this.savedAt);
			if (!Number.isNaN(time.getTime())) {
				return `Saved at ${time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
			}
		}
		if (this.status === 'disabled') return 'Auto-save unavailable';
		if (this.status === 'error') return 'Auto-save failed';
		if (this.status === 'dirty') return 'Unsaved changes';
		return 'Draft auto-save enabled';
	},
	statusClass() {
		if (this.status === 'saving') return 'text-amber-700';
		if (this.status === 'saved') return 'text-green-700';
		if (this.status === 'disabled' || this.status === 'error') return 'text-red-700';
		return 'text-gray-500';
	},
});
