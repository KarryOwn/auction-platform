const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

window.binPurchase = ({
	auctionId,
	binPrice,
	available = true,
	purchaseUrl,
	invoiceBaseUrl,
	loginUrl = null,
	modalName,
	thresholdPct = 0.75,
}) => ({
	auctionId,
	binPrice: Number(binPrice || 0),
	available: Boolean(available),
	loading: false,
	init() {
		window.addEventListener('bid:placed', (event) => {
			if (Number(event.detail.auctionId) !== Number(this.auctionId)) {
				return;
			}

			const explicitAvailability = event.detail.binAvailable ?? event.detail.bin_available ?? event.detail.is_buy_it_now_available;
			if (explicitAvailability === false) {
				this.available = false;
				return;
			}

			const latestPrice = Number(event.detail.newPrice ?? event.detail.new_price ?? event.detail.amount ?? 0);
			if (Number.isFinite(latestPrice) && latestPrice >= (this.binPrice * thresholdPct)) {
				this.available = false;
			}
		});

		window.addEventListener('auction:live-state', (event) => {
			if (Number(event.detail.auctionId) !== Number(this.auctionId)) {
				return;
			}

			if (typeof event.detail.is_buy_it_now_available !== 'undefined') {
				this.available = Boolean(event.detail.is_buy_it_now_available);
			}
		});
	},
	confirm() {
		if (this.loading) {
			return;
		}

		if (loginUrl && !document.body.dataset.authenticated) {
			window.location.href = loginUrl;
			return;
		}

		window.dispatchEvent(new CustomEvent('open-modal', { detail: modalName }));
	},
	close() {
		window.dispatchEvent(new CustomEvent('close-modal', { detail: modalName }));
	},
	async purchase() {
		if (this.loading) {
			return;
		}

		this.loading = true;

		try {
			const response = await fetch(purchaseUrl, {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': getCsrfToken(),
				},
				body: JSON.stringify({}),
			});

			const data = await response.json().catch(() => ({}));

			if (!response.ok || !data.success) {
				if ((data.message || '').toLowerCase().includes('no longer available')) {
					this.available = false;
				}

				window.toast?.error(data.message || 'Unable to complete Buy It Now purchase.');
				return;
			}

			this.close();
			window.location.href = `${invoiceBaseUrl}/${data.invoice_id}?source=bin`;
		} catch (_) {
			window.toast?.error('Network error. Please try again.');
		} finally {
			this.loading = false;
		}
	},
});
